<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCuentaPorPagarRequest;
use App\Http\Requests\StorePagoRequest;
use App\Models\Comprobante;
use App\Models\CuentaPorPagar;
use App\Models\Pago;
use App\Services\CuentaPorPagarService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaPorPagarController extends Controller
{
    public function __construct(
        private readonly CuentaPorPagarService $cuentaPorPagarService,
        private readonly WorkflowNotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        CuentaPorPagar::query()
            ->whereIn('estado', [CuentaPorPagar::ESTADO_PENDIENTE, CuentaPorPagar::ESTADO_VENCIDA])
            ->where('monto_pagado', 0)
            ->where('saldo', '>', 0)
            ->update(['estado' => CuentaPorPagar::ESTADO_PENDIENTE]);

        CuentaPorPagar::query()
            ->where('estado', CuentaPorPagar::ESTADO_PENDIENTE)
            ->where('saldo', '>', 0)
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => CuentaPorPagar::ESTADO_VENCIDA]);

        $query = CuentaPorPagar::query()
            ->with([
                'comprobante',
                'compra',
                'proveedor',
                'moneda',
                'creadoPor',
                'cheques.banco',
                'prorrogas.creadoPor',
                'cuentaOrigen.comprobante',
            ])
            ->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('tipo_operacion')) {
            $tipo = (string) $request->input('tipo_operacion');
            $query->where(function ($q) use ($tipo): void {
                $q->whereHas('comprobante', fn ($comprobante) => $comprobante->where('tipo_operacion', $tipo))
                    ->orWhereHas('cuentaOrigen.comprobante', fn ($comprobante) => $comprobante->where('tipo_operacion', $tipo));
            });
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->integer('proveedor_id'));
        }

        if ($request->filled('compra_id')) {
            $query->where('compra_id', $request->integer('compra_id'));
        }

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->input('buscar'));

            $query->where(function ($query) use ($buscar): void {
                $query
                    ->where('beneficiario', 'like', "%{$buscar}%")
                    ->orWhereHas('comprobante', function ($q) use ($buscar): void {
                        $q->where('serie', 'like', "%{$buscar}%")
                            ->orWhere('numero', 'like', "%{$buscar}%")
                            ->orWhere('emisor_nombre', 'like', "%{$buscar}%")
                            ->orWhere('emisor_ruc', 'like', "%{$buscar}%");
                    })
                    ->orWhereHas('proveedor', function ($q) use ($buscar): void {
                        $q->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('ruc', 'like', "%{$buscar}%");
                    });
            });
        }

        return response()->json(
            $query->paginate(
                min(max((int) $request->input('per_page', 20), 1), 100)
            )
        );
    }

    public function storeDesdeComprobante(
        StoreCuentaPorPagarRequest $request,
        Comprobante $comprobante
    ): JsonResponse {
        $existia = CuentaPorPagar::query()->where('comprobante_id', $comprobante->id)->exists();
        $cuenta = $this->cuentaPorPagarService->crearDesdeComprobante(
            $comprobante,
            $request->validated(),
            $request->user()?->id
        );
        if (! $existia) {
            $this->notifications->contabilidad('Cuenta por pagar pendiente', "Se generó una CxP por {$cuenta->total}, con vencimiento {$cuenta->fecha_vencimiento?->format('d/m/Y')}.", '/contabilidad/cuentas-por-pagar', 'cxp_creada', ['cuenta_por_pagar_id' => $cuenta->id]);
        }

        return response()->json($cuenta, 201);
    }

    public function show(CuentaPorPagar $cuentaPorPagar): JsonResponse
    {
        $this->cuentaPorPagarService->recalcular($cuentaPorPagar);

        return response()->json(
            $cuentaPorPagar->fresh(
                $this->cuentaPorPagarService->relaciones()
            )
        );
    }

    public function registrarPago(
        StorePagoRequest $request,
        CuentaPorPagar $cuentaPorPagar
    ): JsonResponse {
        $pagosAntes = $cuentaPorPagar->pagos()->count();
        $cuenta = $this->cuentaPorPagarService->registrarPago(
            $cuentaPorPagar,
            $request->validated(),
            $request->user()?->id
        );
        if ($cuenta->pagos()->count() > $pagosAntes) {
            $this->notifications->contabilidad('Pago registrado', "Se registró un pago de {$request->validated('monto')} en la CxP #{$cuenta->id}. Saldo: {$cuenta->saldo}.", '/contabilidad/cuentas-por-pagar', 'pago_registrado', ['cuenta_por_pagar_id' => $cuenta->id]);
            if (in_array($request->validated('metodo_pago') ?? null, ['efectivo_dev', 'tarjeta_dev'], true)) {
                $this->notifications->contabilidad('Devolución pendiente', "Se generó una devolución a {$request->validated('pagado_por')} por {$request->validated('monto_desembolsado')}.", '/contabilidad/cuentas-por-pagar', 'devolucion_creada', ['cuenta_origen_id' => $cuenta->id]);
            }
        }

        return response()->json($cuenta, 201);
    }

    public function anularPago(Pago $pago): JsonResponse
    {
        return response()->json(
            $this->cuentaPorPagarService->anularPago($pago)
        );
    }

    public function anular(CuentaPorPagar $cuentaPorPagar): JsonResponse
    {
        return response()->json(
            $this->cuentaPorPagarService->anularCuenta($cuentaPorPagar)
        );
    }

    public function actualizarSeguimiento(Request $request, CuentaPorPagar $cuentaPorPagar): JsonResponse
    {
        $data = $request->validate([
            'estado_operativo' => ['nullable', 'in:registrado,observado,programado_para_pago,reprogramacion_pago,vencido,cancelado'],
            'fecha_estimada_pago' => ['nullable', 'date'],
            'detraccion_monto_programado' => ['nullable', 'numeric', 'min:0'],
            'detraccion_fecha_programada' => ['nullable', 'date'],
            'detraccion_fecha_pago' => ['nullable', 'date'],
            'condicion_pago' => ['nullable', 'in:contado,cheque_30_dias,credito_15_dias,credito_30_dias,credito_45_dias,credito_60_dias,efectivo_dev,tarjeta_dev'],
        ]);
        $cuentaPorPagar->update($data);

        return response()->json($cuentaPorPagar->fresh($this->cuentaPorPagarService->relaciones()));
    }

    public function ampliarVencimiento(Request $request, CuentaPorPagar $cuentaPorPagar): JsonResponse
    {
        $data = $request->validate([
            'fecha_nueva' => ['required', 'date', 'after_or_equal:today'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        if (in_array($cuentaPorPagar->estado, [CuentaPorPagar::ESTADO_PAGADA, CuentaPorPagar::ESTADO_ANULADA], true)) {
            return response()->json(['message' => 'No se puede ampliar una cuenta pagada o anulada.'], 422);
        }

        DB::transaction(function () use ($cuentaPorPagar, $data, $request): void {
            $cuentaPorPagar->prorrogas()->create([
                'fecha_anterior' => $cuentaPorPagar->fecha_vencimiento,
                'fecha_nueva' => $data['fecha_nueva'],
                'motivo' => $data['motivo'],
                'creado_por' => $request->user()?->id,
            ]);
            $cuentaPorPagar->update([
                'fecha_vencimiento' => $data['fecha_nueva'],
                'fecha_estimada_pago' => $data['fecha_nueva'],
                'estado_operativo' => 'reprogramacion_pago',
            ]);
        });

        return response()->json($cuentaPorPagar->fresh($this->cuentaPorPagarService->relaciones()));
    }
}
