<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarOcAtencionRequest;
use App\Http\Requests\StoreOcAtencionRequest;
use App\Models\OcAtencion;
use App\Models\OcRecibida;
use App\Services\OcAtencionService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\Request;

class OcAtencionController extends Controller
{
    public function __construct(private readonly WorkflowNotificationService $notifications) {}

    public function index(OcRecibida $ocRecibida)
    {
        return response()->json([
            'data' => $ocRecibida->atenciones()
                ->with([
                    'items.series',
                    'items.ocRecibidaItem',
                    'items.inventarioMovimiento',
                    'preparadoPor:id,nombres,apellidos,email',
                    'entregadoPor:id,nombres,apellidos,email',
                    'createdBy:id,nombres,apellidos,email',
                ])
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreOcAtencionRequest $request, OcRecibida $ocRecibida, OcAtencionService $service)
    {
        $atencion = $service->crear($ocRecibida, $request->validated(), $request);
        $this->notifications->compras('Atención preparada', "La atención {$atencion->numero} de la OC {$ocRecibida->numero} está lista para confirmar.", '/ordenes-compra', 'atencion_preparada', ['atencion_id' => $atencion->id, 'oc_recibida_id' => $ocRecibida->id]);

        return response()->json([
            'message' => 'Atencion logistica preparada.',
            'atencion' => $atencion,
        ], 201);
    }

    public function show(OcAtencion $ocAtencion)
    {
        return response()->json([
            'atencion' => $ocAtencion->load([
                'ocRecibida.cotizacion',
                'ocRecibida.cliente',
                'items.series',
                'items.ocRecibidaItem.cotizacionItem.producto',
                'items.inventarioMovimiento',
                'preparadoPor:id,nombres,apellidos,email',
                'entregadoPor:id,nombres,apellidos,email',
                'createdBy:id,nombres,apellidos,email',
            ]),
        ]);
    }

    public function confirmar(ConfirmarOcAtencionRequest $request, OcAtencion $ocAtencion, OcAtencionService $service)
    {
        $atencion = $service->confirmar($ocAtencion, $request->validated(), $request);
        $this->notifications->compras('Atención entregada', "La atención {$atencion->numero} fue entregada y generó salida de Kardex.", '/ordenes-compra', 'atencion_entregada', ['atencion_id' => $atencion->id, 'oc_recibida_id' => $atencion->oc_recibida_id]);

        return response()->json([
            'message' => 'Atencion confirmada y salida Kardex registrada.',
            'atencion' => $atencion,
        ]);
    }

    public function cancelar(Request $request, OcAtencion $ocAtencion, OcAtencionService $service)
    {
        $atencion = $service->cancelar($ocAtencion);
        $this->notifications->compras('Atención cancelada', "La atención {$atencion->numero} fue cancelada.", '/ordenes-compra', 'atencion_cancelada', ['atencion_id' => $atencion->id, 'oc_recibida_id' => $atencion->oc_recibida_id]);

        return response()->json([
            'message' => 'Atencion cancelada.',
            'atencion' => $atencion,
        ]);
    }
}
