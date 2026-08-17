<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCuentaPorPagarRequest;
use App\Http\Requests\StorePagoRequest;
use App\Models\Comprobante;
use App\Models\CuentaPorPagar;
use App\Models\Pago;
use App\Services\CuentaPorPagarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuentaPorPagarController extends Controller
{
    public function __construct(
        private readonly CuentaPorPagarService $cuentaPorPagarService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CuentaPorPagar::query()
            ->with([
                'comprobante',
                'compra',
                'proveedor',
                'moneda',
                'creadoPor',
            ])
            ->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
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
                    ->whereHas('comprobante', function ($q) use ($buscar): void {
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
        return response()->json(
            $this->cuentaPorPagarService->crearDesdeComprobante(
                $comprobante,
                $request->validated(),
                $request->user()?->id
            ),
            201
        );
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
        return response()->json(
            $this->cuentaPorPagarService->registrarPago(
                $cuentaPorPagar,
                $request->validated(),
                $request->user()?->id
            ),
            201
        );
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
}
