<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCobroRequest;
use App\Http\Requests\StoreCuentaPorCobrarRequest;
use App\Models\Cobro;
use App\Models\Comprobante;
use App\Models\CuentaPorCobrar;
use App\Services\CuentaPorCobrarService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuentaPorCobrarController extends Controller
{
    public function __construct(
        private readonly CuentaPorCobrarService $cuentaPorCobrarService,
        private readonly WorkflowNotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        CuentaPorCobrar::query()
            ->whereIn('estado', [CuentaPorCobrar::ESTADO_PENDIENTE, CuentaPorCobrar::ESTADO_VENCIDA])
            ->where('monto_cobrado', 0)
            ->where('saldo', '>', 0)
            ->update(['estado' => CuentaPorCobrar::ESTADO_PENDIENTE]);

        CuentaPorCobrar::query()
            ->where('estado', CuentaPorCobrar::ESTADO_PENDIENTE)
            ->where('saldo', '>', 0)
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => CuentaPorCobrar::ESTADO_VENCIDA]);

        $query = CuentaPorCobrar::query()
            ->with([
                'comprobante',
                'ocRecibida',
                'cotizacion',
                'cliente',
                'moneda',
                'creadoPor',
            ])
            ->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('oc_recibida_id')) {
            $query->where('oc_recibida_id', $request->integer('oc_recibida_id'));
        }

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->input('buscar'));

            $query->where(function ($query) use ($buscar): void {
                $query
                    ->whereHas('comprobante', function ($q) use ($buscar): void {
                        $q->where('serie', 'like', "%{$buscar}%")
                            ->orWhere('numero', 'like', "%{$buscar}%")
                            ->orWhere('receptor_nombre', 'like', "%{$buscar}%")
                            ->orWhere('receptor_ruc', 'like', "%{$buscar}%");
                    })
                    ->orWhereHas('cliente', function ($q) use ($buscar): void {
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
        StoreCuentaPorCobrarRequest $request,
        Comprobante $comprobante
    ): JsonResponse {
        $existia = CuentaPorCobrar::query()->where('comprobante_id', $comprobante->id)->exists();
        $cuenta = $this->cuentaPorCobrarService->crearDesdeComprobante(
            $comprobante,
            $request->validated(),
            $request->user()?->id
        );
        if (! $existia) {
            $this->notifications->contabilidad('Cuenta por cobrar pendiente', "Se generó una CxC por {$cuenta->total}, con vencimiento {$cuenta->fecha_vencimiento?->format('d/m/Y')}.", '/contabilidad/cuentas-por-cobrar', 'cxc_creada', ['cuenta_por_cobrar_id' => $cuenta->id]);
        }

        return response()->json($cuenta, 201);
    }

    public function show(CuentaPorCobrar $cuentaPorCobrar): JsonResponse
    {
        $this->cuentaPorCobrarService->recalcular($cuentaPorCobrar);

        return response()->json(
            $cuentaPorCobrar->fresh(
                $this->cuentaPorCobrarService->relaciones()
            )
        );
    }

    public function registrarCobro(
        StoreCobroRequest $request,
        CuentaPorCobrar $cuentaPorCobrar
    ): JsonResponse {
        $cobrosAntes = $cuentaPorCobrar->cobros()->count();
        $cuenta = $this->cuentaPorCobrarService->registrarCobro(
            $cuentaPorCobrar,
            $request->validated(),
            $request->user()?->id
        );
        if ($cuenta->cobros()->count() > $cobrosAntes) {
            $this->notifications->contabilidad('Cobro registrado', "Se registró un cobro de {$request->validated('monto')} en la CxC #{$cuenta->id}. Saldo: {$cuenta->saldo}.", '/contabilidad/cuentas-por-cobrar', 'cobro_registrado', ['cuenta_por_cobrar_id' => $cuenta->id]);
        }

        return response()->json($cuenta, 201);
    }

    public function anularCobro(Cobro $cobro): JsonResponse
    {
        return response()->json(
            $this->cuentaPorCobrarService->anularCobro($cobro)
        );
    }

    public function anular(CuentaPorCobrar $cuentaPorCobrar): JsonResponse
    {
        return response()->json(
            $this->cuentaPorCobrarService->anularCuenta($cuentaPorCobrar)
        );
    }
}
