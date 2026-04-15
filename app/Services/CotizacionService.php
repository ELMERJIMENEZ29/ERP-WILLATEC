<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;

class CotizacionService
{
    public function recalcular(Cotizacion $cotizacion, string $modoDistribucion = 'POR_ITEM'): void
    {
        $cotizacion->load(['items', 'costosAdicionales', 'plantilla']);

        $modoDistribucion = $cotizacion->modo_distribucion ?? $modoDistribucion;
        // Sumar Costos Adicionales
        $totalCostosAdicionales = $cotizacion->costosAdicionales->sum('monto');

        // CALCULAR BASE DE DISTRIBUCION
        $totalItems = $cotizacion->items->count();
        $totalCantidad = $cotizacion->items->sum('cantidad');

        //Divisor segun modo de distribucion
        if($modoDistribucion === 'POR_CANTIDAD'){
            $divisor = $totalCantidad > 0 ? $totalCantidad : 1; // Evitar división por cero
        } else {
            $divisor = $totalItems > 0 ? $totalItems : 1; // Evitar división por cero
        } 
        // Calcular costo extra por item
        $costoExtraUnitario= round($totalCostosAdicionales / $divisor,4);
        
        // Recalcular cada item
        foreach ($cotizacion->items as $item) {
            $costoBase = $item->costo_base; // Costo base del item

            // Si es por cantidad el costo extra se multiplica por la cantidad del item
            if($modoDistribucion === 'POR_CANTIDAD'){
                $costoFinal = $costoBase + ($costoExtraUnitario * $item->cantidad); // Si es por cantidad, el costo extra se multiplica por la cantidad del item
            } else {
                $costoFinal = $costoBase + $costoExtraUnitario; // Si es por item, el costo extra es fijo por item
            }

            $precioVenta = $costoFinal * (1 + $item->margen / 100);

            $subtotal = $item -> cantidad * $precioVenta;

            $item->update([
                'costo_unitario' => round($costoFinal,2), // Costo final del item
                'precio_venta' => round($precioVenta,2),
                'subtotal' => round($subtotal,2),
            ]);
        }

        // Recalcular totales de la cotización
        $subtotal = $cotizacion->items->sum('subtotal');

        if($cotizacion->plantilla->incluye_igv){
            //LOS PRECIOS YA INCLUYEN IGV, POR LO TANTO NO SE CALCULA EL IGV SE DEJA EN 0
            $igv = 0;
            $total = $subtotal;
        } else {
            // LOS PRECIOS NO INCLUYEN IGV, POR LO TANTO SE CALCULA EL IGV Y SE SUMA AL TOTAL
            $igv = round($subtotal * 0.18, 2); // IGV al 18%
            $total = round($subtotal + $igv, 2);
        }

        $cotizacion->update([
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
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
}
