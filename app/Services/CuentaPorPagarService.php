<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\CuentaPorPagar;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaPorPagarService
{
    public function crearDesdeComprobante(
        Comprobante $comprobante,
        array $data = [],
        ?int $userId = null
    ): CuentaPorPagar {
        return DB::transaction(function () use ($comprobante, $data, $userId) {
            $comprobante = Comprobante::query()
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if ($comprobante->tipo_operacion !== Comprobante::TIPO_OPERACION_COMPRA) {
                throw ValidationException::withMessages([
                    'comprobante_id' => 'Solo un comprobante de compra puede generar cuenta por pagar.',
                ]);
            }

            if ($comprobante->estado === Comprobante::ESTADO_ANULADO) {
                throw ValidationException::withMessages([
                    'comprobante_id' => 'No se puede generar cuenta por pagar desde un comprobante anulado.',
                ]);
            }

            $existente = CuentaPorPagar::query()
                ->where('comprobante_id', $comprobante->id)
                ->first();

            if ($existente) {
                return $existente->fresh($this->relaciones());
            }

            $cuenta = CuentaPorPagar::create([
                'comprobante_id' => $comprobante->id,
                'compra_id' => $comprobante->compra_id,
                'proveedor_id' => $comprobante->proveedor_id,
                'moneda_id' => $comprobante->moneda_id,
                'fecha_emision' => $comprobante->fecha_emision,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? $comprobante->fecha_vencimiento,
                'total' => $comprobante->total,
                'monto_pagado' => 0,
                'saldo' => $comprobante->total,
                'estado' => CuentaPorPagar::ESTADO_PENDIENTE,
                'observacion' => $data['observacion'] ?? null,
                'creado_por' => $userId,
            ]);

            $this->recalcular($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function registrarPago(
        CuentaPorPagar $cuenta,
        array $data,
        ?int $userId = null
    ): CuentaPorPagar {
        return DB::transaction(function () use ($cuenta, $data, $userId) {
            $cuenta = CuentaPorPagar::query()
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            if ($cuenta->estado === CuentaPorPagar::ESTADO_ANULADA) {
                throw ValidationException::withMessages([
                    'cuenta_por_pagar_id' => 'No se puede pagar una cuenta anulada.',
                ]);
            }

            if (! empty($data['idempotency_key'])) {
                $pagoExistente = Pago::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();

                if ($pagoExistente) {
                    return $cuenta->fresh($this->relaciones());
                }
            }

            $saldoActual = $this->saldoActual($cuenta);
            $monto = round((float) $data['monto'], 2);

            if ($monto > $saldoActual + 0.00001) {
                throw ValidationException::withMessages([
                    'monto' => 'El pago supera el saldo pendiente de la cuenta por pagar.',
                ]);
            }

            Pago::create([
                'cuenta_por_pagar_id' => $cuenta->id,
                'fecha_pago' => $data['fecha_pago'] ?? now()->toDateString(),
                'monto' => $monto,
                'moneda_id' => $data['moneda_id'] ?? $cuenta->moneda_id,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'estado' => Pago::ESTADO_REGISTRADO,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'creado_por' => $userId,
            ]);

            $this->recalcular($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function anularPago(Pago $pago): CuentaPorPagar
    {
        return DB::transaction(function () use ($pago) {
            $pago = Pago::query()
                ->lockForUpdate()
                ->findOrFail($pago->id);

            $cuenta = CuentaPorPagar::query()
                ->lockForUpdate()
                ->findOrFail($pago->cuenta_por_pagar_id);

            if ($pago->estado !== Pago::ESTADO_ANULADO) {
                $pago->estado = Pago::ESTADO_ANULADO;
                $pago->save();
            }

            $this->recalcular($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function anularCuenta(CuentaPorPagar $cuenta): CuentaPorPagar
    {
        return DB::transaction(function () use ($cuenta) {
            $cuenta = CuentaPorPagar::query()
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            $cuenta->estado = CuentaPorPagar::ESTADO_ANULADA;
            $cuenta->save();

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function recalcular(CuentaPorPagar $cuenta): void
    {
        $pagado = (float) Pago::query()
            ->where('cuenta_por_pagar_id', $cuenta->id)
            ->where('estado', Pago::ESTADO_REGISTRADO)
            ->sum('monto');

        $total = (float) $cuenta->total;
        $saldo = max(round($total - $pagado, 2), 0);

        $cuenta->monto_pagado = round($pagado, 2);
        $cuenta->saldo = $saldo;

        if ($cuenta->estado !== CuentaPorPagar::ESTADO_ANULADA) {
            if ($saldo <= 0.00001) {
                $cuenta->estado = CuentaPorPagar::ESTADO_PAGADA;
            } elseif ($pagado > 0) {
                $cuenta->estado = CuentaPorPagar::ESTADO_PARCIAL;
            } elseif (
                $cuenta->fecha_vencimiento &&
                $cuenta->fecha_vencimiento->isPast()
            ) {
                $cuenta->estado = CuentaPorPagar::ESTADO_VENCIDA;
            } else {
                $cuenta->estado = CuentaPorPagar::ESTADO_PENDIENTE;
            }
        }

        $cuenta->save();
    }

    public function relaciones(): array
    {
        return [
            'comprobante',
            'compra',
            'proveedor',
            'moneda',
            'creadoPor',
            'pagos.moneda',
            'pagos.creadoPor',
        ];
    }

    private function saldoActual(CuentaPorPagar $cuenta): float
    {
        $this->recalcular($cuenta);

        return (float) $cuenta->fresh()->saldo;
    }
}
