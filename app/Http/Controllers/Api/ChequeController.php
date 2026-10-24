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
use Illuminate\Validation\ValidationException;

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
        $query = Cheque::query()->with(['banco', 'proveedor', 'moneda', 'creadoPor', 'cuentasPorPagar.compra', 'cuentasPorPagar.comprobante'])->latest('id');
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
        if (count($aplicaciones) !== 1 || empty($aplicaciones[0]['cuenta_por_pagar_id'])) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'Selecciona una cuenta por pagar para registrar el cheque.',
            ]);
        }

        $aplicacion = $aplicaciones[0];
        $cuenta = CuentaPorPagar::query()
            ->with('proveedor')
            ->lockForUpdate()
            ->findOrFail($aplicacion['cuenta_por_pagar_id']);

        if (! $cuenta->proveedor?->tiene_credito) {
            throw ValidationException::withMessages([
                'cuenta_por_pagar_id' => 'Solo se puede registrar un cheque para compras de proveedores con crédito.',
            ]);
        }

        if (in_array($cuenta->estado, [CuentaPorPagar::ESTADO_PAGADA, CuentaPorPagar::ESTADO_ANULADA], true) || (float) $cuenta->saldo <= 0) {
            throw ValidationException::withMessages([
                'cuenta_por_pagar_id' => 'La cuenta por pagar seleccionada ya no tiene saldo disponible.',
            ]);
        }

        $chequeActivo = DB::table('cheque_aplicaciones')
            ->join('cheques', 'cheques.id', '=', 'cheque_aplicaciones.cheque_id')
            ->where('cheque_aplicaciones.cuenta_por_pagar_id', $cuenta->id)
            ->whereNotIn('cheques.estado', ['anulado', 'devuelto'])
            ->exists();
        if ($chequeActivo) {
            throw ValidationException::withMessages([
                'cuenta_por_pagar_id' => 'Esta cuenta ya tiene un cheque activo asociado.',
            ]);
        }

        if ($cuenta->compra_id && DB::table('cheque_aplicaciones')
            ->join('cheques', 'cheques.id', '=', 'cheque_aplicaciones.cheque_id')
            ->where('cheque_aplicaciones.compra_id', $cuenta->compra_id)
            ->whereNotIn('cheques.estado', ['anulado', 'devuelto'])
            ->exists()) {
            throw ValidationException::withMessages([
                'cuenta_por_pagar_id' => 'La compra ya tiene un cheque activo asociado.',
            ]);
        }

        $monto = (float) $data['monto'];
        if ($monto > (float) $cuenta->saldo + 0.00001) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del cheque no puede superar el saldo de la cuenta por pagar.',
            ]);
        }

        if (! empty($data['moneda_id']) && (int) $data['moneda_id'] !== (int) $cuenta->moneda_id) {
            throw ValidationException::withMessages([
                'moneda_id' => 'La moneda del cheque debe coincidir con la moneda de la cuenta.',
            ]);
        }

        $data['proveedor_id'] = $cuenta->proveedor_id;
        $data['moneda_id'] = $cuenta->moneda_id;
        unset($data['aplicaciones']);
        $data['creado_por'] = $request->user()?->id;
        $cheque = Cheque::create($data);
        DB::table('cheque_aplicaciones')->insert([
            'cheque_id' => $cheque->id,
            'compra_id' => $cuenta->compra_id,
            'cuenta_por_pagar_id' => $cuenta->id,
            'monto_aplicado' => $monto,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json($cheque->load(['banco', 'proveedor', 'moneda', 'cuentasPorPagar.compra', 'cuentasPorPagar.comprobante']), 201);
    }

    public function updateStatus(Request $request, Cheque $cheque): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(['registrado', 'entregado', 'cobrado', 'anulado', 'devuelto'])],
            'fecha_entrega' => ['nullable', 'date'],
            'fecha_cobro' => ['nullable', 'date'],
        ]);
        if (in_array($data['estado'], ['anulado', 'devuelto'], true) && $cheque->estado === 'cobrado') {
            foreach (DB::table('cheque_aplicaciones')->where('cheque_id', $cheque->id)->whereNotNull('cuenta_por_pagar_id')->get() as $application) {
                $pago = Pago::query()->where('idempotency_key', "cheque:{$cheque->id}:cxp:{$application->cuenta_por_pagar_id}")->first();
                if (! $pago || $pago->estado === Pago::ESTADO_ANULADO) continue;
                $pago->update(['estado' => Pago::ESTADO_ANULADO, 'observacion' => 'Pago anulado porque el cheque fue devuelto o anulado.']);
                $this->cuentaPorPagarService->recalcular(CuentaPorPagar::find($application->cuenta_por_pagar_id));
            }
        }

        if ($data['estado'] === 'cobrado' && $cheque->estado !== 'cobrado') {
            foreach (DB::table('cheque_aplicaciones')->where('cheque_id', $cheque->id)->whereNotNull('cuenta_por_pagar_id')->get() as $application) {
                $cuenta = CuentaPorPagar::find($application->cuenta_por_pagar_id);
                if (! $cuenta || (float) $cuenta->saldo <= 0) continue;
                $monto = min((float) ($application->monto_aplicado ?: $cheque->monto), (float) $cuenta->saldo);
                $pago = Pago::firstOrNew(
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
                $pago->estado = Pago::ESTADO_REGISTRADO;
                $pago->save();
                $this->cuentaPorPagarService->recalcular($cuenta);
            }
        }
        $cheque->update($data);
        return response()->json($cheque->fresh(['banco', 'proveedor', 'moneda', 'cuentasPorPagar.compra', 'cuentasPorPagar.comprobante']));
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
