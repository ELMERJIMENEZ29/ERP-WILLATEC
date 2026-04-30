<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    public function recalcular(Cotizacion $cotizacion, string $modoDistribucion = 'POR_ITEM'): void
    {
        
        $cotizacion->load(['items', 'costosAdicionales', 'plantilla']);

        $items = $cotizacion->items->where('activo', true);

        if($items->isEmpty()){
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
        // Sumar Costos Adicionales
        $totalCostosAdicionales = $cotizacion->costosAdicionales->sum('monto');

        // CALCULAR BASE DE DISTRIBUCION
        $totalItems = $items->count();
        $totalCantidad = $items->sum('cantidad');

        //Divisor segun modo de distribucion
        if($modoDistribucion === 'POR_CANTIDAD'){
            $divisor = $totalCantidad > 0 ? $totalCantidad : 1; // Evitar división por cero
        } else {
            $divisor = $totalItems > 0 ? $totalItems : 1; // Evitar división por cero
        } 
        // Calcular costo extra por item
        $costoExtraUnitario= round($totalCostosAdicionales / $divisor,4);
        
        // Recalcular cada item
        foreach ($items as $item) {
            $costoBase = $item->costo_base; // Costo base del item

            // Si es por cantidad el costo extra se multiplica por la cantidad del item
            if($modoDistribucion === 'POR_CANTIDAD'){
                $costoFinal = $costoBase + ($costoExtraUnitario * $item->cantidad); // Si es por cantidad, el costo extra se multiplica por la cantidad del item
            } else {
                $costoFinal = $costoBase + $costoExtraUnitario; // Si es por item, el costo extra es fijo por item
            }

            $precioVenta = $costoFinal / (1 - ($item->margen / 100));
            // $subtotal = $item -> cantidad * $precioVenta;

            // $costoTotal = $item->cantidad * $costoFinal;

            /// ==========================
            // CALCULO GANANCIA POR ITEM
            // ==========================

            // PVT (precio venta total del item)
            $pvt = $item->cantidad * $precioVenta;

            // PTC (precio total compra del item)
            $ptc = $item->cantidad * $costoFinal;

            // Diferencia base
            $diferencia = $pvt - $ptc;

            // Detectar plantilla
            $incluyeIgv = $cotizacion->plantilla->incluye_igv;
            $moneda = $cotizacion->moneda;

            // Tipo de cambio fijo (según tu negocio)
            $tipoCambioVenta = 3.5; // USD → PEN

            if ($incluyeIgv) {
                // 🟣 SOLES-ESTADO (con IGV)
                $ganancia = $diferencia / 1.18;

            } else {
                // 🟢 DOLARES / SOLES (sin IGV)

                if ($moneda === 'USD') {
                    // convertir a soles
                    $ganancia = $diferencia * $tipoCambioVenta;
                } else {
                    // ya está en soles
                    $ganancia = $diferencia;
                }
            }

            // Redondeo final
            $ganancia = round($ganancia, 2);

            $item->update([
                'costo_unitario' => round($costoFinal,2), // Costo final del item
                'precio_venta' => round($precioVenta,2),
                'subtotal' => round($pvt,2),
                'costo_total' => round($ptc,2),
                'ganancia' => round($ganancia,2),
            ]);
        }

        // Recalcular totales de la cotización
        $cotizacion->refresh()->load('items');
        $subtotal = round($items->sum('subtotal'),2);
        $gananciaTotal = round($items->sum('ganancia'),2);

        if($cotizacion->plantilla->incluye_igv){
            //LOS PRECIOS YA INCLUYEN IGV, POR LO TANTO NO SE CALCULA EL IGV SE DEJA EN 0
            $igv = 0;
            $total = $subtotal;
        } else {
            // LOS PRECIOS NO INCLUYEN IGV, POR LO TANTO SE CALCULA EL IGV Y SE SUMA AL TOTAL
            $igv = round($subtotal * 0.18, 2); // IGV al 18%
            $total = round($subtotal + $igv, 2);
        }

        $totalGasto = round($items->sum('costo_total'),2);

        $cotizacion->update([
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'ganancia' => round($gananciaTotal, 2),
            'total_gasto' => round($totalGasto, 2),
        ]);
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
            $estado = 'enviada';
        } 

        $estadoModel = EstadoCotizacion::where('nombre', $estado)->first();

        if ($estadoModel) {
            $cotizacion->update([
                'estado_cotizacion_id' => $estadoModel->id,
            ]);
        }
    }

    public function generarNumero(){
        return DB::transaction(function () {

        $anio = now()->year;

        $correlativo = DB::table('correlativos')
            ->where('tipo', 'cotizacion')
            ->where('anio', $anio)
            ->lockForUpdate()
            ->first();

        if (!$correlativo) {
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

        return str_pad($numero, 5, '0', STR_PAD_LEFT) . '-' . $anio;
    });
    }
}
