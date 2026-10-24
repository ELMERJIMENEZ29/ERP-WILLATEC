<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\CuentaPorCobrar;
use App\Models\CuentaPorPagar;
use App\Models\Cheque;
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
                'action_url' => '/ordenes-compra',
                'severidad' => 'warning',
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
                'action_url' => '/compras/requerimientos',
                'severidad' => 'warning',
                'total' => RequerimientoCompra::query()
                    ->whereIn('estado', ['pendiente', 'en_gestion', 'parcialmente_comprado'])
                    ->count(),
            ],
            [
                'codigo' => 'compra_confirmada_sin_recepcion',
                'titulo' => 'Compras confirmadas sin recepcion',
                'action_url' => '/compras',
                'severidad' => 'warning',
                'total' => Compra::query()
                    ->where('estado', Compra::ESTADO_CONFIRMADA)
                    ->whereDoesntHave('recepciones', fn ($query) => $query->where('estado', 'confirmada'))
                    ->count(),
            ],
            [
                'codigo' => 'compra_parcialmente_recibida',
                'titulo' => 'Compras parcialmente recibidas',
                'action_url' => '/compras',
                'severidad' => 'warning',
                'total' => Compra::query()
                    ->where('estado', Compra::ESTADO_PARCIALMENTE_RECIBIDA)
                    ->count(),
            ],
            [
                'codigo' => 'recepcion_sin_factura_proveedor',
                'titulo' => 'Recepciones sin factura proveedor',
                'action_url' => '/contabilidad/comprobantes',
                'severidad' => 'urgent',
                'total' => Compra::query()
                    ->whereHas('recepciones', fn ($query) => $query->where('estado', 'confirmada'))
                    ->whereDoesntHave('comprobantes', fn ($query) => $query->where('estado', Comprobante::ESTADO_REGISTRADO))
                    ->count(),
            ],
            [
                'codigo' => 'compra_sin_comprobante',
                'titulo' => 'Compras recibidas sin comprobante',
                'action_url' => '/contabilidad/comprobantes',
                'severidad' => 'urgent',
                'total' => Compra::query()
                    ->whereIn('estado', [Compra::ESTADO_PARCIALMENTE_RECIBIDA, Compra::ESTADO_RECIBIDA])
                    ->whereDoesntHave('recepciones', fn ($query) => $query->where('estado', 'confirmada'))
                    ->whereDoesntHave('comprobantes', fn ($query) => $query->where('estado', Comprobante::ESTADO_REGISTRADO))
                    ->count(),
            ],
            [
                'codigo' => 'cxp_vencida',
                'titulo' => 'CxP vencidas',
                'action_url' => '/contabilidad/cuentas-por-pagar?estado=vencida',
                'severidad' => 'urgent',
                'total' => CuentaPorPagar::query()
                    ->where('estado', '!=', CuentaPorPagar::ESTADO_ANULADA)
                    ->where('saldo', '>', 0)
                    ->whereDate('fecha_vencimiento', '<', $today)
                    ->count(),
            ],
            [
                'codigo' => 'cxp_por_vencer',
                'titulo' => 'CxP próximas a vencer',
                'action_url' => '/contabilidad/cuentas-por-pagar?estado=pendiente',
                'severidad' => 'warning',
                'total' => CuentaPorPagar::query()
                    ->whereNotIn('estado', [CuentaPorPagar::ESTADO_ANULADA, CuentaPorPagar::ESTADO_PAGADA])
                    ->where('saldo', '>', 0)
                    ->whereDate('fecha_vencimiento', '>=', $today)
                    ->whereDate('fecha_vencimiento', '<=', now()->addDays(3)->toDateString())
                    ->count(),
            ],
            [
                'codigo' => 'cheques_por_cobrar',
                'titulo' => 'Cheques próximos a cobrar',
                'action_url' => '/contabilidad/cheques',
                'severidad' => 'warning',
                'total' => Cheque::query()
                    ->whereIn('estado', ['registrado', 'entregado'])
                    ->whereDate('fecha_cheque', '<=', now()->addDays(3)->toDateString())
                    ->count(),
            ],
            [
                'codigo' => 'cheques_sin_entregar',
                'titulo' => 'Cheques pendientes de entrega',
                'action_url' => '/contabilidad/cheques',
                'severidad' => 'urgent',
                'total' => Cheque::query()
                    ->where('estado', 'registrado')
                    ->whereDate('fecha_cheque', '<=', $today)
                    ->count(),
            ],
            [
                'codigo' => 'cxc_vencida',
                'titulo' => 'CxC vencidas',
                'action_url' => '/contabilidad/cuentas-por-cobrar?estado=vencida',
                'severidad' => 'urgent',
                'total' => CuentaPorCobrar::query()
                    ->where('estado', '!=', CuentaPorCobrar::ESTADO_ANULADA)
                    ->where('saldo', '>', 0)
                    ->whereDate('fecha_vencimiento', '<', $today)
                    ->count(),
            ],
            [
                'codigo' => 'documento_cliente_pendiente',
                'titulo' => 'Documentos cliente pendientes',
                'action_url' => '/ordenes-compra',
                'severidad' => 'info',
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
