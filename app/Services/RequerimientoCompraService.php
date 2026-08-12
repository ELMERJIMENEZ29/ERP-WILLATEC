<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\OcRecibida;
use App\Models\OcRecibidaItem;
use App\Models\RequerimientoCompra;
use App\Models\RequerimientoCompraItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequerimientoCompraService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function crearManual(array $data, Request $request): RequerimientoCompra
    {
        return DB::transaction(function () use ($data, $request): RequerimientoCompra {
            $requerimiento = RequerimientoCompra::create([
                'numero' => $this->generarNumero(),
                'origen_tipo' => $data['origen_tipo'],
                'oc_recibida_id' => $data['oc_recibida_id'] ?? null,
                'estado' => RequerimientoCompra::ESTADO_PENDIENTE,
                'prioridad' => $data['prioridad'] ?? RequerimientoCompra::PRIORIDAD_NORMAL,
                'solicitado_por' => $request->user()?->id,
                'asignado_a' => $data['asignado_a'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                RequerimientoCompraItem::create([
                    'requerimiento_compra_id' => $requerimiento->id,
                    'oc_recibida_item_id' => $itemData['oc_recibida_item_id'] ?? null,
                    'cotizacion_item_id' => $itemData['cotizacion_item_id'] ?? null,
                    'producto_id' => $itemData['producto_id'] ?? null,
                    'producto_externo_id' => $itemData['producto_externo_id'] ?? null,
                    'descripcion' => $itemData['descripcion'],
                    'cantidad_requerida' => (float) $itemData['cantidad_requerida'],
                    'cantidad_comprada' => 0,
                    'cantidad_recibida' => 0,
                    'estado' => RequerimientoCompra::ESTADO_PENDIENTE,
                ]);
            }

            return $requerimiento->refresh()->load($this->relations());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function generarDesdeOc(OcRecibida $ocRecibida, array $data, Request $request): RequerimientoCompra
    {
        return DB::transaction(function () use ($ocRecibida, $data, $request): RequerimientoCompra {
            $ocRecibida = OcRecibida::query()
                ->lockForUpdate()
                ->with(['items.cotizacionItem.producto', 'items.cotizacionItem.productoExterno'])
                ->findOrFail($ocRecibida->id);

            if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
                throw ValidationException::withMessages([
                    'oc_recibida' => 'No se puede generar requerimiento de compra para una OC cancelada.',
                ]);
            }

            $faltantes = $this->calcularFaltantesOc($ocRecibida);

            if ($faltantes->isEmpty()) {
                $activo = $this->requerimientoActivoDeOc($ocRecibida);

                if ($activo) {
                    return $activo->load($this->relations());
                }

                throw ValidationException::withMessages([
                    'items' => 'No hay faltantes reales para generar requerimiento de compra.',
                ]);
            }

            $requerimiento = RequerimientoCompra::create([
                'numero' => $this->generarNumero(),
                'origen_tipo' => RequerimientoCompra::ORIGEN_OC_CLIENTE,
                'oc_recibida_id' => $ocRecibida->id,
                'estado' => RequerimientoCompra::ESTADO_PENDIENTE,
                'prioridad' => $data['prioridad'] ?? RequerimientoCompra::PRIORIDAD_NORMAL,
                'solicitado_por' => $request->user()?->id,
                'asignado_a' => $data['asignado_a'] ?? null,
                'observacion' => $data['observacion'] ?? "Faltantes generados desde OC {$ocRecibida->numero}",
            ]);

            foreach ($faltantes as $faltante) {
                RequerimientoCompraItem::create([
                    'requerimiento_compra_id' => $requerimiento->id,
                    'oc_recibida_item_id' => $faltante['oc_recibida_item_id'],
                    'cotizacion_item_id' => $faltante['cotizacion_item_id'],
                    'producto_id' => $faltante['producto_id'],
                    'producto_externo_id' => $faltante['producto_externo_id'],
                    'descripcion' => $faltante['descripcion'],
                    'cantidad_requerida' => $faltante['cantidad_faltante'],
                    'cantidad_comprada' => 0,
                    'cantidad_recibida' => 0,
                    'estado' => RequerimientoCompra::ESTADO_PENDIENTE,
                ]);
            }

            return $requerimiento->refresh()->load($this->relations());
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function calcularFaltantesOc(OcRecibida $ocRecibida): Collection
    {
        $ocRecibida->loadMissing(['items.cotizacionItem.producto', 'items.cotizacionItem.productoExterno']);

        return $ocRecibida->items
            ->where('seleccionado', true)
            ->map(function (OcRecibidaItem $item): ?array {
                $solicitado = (float) $item->cantidad_recibida;
                $cubierto = $this->cantidadCubiertaPorStock($item);
                $activoPrevio = $this->cantidadEnRequerimientosActivos($item);
                $faltante = max(0, $solicitado - $cubierto - $activoPrevio);

                if ($faltante <= 0) {
                    return null;
                }

                $cotizacionItem = $item->cotizacionItem;

                return [
                    'oc_recibida_item_id' => $item->id,
                    'cotizacion_item_id' => $item->cotizacion_item_id,
                    'producto_id' => $cotizacionItem?->producto_id,
                    'producto_externo_id' => $cotizacionItem?->producto_externo_id,
                    'descripcion' => $item->descripcion ?: $cotizacionItem?->descripcion ?: 'Item sin descripcion',
                    'cantidad_solicitada' => $solicitado,
                    'cantidad_cubierta' => $cubierto,
                    'cantidad_en_requerimientos_activos' => $activoPrevio,
                    'cantidad_faltante' => $faltante,
                ];
            })
            ->filter()
            ->values();
    }

    private function cantidadCubiertaPorStock(OcRecibidaItem $item): float
    {
        $reservaKey = "oc-recibida:{$item->oc_recibida_id}:reserva:cotizacion-item:{$item->cotizacion_item_id}";
        $salidaKey = "oc-recibida:{$item->oc_recibida_id}:salida:cotizacion-item:{$item->cotizacion_item_id}";

        $reservadoLegacy = (float) InventarioMovimiento::query()
            ->where('idempotency_key', $reservaKey)
            ->sum('cantidad');

        $salidaLegacy = (float) InventarioMovimiento::query()
            ->where('idempotency_key', $salidaKey)
            ->sum('cantidad');

        $salidaAtenciones = (float) InventarioMovimiento::query()
            ->where('referencia_tipo', 'oc_atencion')
            ->where('tipo_movimiento', InventarioMovimiento::TIPO_SALIDA)
            ->where('producto_id', $item->cotizacionItem?->producto_id)
            ->whereHas('ocAtencionItem', fn ($query) => $query->where('oc_recibida_item_id', $item->id))
            ->sum('cantidad');

        return min((float) $item->cantidad_recibida, max($reservadoLegacy, $salidaLegacy + $salidaAtenciones));
    }

    private function cantidadEnRequerimientosActivos(OcRecibidaItem $item): float
    {
        return (float) RequerimientoCompraItem::query()
            ->where('oc_recibida_item_id', $item->id)
            ->whereHas('requerimiento', fn ($query) => $query->whereIn('estado', RequerimientoCompra::estadosActivos()))
            ->get()
            ->sum(fn (RequerimientoCompraItem $item): float => max(
                0,
                (float) $item->cantidad_requerida - (float) $item->cantidad_comprada
            ));
    }

    private function requerimientoActivoDeOc(OcRecibida $ocRecibida): ?RequerimientoCompra
    {
        return RequerimientoCompra::query()
            ->where('oc_recibida_id', $ocRecibida->id)
            ->whereIn('estado', RequerimientoCompra::estadosActivos())
            ->latest()
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'items.ocRecibidaItem',
            'items.cotizacionItem',
            'items.producto',
            'items.productoExterno',
            'ocRecibida',
            'solicitadoPor:id,nombres,apellidos,email',
            'asignadoA:id,nombres,apellidos,email',
        ];
    }

    private function generarNumero(): string
    {
        $maxId = (int) (RequerimientoCompra::query()->max('id') ?? 0);

        do {
            $maxId++;
            $numero = 'REQ-'.str_pad((string) $maxId, 6, '0', STR_PAD_LEFT);
        } while (RequerimientoCompra::query()->where('numero', $numero)->exists());

        return $numero;
    }
}
