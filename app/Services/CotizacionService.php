<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    private const DESTINO_DEFAULT = 'Lima Metropolitana';

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
        $cotizacion->load(['items.destinosEntrega', 'costosAdicionales', 'plantilla']);
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
        $entregaMultidestino = (bool) ($cotizacion->entrega_multidestino ?? false);
        $costosPorDestino = $entregaMultidestino
            ? $cotizacion->costosAdicionales
                ->groupBy(fn ($costo): string => $this->normalizeDestino($costo->destino_entrega))
                ->map(fn ($costos) => (float) $costos->sum('monto'))
            : collect(['__GLOBAL__' => (float) $cotizacion->costosAdicionales->sum('monto')]);
        $lineasDestino = $items->flatMap(function ($item) use ($entregaMultidestino) {
            if ($entregaMultidestino && $item->destinosEntrega->isNotEmpty()) {
                return $item->destinosEntrega->map(fn ($destino) => [
                    'item' => $item,
                    'destino_model' => $destino,
                    'destino' => $this->normalizeDestino($destino->destino_entrega),
                    'cantidad' => (int) ($destino->cantidad ?: 0),
                    'margen' => $destino->margen,
                ]);
            }

            return [[
                'item' => $item,
                'destino_model' => null,
                'destino' => $entregaMultidestino ? $this->normalizeDestino($item->destino_entrega) : '__GLOBAL__',
                'cantidad' => (int) ($item->cantidad ?: 0),
                'margen' => null,
            ]];
        });
        $itemsPorDestino = $entregaMultidestino
            ? $lineasDestino->groupBy('destino')
            : collect(['__GLOBAL__' => $lineasDestino]);
        $costoExtraUnitarioPorDestino = [];
        $itemIdsConCostosAdicionalesPorDestino = [];

        foreach ($itemsPorDestino as $destino => $lineas) {
            $totalCostosAdicionales = (float) ($costosPorDestino[$destino] ?? 0);

            if ($modoDistribucion === 'POR_CANTIDAD') {
                $lineasConCostosAdicionales = $lineas;
            } else {
                $lineasConCostosAdicionales = $lineas->filter(fn ($linea) => (bool) $linea['item']->aplica_costos_adicionales);
                if ($lineasConCostosAdicionales->isEmpty()) {
                    $lineasConCostosAdicionales = $lineas;
                }
            }

            $divisor = max(1, (int) $lineasConCostosAdicionales->sum('cantidad'));
            $costoExtraUnitarioPorDestino[$destino] = $totalCostosAdicionales / $divisor;
            $itemIdsConCostosAdicionalesPorDestino[$destino] = $lineasConCostosAdicionales
                ->map(fn ($linea) => $linea['item']->id)
                ->unique()
                ->values()
                ->all();
        }

        foreach ($items as $item) {
            $lineasItem = $lineasDestino->filter(fn ($linea) => $linea['item']->id === $item->id)->values();
            $costoBase = (float) $item->costo_base;
            $subtotalItem = 0;
            $costoTotalItem = 0;
            $gananciaTotalItem = 0;
            $precioVentaPonderado = 0;
            $costoUnitarioPonderado = 0;

            foreach ($lineasItem as $linea) {
                $destinoItem = $linea['destino'];
                $cantidadLinea = max(0, (int) $linea['cantidad']);
                $costoExtraItem = in_array($item->id, $itemIdsConCostosAdicionalesPorDestino[$destinoItem] ?? [], true)
                    ? ($costoExtraUnitarioPorDestino[$destinoItem] ?? 0)
                    : 0;
                $costoFinal = $costoBase + $costoExtraItem;
                $margen = $linea['margen'] !== null ? (float) $linea['margen'] : (float) ($item->margen ?? 0);
                $periodoMeses = max(0, (int) ($item->garantia_meses ?? 0));
                $precioVenta = round($margen < 100 ? $costoFinal / (1 - ($margen / 100)) : $costoFinal, 2);
                $costoUnitario = round($costoFinal, 2);
                $subtotal = round($cantidadLinea * $precioVenta * ($esAlquiler ? $periodoMeses : 1), 2);
                $costoTotal = round($cantidadLinea * $costoUnitario, 2);
                $ganancia = $cotizacion->plantilla->incluye_igv
                    ? round(($subtotal - $costoTotal) / 1.18, 2)
                    : round($subtotal - $costoTotal, 2);

                if ($linea['destino_model']) {
                    $linea['destino_model']->update([
                        'costo_unitario' => $costoUnitario,
                        'margen' => $margen,
                        'precio_venta' => $precioVenta,
                        'subtotal' => $subtotal,
                        'costo_total' => $costoTotal,
                        'ganancia' => $ganancia,
                    ]);
                }

                $subtotalItem += $subtotal;
                $costoTotalItem += $costoTotal;
                $gananciaTotalItem += $ganancia;
                $precioVentaPonderado += $precioVenta * $cantidadLinea;
                $costoUnitarioPonderado += $costoUnitario * $cantidadLinea;
            }

            $cantidadItem = max(1, (int) ($item->cantidad ?: $lineasItem->sum('cantidad') ?: 1));

            $item->update([
                'costo_unitario' => round($costoUnitarioPonderado / $cantidadItem, 2),
                'precio_venta' => round($precioVentaPonderado / $cantidadItem, 2),
                'subtotal' => round($subtotalItem, 2),
                'costo_total' => round($costoTotalItem, 2),
                'ganancia' => round($gananciaTotalItem, 2),
            ]);
        }

        $cotizacion->refresh()->load('items');
        $items = $cotizacion->items;
        $sumSubtotales = round($items->sum('subtotal'), 2);
        $gananciaTotal = round($items->sum('ganancia'), 2);

        if ($cotizacion->plantilla->incluye_igv) {
            $total = round($sumSubtotales, 2);
            $igv = round($total - ($total / 1.18), 2);
            $subtotal = round($total / 1.18, 2);
        } else {
            $subtotal = round($sumSubtotales, 2);
            $igv = round($subtotal * 0.18, 2);
            $total = round($subtotal + $igv, 2);
        }

        $cotizacion->update([
            'subtotal' => round($subtotal, 2),
            'igv' => round($igv, 2),
            'total' => round($total, 2),
            'ganancia' => round($gananciaTotal, 2),
            'total_gasto' => round($items->sum('costo_total'), 2),
        ]);

        $cotizacion->refresh()->load('items');
        $this->actualizarEstado($cotizacion);
    }

    private function normalizeDestino(?string $destino): string
    {
        $destino = trim((string) $destino);

        return $destino !== '' ? $destino : self::DESTINO_DEFAULT;
    }

    private function actualizarEstado(Cotizacion $cotizacion): void
    {
        $aprobados = $cotizacion->items->where('estado_cotizacion_item_id', 2)->count();
        $rechazados = $cotizacion->items->where('estado_cotizacion_item_id', 3)->count();
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
