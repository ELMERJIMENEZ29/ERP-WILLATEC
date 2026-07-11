<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    private function esPlantillaAlquiler(Cotizacion $cotizacion): bool
    {
        $descriptor = strtoupper(
            iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                implode(' ', array_filter([
                    $cotizacion->plantilla?->nombre,
                    $cotizacion->plantilla?->formato_pdf,
                ]))
            ) ?: ''
        );

        return str_contains($descriptor, 'ALQUILER')
            || (str_contains($descriptor, 'GSD')
                && (str_contains($descriptor, 'ESTADO') || str_contains($descriptor, 'PRIVADO')));
    }

    public function recalcular(Cotizacion $cotizacion, string $modoDistribucion = 'POR_ITEM'): void
    {

        $cotizacion->load(['items', 'costosAdicionales', 'plantilla']);

        $items = $cotizacion->items;

        if ($items->isEmpty()) {
            $cotizacion->update([
                'subtotal' => 0,
                'igv' => 0,
                'total' => 0,
                'ganancia' => 0,
                'total_gasto' => 0,
            ]);

            return;
        }

        $modoDistribucion = $cotizacion->modo_distribucion ?? $modoDistribucion;
        $esAlquiler = $this->esPlantillaAlquiler($cotizacion);

        // Sumar Costos Adicionales
        $totalCostosAdicionales = $cotizacion->costosAdicionales->sum('monto');

        // CALCULAR BASE DE DISTRIBUCION
        $totalCantidad = $items->sum('cantidad');
        $itemsConCostosAdicionales = $items;

        // =====================================
        // DEFINIR DIVISOR SEGUN MODO
        // =====================================

        // POR_ITEM = distribuir por líneas/items
        // POR_CANTIDAD = distribuir por unidades totales
        if ($modoDistribucion === 'POR_CANTIDAD') {
            $divisor = $totalCantidad > 0 ? $totalCantidad : 1; // Evitar división por cero
        } else {
            $itemsConCostosAdicionales = $items->where('aplica_costos_adicionales', true);

            if ($itemsConCostosAdicionales->isEmpty()) {
                $itemsConCostosAdicionales = $items;
            }

            $totalCantidadSeleccionada = $itemsConCostosAdicionales->sum('cantidad');
            $divisor = $totalCantidadSeleccionada > 0 ? $totalCantidadSeleccionada : 1; // Evitar división por cero
        }
        // Calcular costo extra unitario, igual que el helper del frontend.
        $costoExtraUnitario = $totalCostosAdicionales / $divisor;
        $itemIdsConCostosAdicionales = $itemsConCostosAdicionales->pluck('id')->all();

        // Recalcular cada item
        foreach ($items as $item) {
            $costoBase = $item->costo_base; // Costo base del item

            $costoExtraItem = in_array($item->id, $itemIdsConCostosAdicionales, true)
                ? $costoExtraUnitario
                : 0;

            $costoFinal = $costoBase + $costoExtraItem;

            $margen = (float) ($item->margen ?? 0);
            $periodoMeses = max(0, (int) ($item->garantia_meses ?? 0));
            $precioVentaBase = $margen < 100
                ? $costoFinal / (1 - ($margen / 100))
                : $costoFinal;
            $precioVenta = $precioVentaBase;

            // Redondear precio unitario antes de multiplicar
            $precioVentaRedondeado = round($precioVenta, 2);
            $costoFinalRedondeado = round($costoFinal, 2);
            // / ==========================
            // CALCULO GANANCIA POR ITEM
            // ==========================

            // PVT (precio venta total del item)
            $pvt = round(
                $item->cantidad * $precioVentaRedondeado * ($esAlquiler ? $periodoMeses : 1),
                2
            );

            // PTC (precio total compra del item)
            $ptc = round($item->cantidad * $costoFinalRedondeado, 2);

            // Diferencia base
            $diferencia = $pvt - $ptc;

            // Detectar plantilla
            $incluyeIgv = $cotizacion->plantilla->incluye_igv;

            if ($incluyeIgv) {
                // 🟣 SOLES-ESTADO (con IGV)
                $ganancia = $diferencia / 1.18;
            } else {
                // 🟢 DOLARES / SOLES (sin IGV)
                $ganancia = $diferencia;
            }

            // Redondeo final
            $ganancia = round($ganancia, 2);

            $item->update([
                'costo_unitario' => $costoFinalRedondeado, // Costo final del item
                'precio_venta' => $precioVentaRedondeado, // Precio de venta del item
                'subtotal' => $pvt,
                'costo_total' => $ptc,
                'ganancia' => round($ganancia, 2),
            ]);
        }

        // Recalcular totales de la cotización
        $cotizacion->refresh()->load('items');
        $items = $cotizacion->items;
        $sumSubtotales = round($items->sum('subtotal'), 2);
        $gananciaTotal = round($items->sum('ganancia'), 2);

        if ($cotizacion->plantilla->incluye_igv) {
            // Los subtotales de los items ya incluyen IGV.
            $total = round($sumSubtotales, 2);
            $igv = round($total - ($total / 1.18), 2);
            $subtotal = round($total / 1.18, 2);
        } else {
            // LOS PRECIOS NO INCLUYEN IGV, POR LO TANTO SE CALCULA EL IGV Y SE SUMA AL TOTAL
            $subtotal = round($sumSubtotales, 2);
            $igv = round($subtotal * 0.18, 2); // IGV al 18%
            $total = round($subtotal + $igv, 2);
        }

        $totalGasto = round($items->sum('costo_total'), 2);

        $cotizacion->update([
            'subtotal' => round($subtotal, 2),
            'igv' => round($igv, 2),
            'total' => round($total, 2),
            'ganancia' => round($gananciaTotal, 2),
            'total_gasto' => round($totalGasto, 2),
        ]);

        $cotizacion->refresh()->load('items');
        // Llamar al estado desde recalcular
        $this->actualizarEstado($cotizacion);
    }

    private function actualizarEstado(Cotizacion $cotizacion): void
    {
        // Actualizar Estado de la cotización
        $aprobados = $cotizacion->items->where('estado_cotizacion_item_id', 2)->count(); // Aprobado
        $rechazados = $cotizacion->items->where('estado_cotizacion_item_id', 3)->count(); // Rechazado

        $totalItems = $cotizacion->items()->count();

        if ($aprobados === $totalItems && $totalItems > 0) {
            $estado = 'aprobada';
        } elseif ($rechazados === $totalItems && $totalItems > 0) {
            $estado = 'rechazada';
        } elseif ($aprobados > 0 || $rechazados > 0) {
            $estado = 'parcialmente_aprobada';
        } else {
            return;
        }

        $estadoModel = EstadoCotizacion::where('nombre', $estado)->first();

        if ($estadoModel) {
            $cotizacion->update([
                'estado_cotizacion_id' => $estadoModel->id,
            ]);
        }
    }

    public function generarNumero()
    {
        return DB::transaction(function () {

            $anio = now()->year;

            $correlativo = DB::table('correlativos')
                ->where('tipo', 'cotizacion')
                ->where('anio', $anio)
                ->lockForUpdate()
                ->first();

            if (! $correlativo) {
                DB::table('correlativos')->insert([
                    'tipo' => 'cotizacion',
                    'numero_actual' => 1,
                    'anio' => $anio,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $numero = 1;
            } else {
                $numero = $correlativo->numero_actual + 1;

                DB::table('correlativos')
                    ->where('id', $correlativo->id)
                    ->update([
                        'numero_actual' => $numero,
                        'updated_at' => now(),
                    ]);
            }

            return str_pad($numero, 6, '0', STR_PAD_LEFT).'-'.$anio;
        });
    }
}
