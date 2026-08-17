<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\CuentaPorCobrar;
use App\Models\CuentaPorPagar;
use App\Models\OcRecibida;
use App\Models\RequerimientoCompra;
use Illuminate\Http\JsonResponse;

class AlertaOperativaController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        $alertas = [
            [
                'codigo' => 'oc_faltante_sin_requerimiento',
                'titulo' => 'OC con faltante sin requerimiento',
                'total' => OcRecibida::query()
                    ->whereDoesntHave('requerimientosCompra', fn ($query) => $query->where('estado', '!=', 'cancelado'))
                    ->whereIn('estado_logistico', [
                        OcRecibida::ESTADO_LOGISTICO_PENDIENTE,
                        OcRecibida::ESTADO_LOGISTICO_PREPARANDO,
                        OcRecibida::ESTADO_LOGISTICO_PARCIAL,
                    ])
                    ->count(),
            ],
            [
                'codigo' => 'requerimiento_pendiente',
                'titulo' => 'Requerimientos pendientes',
                'total' => RequerimientoCompra::query()
                    ->whereIn('estado', ['pendiente', 'en_gestion', 'parcialmente_comprado'])
                    ->count(),
            ],
            [
                'codigo' => 'compra_confirmada_sin_recepcion',
                'titulo' => 'Compras confirmadas sin recepcion',
                'total' => Compra::query()
                    ->where('estado', Compra::ESTADO_CONFIRMADA)
                    ->whereDoesntHave('recepciones', fn ($query) => $query->where('estado', 'confirmada'))
                    ->count(),
            ],
            [
                'codigo' => 'compra_parcialmente_recibida',
                'titulo' => 'Compras parcialmente recibidas',
                'total' => Compra::query()
                    ->where('estado', Compra::ESTADO_PARCIALMENTE_RECIBIDA)
                    ->count(),
            ],
            [
                'codigo' => 'recepcion_sin_factura_proveedor',
                'titulo' => 'Recepciones sin factura proveedor',
                'total' => Compra::query()
                    ->whereHas('recepciones', fn ($query) => $query->where('estado', 'confirmada'))
                    ->whereDoesntHave('comprobantes', fn ($query) => $query->where('estado', Comprobante::ESTADO_REGISTRADO))
                    ->count(),
            ],
            [
                'codigo' => 'comprobante_observado',
                'titulo' => 'Comprobantes observados',
                'total' => Comprobante::query()
                    ->where('tipo_operacion', 'observado')
                    ->count(),
            ],
            [
                'codigo' => 'cxp_vencida',
                'titulo' => 'CxP vencidas',
                'total' => CuentaPorPagar::query()
                    ->where('estado', '!=', CuentaPorPagar::ESTADO_ANULADA)
                    ->where('saldo', '>', 0)
                    ->whereDate('fecha_vencimiento', '<', $today)
                    ->count(),
            ],
            [
                'codigo' => 'cxc_vencida',
                'titulo' => 'CxC vencidas',
                'total' => CuentaPorCobrar::query()
                    ->where('estado', '!=', CuentaPorCobrar::ESTADO_ANULADA)
                    ->where('saldo', '>', 0)
                    ->whereDate('fecha_vencimiento', '<', $today)
                    ->count(),
            ],
            [
                'codigo' => 'documento_cliente_pendiente',
                'titulo' => 'Documentos cliente pendientes',
                'total' => OcRecibida::query()
                    ->where('estado_documental', '!=', OcRecibida::ESTADO_DOCUMENTAL_COMPLETO)
                    ->count(),
            ],
        ];

        return response()->json([
            'data' => $alertas,
            'total_alertas' => collect($alertas)->sum('total'),
        ]);
    }
}
