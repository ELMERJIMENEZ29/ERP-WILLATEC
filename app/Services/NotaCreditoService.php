<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\NotaCredito;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotaCreditoService
{
    public function __construct(private readonly CuentaPorPagarService $cxpService, private readonly CuentaPorCobrarService $cxcService) {}

    public function registrar(Comprobante $comprobante, array $data, ?int $userId = null): NotaCredito
    {
        return DB::transaction(function () use ($comprobante, $data, $userId) {
            $comprobante = Comprobante::query()->with(['items', 'notasCredito.items', 'cuentaPorPagar.pagos', 'cuentaPorCobrar.cobros', 'ocRecibida.items'])->lockForUpdate()->findOrFail($comprobante->id);
            $creditoPrevio = (float) $comprobante->notasCredito->sum('monto');
            $saldoAnulable = max((float) $comprobante->total - $creditoPrevio, 0);
            $monto = $data['tipo_anulacion'] === 'total' ? $saldoAnulable : round((float) $data['monto'], 2);
            if ($monto <= 0 || $monto > $saldoAnulable + 0.00001) {
                throw ValidationException::withMessages(['monto' => 'El monto de la nota de crédito supera el saldo anulable.']);
            }

            $itemsCredito = collect($data['items'] ?? []);
            if ($itemsCredito->isNotEmpty()) {
                $itemsFactura = $comprobante->items->keyBy('id');
                foreach ($itemsCredito as $itemCredito) {
                    $itemFactura = $itemsFactura->get((int) ($itemCredito['comprobante_item_id'] ?? 0));
                    if (! $itemFactura) {
                        throw ValidationException::withMessages(['items' => 'Todos los productos deben pertenecer al comprobante seleccionado.']);
                    }
                    $cantidadPrevia = (float) $comprobante->notasCredito->flatMap->items
                        ->where('comprobante_item_id', $itemFactura->id)->sum('cantidad');
                    if (isset($itemCredito['cantidad']) && (float) $itemCredito['cantidad'] > (float) $itemFactura->cantidad - $cantidadPrevia + 0.00001) {
                        throw ValidationException::withMessages(['items' => "La cantidad anulada de {$itemFactura->descripcion} supera el saldo disponible."]);
                    }
                }
                if (abs((float) $itemsCredito->sum('monto') - $monto) > 0.01) {
                    throw ValidationException::withMessages(['items' => 'La suma del detalle debe coincidir con el monto parcial de la nota.']);
                }
            }

            $requiereDevolucion = $comprobante->tipo_operacion === Comprobante::TIPO_OPERACION_VENTA
                && (bool) $comprobante->ocRecibida?->items?->contains(fn ($item) => (bool) $item->entregado);
            if ($requiereDevolucion && empty($data['devolucion_fisica_confirmada'])) {
                throw ValidationException::withMessages(['devolucion_fisica_confirmada' => 'Confirma si los productos entregados retornaron físicamente antes de registrar la nota.']);
            }

            $nota = NotaCredito::create([
                ...$data, 'comprobante_id' => $comprobante->id, 'monto' => $monto,
                'requiere_devolucion_fisica' => $requiereDevolucion, 'creado_por' => $userId,
            ]);
            foreach ($data['items'] ?? [] as $item) {
                $nota->items()->create($item);
            }

            $nuevoTotal = max(round((float) $comprobante->total - $creditoPrevio - $monto, 2), 0);
            if ($comprobante->cuentaPorPagar) {
                $pagado = (float) $comprobante->cuentaPorPagar->pagos->where('estado', 'registrado')->sum('monto_moneda_cuenta');
                if ($pagado <= 0) {
                    $pagado = (float) $comprobante->cuentaPorPagar->pagos->where('estado', 'registrado')->sum('monto');
                }
                if ($pagado > $nuevoTotal + 0.00001) {
                    throw ValidationException::withMessages(['monto' => 'La cuenta tiene pagos mayores al nuevo total. Registra primero el reembolso o ajuste correspondiente.']);
                }
                $comprobante->cuentaPorPagar->update(['total' => $nuevoTotal, 'estado' => $nuevoTotal <= 0 ? 'anulada' : $comprobante->cuentaPorPagar->estado]);
                if ($nuevoTotal > 0) {
                    $this->cxpService->recalcular($comprobante->cuentaPorPagar);
                }
            }
            if ($comprobante->cuentaPorCobrar) {
                $cobrado = (float) $comprobante->cuentaPorCobrar->cobros->where('estado', 'registrado')->sum('monto_moneda_cuenta');
                if ($cobrado <= 0) {
                    $cobrado = (float) $comprobante->cuentaPorCobrar->cobros->where('estado', 'registrado')->sum('monto');
                }
                if ($cobrado > $nuevoTotal + 0.00001) {
                    throw ValidationException::withMessages(['monto' => 'La cuenta tiene cobros mayores al nuevo total. Registra primero el reembolso o ajuste correspondiente.']);
                }
                $comprobante->cuentaPorCobrar->update(['total' => $nuevoTotal, 'estado' => $nuevoTotal <= 0 ? 'anulada' : $comprobante->cuentaPorCobrar->estado]);
                if ($nuevoTotal > 0) {
                    $this->cxcService->recalcular($comprobante->cuentaPorCobrar);
                }
            }
            $comprobante->update(['estado' => $nuevoTotal <= 0 ? Comprobante::ESTADO_ANULADO : 'parcialmente_anulado']);

            return $nota->fresh(['items.comprobanteItem', 'creadoPor']);
        });
    }
}
