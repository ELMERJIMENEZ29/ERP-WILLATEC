<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudSobrecompra;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SolicitudSobrecompraController extends Controller
{
    public function __construct(private readonly WorkflowNotificationService $notifications) {}

    public function index(Request $request)
    {
        $query = SolicitudSobrecompra::query()->with(['solicitante:id,nombres,apellidos,email', 'resolvedor:id,nombres,apellidos,email', 'compra:id,numero'])->latest();
        if ($request->filled('estado') && $request->estado !== 'todos') $query->where('estado', $request->estado);
        if (! $request->user()->hasAnyRole(['superadmin', 'admin'])) $query->where('solicitado_por', $request->user()->id);
        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'justificacion' => ['required', 'string', 'min:10', 'max:3000'],
            'payload_compra' => ['required', 'array'],
            'payload_compra.proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'payload_compra.modalidad' => ['required', Rule::in(['directa', 'oc_proveedor'])],
            'payload_compra.items' => ['required', 'array', 'min:1'],
            'payload_compra.items.*.requerimiento_compra_item_id' => ['nullable', 'integer', 'exists:requerimiento_compra_items,id'],
            'payload_compra.items.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ]);

        $solicitud = DB::transaction(function () use ($data, $request) {
            $row = SolicitudSobrecompra::create(['numero' => 'TMP-'.uuid(), 'estado' => 'pendiente', 'justificacion' => $data['justificacion'], 'payload_compra' => $data['payload_compra'], 'solicitado_por' => $request->user()->id]);
            $row->update(['numero' => sprintf('SC-%06d', $row->id)]);
            return $row;
        });
        $this->notifications->administracion('Solicitud de sobrecompra pendiente', "La solicitud {$solicitud->numero} requiere revisión administrativa.", '/compras/sobrecompras', 'sobrecompra_solicitada', ['solicitud_id' => $solicitud->id]);
        return response()->json($solicitud->load('solicitante'), 201);
    }

    public function resolve(Request $request, SolicitudSobrecompra $solicitud)
    {
        abort_unless($request->user()->hasAnyRole(['superadmin', 'admin']), 403);
        $data = $request->validate(['estado' => ['required', Rule::in(['aprobada', 'rechazada'])], 'comentario_resolucion' => ['required', 'string', 'min:3', 'max:3000']]);
        abort_if($solicitud->estado !== 'pendiente', 422, 'La solicitud ya fue resuelta.');
        $solicitud->update(['estado' => $data['estado'], 'comentario_resolucion' => $data['comentario_resolucion'], 'resuelto_por' => $request->user()->id, 'resuelto_en' => now()]);
        $this->notifications->usuario($solicitud->solicitado_por, 'Solicitud de sobrecompra '.($data['estado'] === 'aprobada' ? 'aprobada' : 'rechazada'), "La solicitud {$solicitud->numero} fue {$data['estado']}.", '/compras', 'sobrecompra_resuelta', ['solicitud_id' => $solicitud->id]);
        return response()->json($solicitud->fresh(['solicitante', 'resolvedor']));
    }
}
