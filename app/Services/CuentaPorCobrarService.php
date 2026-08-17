<?php

namespace App\Services;

use App\Models\Cobro;
use App\Models\Comprobante;
use App\Models\CuentaPorCobrar;
use App\Models\OcRecibida;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaPorCobrarService
{
    public function crearDesdeComprobante(
        Comprobante $comprobante,
        array $data = [],
        ?int $userId = null
    ): CuentaPorCobrar {
        return DB::transaction(function () use ($comprobante, $data, $userId) {
            $comprobante = Comprobante::query()
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if ($comprobante->tipo_operacion !== Comprobante::TIPO_OPERACION_VENTA) {
                throw ValidationException::withMessages([
                    'comprobante_id' => 'Solo un comprobante de venta puede generar cuenta por cobrar.',
                ]);
            }

            if ($comprobante->estado === Comprobante::ESTADO_ANULADO) {
                throw ValidationException::withMessages([
                    'comprobante_id' => 'No se puede generar cuenta por cobrar desde un comprobante anulado.',
                ]);
            }

            $existente = CuentaPorCobrar::query()
                ->where('comprobante_id', $comprobante->id)
                ->first();

            if ($existente) {
                return $existente->fresh($this->relaciones());
            }

            $cuenta = CuentaPorCobrar::create([
                'comprobante_id' => $comprobante->id,
                'oc_recibida_id' => $comprobante->oc_recibida_id,
                'cotizacion_id' => $comprobante->cotizacion_id,
                'cliente_id' => $comprobante->cliente_id,
                'moneda_id' => $comprobante->moneda_id,
                'fecha_emision' => $comprobante->fecha_emision,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? $comprobante->fecha_vencimiento,
                'total' => $comprobante->total,
                'monto_cobrado' => 0,
                'saldo' => $comprobante->total,
                'estado' => CuentaPorCobrar::ESTADO_PENDIENTE,
                'observacion' => $data['observacion'] ?? null,
                'creado_por' => $userId,
            ]);

            $this->recalcular($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function registrarCobro(
        CuentaPorCobrar $cuenta,
        array $data,
        ?int $userId = null
    ): CuentaPorCobrar {
        return DB::transaction(function () use ($cuenta, $data, $userId) {
            $cuenta = CuentaPorCobrar::query()
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            if ($cuenta->estado === CuentaPorCobrar::ESTADO_ANULADA) {
                throw ValidationException::withMessages([
                    'cuenta_por_cobrar_id' => 'No se puede cobrar una cuenta anulada.',
                ]);
            }

            if (! empty($data['idempotency_key'])) {
                $cobroExistente = Cobro::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();

                if ($cobroExistente) {
                    return $cuenta->fresh($this->relaciones());
                }
            }

            $saldoActual = $this->saldoActual($cuenta);
            $monto = round((float) $data['monto'], 2);

            if ($monto > $saldoActual + 0.00001) {
                throw ValidationException::withMessages([
                    'monto' => 'El cobro supera el saldo pendiente de la cuenta por cobrar.',
                ]);
            }

            Cobro::create([
                'cuenta_por_cobrar_id' => $cuenta->id,
                'fecha_cobro' => $data['fecha_cobro'] ?? now()->toDateString(),
                'monto' => $monto,
                'moneda_id' => $data['moneda_id'] ?? $cuenta->moneda_id,
                'metodo_cobro' => $data['metodo_cobro'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'estado' => Cobro::ESTADO_REGISTRADO,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'creado_por' => $userId,
            ]);

            $this->recalcular($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function anularCobro(Cobro $cobro): CuentaPorCobrar
    {
        return DB::transaction(function () use ($cobro) {
            $cobro = Cobro::query()
                ->lockForUpdate()
                ->findOrFail($cobro->id);

            $cuenta = CuentaPorCobrar::query()
                ->lockForUpdate()
                ->findOrFail($cobro->cuenta_por_cobrar_id);

            if ($cobro->estado !== Cobro::ESTADO_ANULADO) {
                $cobro->estado = Cobro::ESTADO_ANULADO;
                $cobro->save();
            }

            $this->recalcular($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function anularCuenta(CuentaPorCobrar $cuenta): CuentaPorCobrar
    {
        return DB::transaction(function () use ($cuenta) {
            $cuenta = CuentaPorCobrar::query()
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            $cuenta->estado = CuentaPorCobrar::ESTADO_ANULADA;
            $cuenta->save();
            $this->sincronizarOc($cuenta);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function recalcular(CuentaPorCobrar $cuenta): void
    {
        $cobrado = (float) Cobro::query()
            ->where('cuenta_por_cobrar_id', $cuenta->id)
            ->where('estado', Cobro::ESTADO_REGISTRADO)
            ->sum('monto');

        $total = (float) $cuenta->total;
        $saldo = max(round($total - $cobrado, 2), 0);

        $cuenta->monto_cobrado = round($cobrado, 2);
        $cuenta->saldo = $saldo;

        if ($cuenta->estado !== CuentaPorCobrar::ESTADO_ANULADA) {
            if ($saldo <= 0.00001) {
                $cuenta->estado = CuentaPorCobrar::ESTADO_COBRADA;
            } elseif ($cobrado > 0) {
                $cuenta->estado = CuentaPorCobrar::ESTADO_PARCIAL;
            } elseif (
                $cuenta->fecha_vencimiento &&
                $cuenta->fecha_vencimiento->isPast()
            ) {
                $cuenta->estado = CuentaPorCobrar::ESTADO_VENCIDA;
            } else {
                $cuenta->estado = CuentaPorCobrar::ESTADO_PENDIENTE;
            }
        }

        $cuenta->save();
        $this->sincronizarOc($cuenta);
    }

    public function relaciones(): array
    {
        return [
            'comprobante',
            'ocRecibida',
            'cotizacion',
            'cliente',
            'moneda',
            'creadoPor',
            'cobros.moneda',
            'cobros.creadoPor',
        ];
    }

    private function saldoActual(CuentaPorCobrar $cuenta): float
    {
        $this->recalcular($cuenta);

        return (float) $cuenta->fresh()->saldo;
    }

    private function sincronizarOc(CuentaPorCobrar $cuenta): void
    {
        if (! $cuenta->oc_recibida_id) {
            return;
        }

        $oc = OcRecibida::query()->find($cuenta->oc_recibida_id);

        if (! $oc) {
            return;
        }

        $estado = match ($cuenta->estado) {
            CuentaPorCobrar::ESTADO_COBRADA => 'cobrado',
            CuentaPorCobrar::ESTADO_PARCIAL => 'parcial',
            CuentaPorCobrar::ESTADO_VENCIDA => 'vencido',
            CuentaPorCobrar::ESTADO_ANULADA => OcRecibida::ESTADO_FINANCIERO_PENDIENTE,
            default => OcRecibida::ESTADO_FINANCIERO_PENDIENTE,
        };

        if ($oc->estado_financiero !== $estado) {
            $oc->estado_financiero = $estado;
            $oc->save();
        }
    }
}
