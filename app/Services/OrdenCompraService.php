<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use Illuminate\Support\Facades\DB;

class OrdenCompraService
{
    public function generarDesdeCotizacion(
        Cotizacion $cotizacion,
        array $itemsSeleccionados,
        array $dataExtra = []
    ): OrdenCompra {

        return DB::transaction(function () use (
            $cotizacion,
            $itemsSeleccionados,
            $dataExtra) {

            // Validacion de existencia de OC
            if ($cotizacion->ordenCompra) {
                throw new \Exception('La cotización ya tiene una orden de compra asociada.');
            }

            // Cargar relaciones necesarias
            $cotizacion->load(['items.producto', 'cliente']);

            // Obtener items válidos
            $items = $cotizacion->items
                ->whereIn('id', array_keys($itemsSeleccionados))
                ->where('activo', true);

            if ($items->isEmpty()) {
                throw new \Exception('No se han seleccionado items válidos para generar la orden de compra.');
            }

            // Totales
            $subtotal = 0;
            $igv = 0;
            $total = 0;

            // CREACION DE ORDEN DE COMPRA
            $orden = OrdenCompra::create([
                'numero' => $dataExtra['numero'] ?? 'OC-'.str_pad(OrdenCompra::count() + 1, 6, '0', STR_PAD_LEFT),
                'fecha' => now(),

                'estado' => 'pendiente', // Estado inicial

                'observaciones' => $dataExtra['observaciones'] ?? null,
                'fecha_entrega' => $dataExtra['fecha_entrega'] ?? null,

                'moneda' => $cotizacion->moneda,

                'subtotal' => 0,
                'igv' => 0,
                'total' => 0,

                // Snapshot cliente
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'cliente_ruc' => $cotizacion->cliente_ruc,
                'cliente_contacto' => $cotizacion->cliente_contacto,
                'cliente_telefono' => $cotizacion->cliente_telefono,

                // Relaciones
                'cotizacion_id' => $cotizacion->id,
                'cliente_id' => $cotizacion->cliente_id,
                'user_id' => $cotizacion->user_id,
                'estado_orden_compra_id' => EstadoCotizacion::where('nombre', 'Aprobada')->first()->id, // Asumiendo que el estado "Aprobada" existe
            ]);

            foreach ($items as $item) {
                $cantidadAprobada = $itemsSeleccionados[$item->id] ?? 0;

                if ($cantidadAprobada <= 0) {
                    continue; // Saltar items sin cantidad aprobada
                }

                $costoTotal =
                    $cantidadAprobada * $item->costo_unitario;

                $subtotalItem =
                    $cantidadAprobada * $item->precio_venta;

                $ordenItem = OrdenCompraItem::create([
                    'orden_compra_id' => $orden->id,

                    'cotizacion_item_id' => $item->id,

                    'descripcion' => $item->descripcion,
                    'codigo' => $item->codigo,
                    'marca' => $item->marca,
                    'unidad_medida' => $item->unidad_medida,

                    // Cantidad original cotizada
                    'cantidad' => $item->cantidad,

                    // Cantidad aprobada
                    'cantidad_aprobada' => $cantidadAprobada,

                    // Costos
                    'costo_total' => round($costoTotal, 2),
                    'costo_unitario' => $item->costo_unitario,

                    'precio_venta_unitario' => $item->precio_venta,
                    'subtotal' => round($subtotalItem, 2),

                    'estado' => 'pendiente', // Estado inicial
                ]);

                if ($item->producto) {
                    app(InventarioService::class)->registrarSalida(
                        productoId: $item->producto->id,
                        cantidad: (float) $cantidadAprobada,
                        referenciaTipo: OrdenCompraItem::class,
                        referenciaId: $ordenItem->id,
                        origen: 'orden_compra',
                        idempotencyKey: "orden_compra_item_{$ordenItem->id}_salida",
                        createdBy: $cotizacion->user_id,
                        observacion: 'Salida generada desde orden de compra de cotizacion',
                        monedaId: $cotizacion->moneda_id
                    );
                }

                $subtotal += $subtotalItem;
            }

            // Calcular IGV y total
            if ($cotizacion->plantilla->incluye_igv) {
                $igv = 0;
                $total = $subtotal;
            } else {
                $igv = round($subtotal * 0.18, 2);
                $total = round($subtotal + $igv, 2);
            }

            // Actualizar totales en la orden de compra
            $orden->update([
                'subtotal' => round($subtotal, 2),
                'igv' => round($igv, 2),
                'total' => round($total, 2),
            ]);

            // Actualizar estado de la cotización
            $estado = EstadoCotizacion::where(
                'nombre',
                'oc_registrada')->first() ?? EstadoCotizacion::where('nombre', 'Aprobada')->first();

            if ($estado) {
                $cotizacion->update(['estado_cotizacion_id' => $estado->id]);
            }

            return $orden->load('items');
        });
    }
}
