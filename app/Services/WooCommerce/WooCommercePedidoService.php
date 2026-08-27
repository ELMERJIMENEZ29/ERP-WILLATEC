<?php

namespace App\Services\WooCommerce;

use App\Models\Producto;
use App\Models\WooCommercePedido;
use App\Models\WooCommercePedidoItem;
use App\Models\WooCommerceProducto;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WooCommercePedidoService
{
    public function __construct(
        private readonly WooCommerceService $wooCommerceService,
        private readonly InventarioService $inventarioService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sincronizar(int $perPage = 25, ?string $status = null): array
    {
        $orders = $this->wooCommerceService->obtenerPedidos([
            'per_page' => max(1, min($perPage, 100)),
            'orderby' => 'date',
            'order' => 'desc',
            'status' => $status ?: 'any',
        ]);

        $importados = collect($orders)
            ->map(fn (array $order): WooCommercePedido => $this->importarPedido($order))
            ->values();

        return [
            'total' => $importados->count(),
            'ids' => $importados->pluck('id')->all(),
        ];
    }

    public function importarPedido(array $order): WooCommercePedido
    {
        return DB::transaction(function () use ($order): WooCommercePedido {
            $wooOrderId = (int) ($order['id'] ?? 0);

            if ($wooOrderId <= 0) {
                throw ValidationException::withMessages([
                    'woo_order_id' => 'WooCommerce no devolvio un ID de pedido valido.',
                ]);
            }

            $billing = $order['billing'] ?? [];
            $pedido = WooCommercePedido::query()->updateOrCreate(
                ['woo_order_id' => $wooOrderId],
                [
                    'numero' => (string) ($order['number'] ?? $wooOrderId),
                    'estado_woo' => (string) ($order['status'] ?? ''),
                    'cliente_nombre' => trim((string) (($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')))
                        ?: ($billing['company'] ?? null),
                    'cliente_email' => $billing['email'] ?? null,
                    'cliente_telefono' => $billing['phone'] ?? null,
                    'moneda' => $order['currency'] ?? null,
                    'total' => (float) ($order['total'] ?? 0),
                    'fecha_woo' => $this->parseWooDate($order['date_created_gmt'] ?? $order['date_created'] ?? null),
                    'last_synced_at' => now(),
                    'raw_payload' => $order,
                ]
            );

            $lineItems = collect($order['line_items'] ?? []);
            $lineIds = [];

            foreach ($lineItems as $lineItem) {
                $item = $this->upsertItem($pedido, (array) $lineItem);
                $lineIds[] = $item->woo_line_item_id;
            }

            if (! empty($lineIds)) {
                $pedido->items()
                    ->whereNotNull('woo_line_item_id')
                    ->whereNotIn('woo_line_item_id', $lineIds)
                    ->delete();
            }

            $this->actualizarEstadoPedido($pedido->refresh()->load('items.producto'));

            return $pedido->refresh()->load('items.producto');
        });
    }

    public function reservarPedido(WooCommercePedido $pedido, Request $request): WooCommercePedido
    {
        return DB::transaction(function () use ($pedido, $request): WooCommercePedido {
            $pedido = WooCommercePedido::query()
                ->with('items.producto')
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if ($pedido->estado_erp === WooCommercePedido::ESTADO_RESERVADO) {
                return $pedido;
            }

            if ($pedido->estado_erp === WooCommercePedido::ESTADO_ATENDIDO) {
                throw ValidationException::withMessages([
                    'pedido' => 'El pedido ya fue atendido.',
                ]);
            }

            $bloqueos = $pedido->items
                ->filter(fn (WooCommercePedidoItem $item): bool => ! $item->producto_id || $item->estado_match !== WooCommercePedidoItem::MATCH_OK)
                ->map(fn (WooCommercePedidoItem $item): string => $item->nombre ?: $item->sku ?: 'Item sin nombre')
                ->values();

            if ($bloqueos->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Hay items sin producto ERP o sin stock suficiente: '.$bloqueos->join(', '),
                ]);
            }

            foreach ($pedido->items as $item) {
                $this->inventarioService->reservarStock(
                    productoId: (int) $item->producto_id,
                    cantidad: (float) $item->cantidad,
                    referenciaTipo: 'woocommerce_pedido',
                    referenciaId: $pedido->id,
                    origen: 'woocommerce',
                    idempotencyKey: "woocommerce-pedido:{$pedido->id}:reserva:item:{$item->id}",
                    createdBy: $request->user()?->id,
                    observacion: "Reserva por pedido WooCommerce #{$pedido->numero}",
                    ipOrigen: $request->ip(),
                    userAgent: $request->userAgent()
                );

                $item->forceFill([
                    'estado_reserva' => WooCommercePedidoItem::RESERVA_RESERVADO,
                ])->save();
            }

            $pedido->forceFill([
                'estado_erp' => WooCommercePedido::ESTADO_RESERVADO,
                'reservado_at' => now(),
            ])->save();

            return $pedido->refresh()->load('items.producto');
        });
    }

    private function upsertItem(WooCommercePedido $pedido, array $lineItem): WooCommercePedidoItem
    {
        $sku = trim((string) ($lineItem['sku'] ?? '')) ?: null;
        $quantity = (float) ($lineItem['quantity'] ?? 0);
        $total = (float) ($lineItem['total'] ?? 0);
        $lineItemId = (int) ($lineItem['id'] ?? 0) ?: null;
        $producto = $this->resolverProducto($sku, (int) ($lineItem['product_id'] ?? 0), (int) ($lineItem['variation_id'] ?? 0));
        $stockDisponible = $producto ? (float) $producto->stock_disponible : null;
        [$estadoMatch, $mensaje] = $this->evaluarItem($sku, $producto, $quantity, $stockDisponible);
        $existing = WooCommercePedidoItem::query()
            ->where('woocommerce_pedido_id', $pedido->id)
            ->where('woo_line_item_id', $lineItemId)
            ->first();
        $estadoReserva = $existing && in_array($existing->estado_reserva, [
            WooCommercePedidoItem::RESERVA_RESERVADO,
            WooCommercePedidoItem::RESERVA_ATENDIDO,
            WooCommercePedidoItem::RESERVA_CANCELADO,
        ], true)
            ? $existing->estado_reserva
            : WooCommercePedidoItem::RESERVA_PENDIENTE;

        return WooCommercePedidoItem::query()->updateOrCreate(
            [
                'woocommerce_pedido_id' => $pedido->id,
                'woo_line_item_id' => $lineItemId,
            ],
            [
                'woo_product_id' => (int) ($lineItem['product_id'] ?? 0) ?: null,
                'woo_variation_id' => (int) ($lineItem['variation_id'] ?? 0) ?: null,
                'producto_id' => $producto?->id,
                'sku' => $sku,
                'nombre' => $lineItem['name'] ?? null,
                'cantidad' => $quantity,
                'precio_unitario' => $quantity > 0 ? round($total / $quantity, 4) : 0,
                'total' => $total,
                'stock_disponible_snapshot' => $stockDisponible,
                'estado_match' => $estadoMatch,
                'estado_reserva' => $estadoReserva,
                'mensaje' => $mensaje,
            ]
        );
    }

    private function resolverProducto(?string $sku, int $wooProductId, int $wooVariationId): ?Producto
    {
        if ($sku) {
            $producto = Producto::query()
                ->where('sku', $sku)
                ->first();

            if ($producto) {
                return $producto;
            }

            $mapping = WooCommerceProducto::query()
                ->with('producto')
                ->where('woo_sku', $sku)
                ->first();

            if ($mapping?->producto) {
                return $mapping->producto;
            }
        }

        if ($wooVariationId > 0) {
            $mapping = WooCommerceProducto::query()
                ->with('producto')
                ->where('woo_variation_id', $wooVariationId)
                ->first();

            if ($mapping?->producto) {
                return $mapping->producto;
            }
        }

        if ($wooProductId > 0) {
            $mapping = WooCommerceProducto::query()
                ->with('producto')
                ->where('woo_product_id', $wooProductId)
                ->first();

            if ($mapping?->producto) {
                return $mapping->producto;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function evaluarItem(?string $sku, ?Producto $producto, float $cantidad, ?float $stockDisponible): array
    {
        if (! $sku) {
            return [WooCommercePedidoItem::MATCH_SIN_SKU, 'El item no tiene SKU en WooCommerce.'];
        }

        if (! $producto) {
            return [WooCommercePedidoItem::MATCH_NO_ENCONTRADO, 'No se encontro un producto ERP con este SKU.'];
        }

        if ($stockDisponible === null || $stockDisponible <= 0) {
            return [WooCommercePedidoItem::MATCH_SIN_STOCK, 'Producto encontrado, pero sin stock disponible.'];
        }

        if ($stockDisponible < $cantidad) {
            return [WooCommercePedidoItem::MATCH_STOCK_PARCIAL, "Stock parcial: disponible {$stockDisponible}, solicitado {$cantidad}."];
        }

        return [WooCommercePedidoItem::MATCH_OK, null];
    }

    private function actualizarEstadoPedido(WooCommercePedido $pedido): void
    {
        if ($pedido->estado_erp === WooCommercePedido::ESTADO_RESERVADO || $pedido->estado_erp === WooCommercePedido::ESTADO_ATENDIDO) {
            return;
        }

        $items = $pedido->items;

        $estado = match (true) {
            $items->isEmpty() => WooCommercePedido::ESTADO_REVISAR,
            $items->every(fn (WooCommercePedidoItem $item): bool => $item->estado_match === WooCommercePedidoItem::MATCH_OK) => WooCommercePedido::ESTADO_LISTO_RESERVA,
            $items->contains(fn (WooCommercePedidoItem $item): bool => $item->estado_match === WooCommercePedidoItem::MATCH_SIN_STOCK || $item->estado_match === WooCommercePedidoItem::MATCH_STOCK_PARCIAL) => WooCommercePedido::ESTADO_SIN_STOCK,
            default => WooCommercePedido::ESTADO_REVISAR,
        };

        $pedido->forceFill(['estado_erp' => $estado])->save();
    }

    private function parseWooDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->timezone(config('app.timezone', 'America/Lima'));
    }
}
