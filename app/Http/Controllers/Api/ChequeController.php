<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\Cheque;
use App\Models\CuentaPorPagar;
use App\Models\Pago;
use App\Services\CuentaPorPagarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChequeController extends Controller
{
    public function __construct(private readonly CuentaPorPagarService $cuentaPorPagarService) {}
    public function bancos(): JsonResponse
    {
        return response()->json(Banco::query()->where('activo', true)->orderBy('nombre')->get());
    }

    public function storeBanco(Request $request): JsonResponse
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:120', 'unique:bancos,nombre']]);
        return response()->json(Banco::create(['nombre' => trim($data['nombre']), 'activo' => true]), 201);
    }

    public function destroyBanco(Banco $banco): JsonResponse
    {
        if ($banco->cheques()->exists()) {
            $banco->update(['activo' => false]);
            return response()->json(['message' => 'El banco tiene cheques y se desactivo para conservar el historial.']);
        }
        $banco->delete();
        return response()->json(['message' => 'Banco eliminado.']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Cheque::query()->with(['banco', 'proveedor', 'moneda', 'creadoPor'])->latest('id');
        if ($request->filled('estado')) $query->where('estado', $request->string('estado'));
        if ($request->filled('buscar')) {
            $term = trim((string) $request->input('buscar'));
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$term}%")
                ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$term}%"))
                ->orWhereHas('banco', fn ($b) => $b->where('nombre', 'like', "%{$term}%")));
        }
        return response()->json($query->paginate(min(max((int) $request->input('per_page', 20), 1), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'banco_id' => ['required', 'exists:bancos,id'],
            'numero' => ['required', 'string', 'max:60'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'moneda_id' => ['nullable', 'exists:monedas,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_emision' => ['required', 'date'],
            'fecha_cheque' => ['required', 'date'],
            'fecha_entrega' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],
            'aplicaciones' => ['nullable', 'array'],
            'aplicaciones.*.compra_id' => ['nullable', 'exists:compras,id'],
            'aplicaciones.*.cuenta_por_pagar_id' => ['nullable', 'exists:cuentas_por_pagar,id'],
            'aplicaciones.*.monto_aplicado' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        if (Cheque::where('banco_id', $data['banco_id'])->where('numero', $data['numero'])->exists()) {
            return response()->json(['message' => 'El numero de cheque ya existe para este banco.'], 422);
        }
        $aplicaciones = $data['aplicaciones'] ?? [];
        unset($data['aplicaciones']);
        $data['creado_por'] = $request->user()?->id;
        $cheque = Cheque::create($data);
        foreach ($aplicaciones as $aplicacion) {
            if (empty($aplicacion['compra_id']) && empty($aplicacion['cuenta_por_pagar_id'])) continue;
            DB::table('cheque_aplicaciones')->insert([
                'cheque_id' => $cheque->id,
                'compra_id' => $aplicacion['compra_id'] ?? null,
                'cuenta_por_pagar_id' => $aplicacion['cuenta_por_pagar_id'] ?? null,
                'monto_aplicado' => $aplicacion['monto_aplicado'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json($cheque->load(['banco', 'proveedor', 'moneda']), 201);
    }

    public function updateStatus(Request $request, Cheque $cheque): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(['registrado', 'entregado', 'cobrado', 'anulado', 'devuelto'])],
            'fecha_entrega' => ['nullable', 'date'],
            'fecha_cobro' => ['nullable', 'date'],
        ]);
        if ($data['estado'] === 'cobrado' && $cheque->estado !== 'cobrado') {
            foreach (DB::table('cheque_aplicaciones')->where('cheque_id', $cheque->id)->whereNotNull('cuenta_por_pagar_id')->get() as $application) {
                $cuenta = CuentaPorPagar::find($application->cuenta_por_pagar_id);
                if (! $cuenta || (float) $cuenta->saldo <= 0) continue;
                $monto = min((float) ($application->monto_aplicado ?: $cheque->monto), (float) $cuenta->saldo);
                $pago = Pago::firstOrCreate(
                    ['idempotency_key' => "cheque:{$cheque->id}:cxp:{$cuenta->id}"],
                    [
                        'cuenta_por_pagar_id' => $cuenta->id,
                        'fecha_pago' => $data['fecha_cobro'] ?? now()->toDateString(),
                        'monto' => $monto,
                        'moneda_id' => $cuenta->moneda_id,
                        'metodo_pago' => 'cheque',
                        'referencia' => $cheque->numero,
                        'estado' => Pago::ESTADO_REGISTRADO,
                        'observacion' => 'Pago generado al marcar cheque como cobrado.',
                        'creado_por' => $request->user()?->id,
                    ]
                );
                if ($pago->wasRecentlyCreated) $this->cuentaPorPagarService->recalcular($cuenta);
            }
        }
        $cheque->update($data);
        return response()->json($cheque->fresh(['banco', 'proveedor', 'moneda']));
    }

    public function evidencia(Request $request, Cheque $cheque): JsonResponse
    {
        $request->validate(['evidencia' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);
        if ($cheque->evidencia_path) Storage::disk('public')->delete($cheque->evidencia_path);
        $file = $request->file('evidencia');
        $path = $file->store('cheques', 'public');
        $cheque->update(['evidencia_path' => $path, 'evidencia_nombre' => $file->getClientOriginalName()]);
        return response()->json($cheque->fresh(['banco', 'proveedor', 'moneda']));
    }

    public function destroy(Cheque $cheque): JsonResponse
    {
        if ($cheque->estado === 'cobrado') return response()->json(['message' => 'No se puede eliminar un cheque cobrado.'], 422);
        $cheque->delete();
        return response()->json(['message' => 'Cheque eliminado.']);
    }
}
