<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\CuentaPorPagar;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaPorPagarService
{
    public function crearDesdeCompra(Compra $compra, ?int $userId = null): CuentaPorPagar
    {
        return DB::transaction(function () use ($compra, $userId) {
            $compra = Compra::query()->with(['proveedor', 'moneda'])->lockForUpdate()->findOrFail($compra->id);
            $existente = CuentaPorPagar::query()->where('compra_id', $compra->id)->first();

            if ($existente) {
                return $existente->fresh($this->relaciones());
            }

            $proveedor = $compra->proveedor;
            $fechaEmision = $compra->fecha_compra?->toDateString() ?? now()->toDateString();
            $fechaVencimiento = $proveedor?->tiene_credito
                ? now()->parse($fechaEmision)->addDays((int) $proveedor->dias_credito)->toDateString()
                : $fechaEmision;
            $total = round((float) ($compra->total_estimado ?? 0), 2);

            $cuenta = CuentaPorPagar::create([
                'comprobante_id' => null,
                'compra_id' => $compra->id,
                'proveedor_id' => $compra->proveedor_id,
                'moneda_id' => $compra->moneda_id,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'total' => $total,
                'monto_pagado' => 0,
                'saldo' => $total,
                'estado' => CuentaPorPagar::ESTADO_PENDIENTE,
                'observacion' => "Compra {$compra->numero} pendiente de comprobante.",
                'creado_por' => $userId,
            ]);

            return $cuenta->fresh($this->relaciones());
        });
    }

    public function vincularComprobanteProvisional(Comprobante $comprobante): void
    {
        if (! $comprobante->compra_id) {
            return;
        }

        $cuenta = CuentaPorPagar::query()
            ->where('compra_id', $comprobante->compra_id)
            ->whereNull('comprobante_id')
            ->lockForUpdate()
            ->first();

        if (! $cuenta) {
            return;
        }

        $cuenta->update([
            'comprobante_id' => $comprobante->id,
            'proveedor_id' => $comprobante->proveedor_id,
            'moneda_id' => $comprobante->moneda_id,
            'fecha_emision' => $comprobante->fecha_emision,
            'fecha_vencimiento' => $comprobante->fecha_vencimiento ?: $cuenta->fecha_vencimiento,
            'condicion_pago' => $comprobante->condicion_pago ?: $cuenta->condicion_pago,
            'total' => $comprobante->total,
            'detraccion_monto_original' => $comprobante->detraccion_monto_original ?? 0,
            'detraccion_monto_programado' => $comprobante->detraccion_monto_original ?? 0,
            'observacion' => 'Actualizada con el comprobante registrado.',
        ]);

        $this->recalcular($cuenta);
    }

    public function crearDesdeComprobante(
        Comprobante $comprobante,
        array $data = [],
        ?int $userId = null
    ): CuentaPorPagar {
        return DB::transaction(function () use ($comprobante, $data, $userId) {
            $comprobante = Comprobante::query()
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if (! in_array($comprobante->tipo_operacion, [Comprobante::TIPO_OPERACION_COMPRA, Comprobante::TIPO_OPERACION_GASTO], true)) {
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

            $proveedor = $comprobante->proveedor ?? $comprobante->compra?->proveedor;
            $fechaVencimiento = $data['fecha_vencimiento'] ?? $comprobante->fecha_vencimiento;

            if (! $fechaVencimiento) {
                $fechaBase = $comprobante->fecha_emision?->copy() ?? now();
                $fechaVencimiento = $proveedor?->tiene_credito
                    ? $fechaBase->addDays((int) $proveedor->dias_credito)->toDateString()
                    : $fechaBase->toDateString();
            }

            $cuenta = CuentaPorPagar::create([
                'comprobante_id' => $comprobante->id,
                'compra_id' => $comprobante->compra_id,
                'proveedor_id' => $comprobante->proveedor_id,
                'moneda_id' => $comprobante->moneda_id,
                'fecha_emision' => $comprobante->fecha_emision,
                'fecha_vencimiento' => $fechaVencimiento,
                'condicion_pago' => $data['condicion_pago'] ?? $comprobante->condicion_pago,
                'total' => $comprobante->total,
                'monto_pagado' => 0,
                'saldo' => $comprobante->total,
                'detraccion_monto_original' => $comprobante->detraccion_monto_original ?? 0,
                'detraccion_monto_programado' => $comprobante->detraccion_monto_original ?? 0,
                'estado_operativo' => 'registrado',
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
                    if (
                        (int) $pagoExistente->cuenta_por_pagar_id !== (int) $cuenta->id ||
                        abs((float) $pagoExistente->monto - round((float) $data['monto'], 2)) > 0.00001
                    ) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'La clave de idempotencia ya fue usada para otra operación.',
                        ]);
                    }

                    return $cuenta->fresh($this->relaciones());
                }
            }

            $saldoActual = $this->saldoActual($cuenta);
            $monto = round((float) $data['monto'], 2);
            $monedaPago = (int) ($data['moneda_id'] ?? $cuenta->moneda_id);
            $esOtraMoneda = $monedaPago !== (int) $cuenta->moneda_id;
            if ($esOtraMoneda && empty($data['tipo_cambio'])) {
                throw ValidationException::withMessages(['tipo_cambio' => 'Indica el tipo de cambio para un pago en moneda diferente.']);
            }
            $montoCuenta = round($monto * ($esOtraMoneda ? (float) $data['tipo_cambio'] : 1), 2);
            $esDevolucion = in_array($data['metodo_pago'] ?? null, ['efectivo_dev', 'tarjeta_dev'], true);
            if ($esDevolucion && $cuenta->tipo_cuenta === 'devolucion') {
                throw ValidationException::withMessages(['metodo_pago' => 'Una devolución no puede pagarse generando otra devolución.']);
            }
            $montoDesembolsado = $esDevolucion ? round((float) ($data['monto_desembolsado'] ?? $monto), 2) : null;
            if ($esDevolucion && $montoDesembolsado < $monto) {
                throw ValidationException::withMessages(['monto_desembolsado' => 'El monto desembolsado no puede ser menor al pago aplicado.']);
            }

            if ($montoCuenta > $saldoActual + 0.00001) {
                throw ValidationException::withMessages([
                    'monto' => 'El pago supera el saldo pendiente de la cuenta por pagar.',
                ]);
            }

            $pago = Pago::create([
                'cuenta_por_pagar_id' => $cuenta->id,
                'fecha_pago' => $data['fecha_pago'] ?? now()->toDateString(),
                'monto' => $monto,
                'moneda_id' => $data['moneda_id'] ?? $cuenta->moneda_id,
                'tipo_cambio' => $esOtraMoneda ? $data['tipo_cambio'] : 1,
                'monto_moneda_cuenta' => $montoCuenta,
                'monto_desembolsado' => $montoDesembolsado,
                'numero_cuotas' => $data['numero_cuotas'] ?? null,
                'pagado_por' => $data['pagado_por'] ?? null,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'estado' => Pago::ESTADO_REGISTRADO,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'creado_por' => $userId,
            ]);

            if ($esDevolucion) {
                CuentaPorPagar::create([
                    'tipo_cuenta' => 'devolucion',
                    'cuenta_origen_id' => $cuenta->id,
                    'pago_origen_id' => $pago->id,
                    'compra_id' => $cuenta->compra_id,
                    'moneda_id' => $monedaPago,
                    'beneficiario' => $data['pagado_por'],
                    'fecha_emision' => $data['fecha_pago'] ?? now()->toDateString(),
                    'fecha_vencimiento' => $data['fecha_pago'] ?? now()->toDateString(),
                    'fecha_estimada_pago' => $data['fecha_pago'] ?? now()->toDateString(),
                    'condicion_pago' => $data['metodo_pago'],
                    'total' => $montoDesembolsado,
                    'monto_pagado' => 0,
                    'saldo' => $montoDesembolsado,
                    'comision_origen' => round($montoDesembolsado - $monto, 2),
                    'numero_cuotas' => $data['numero_cuotas'] ?? null,
                    'estado' => CuentaPorPagar::ESTADO_PENDIENTE,
                    'estado_operativo' => 'registrado',
                    'observacion' => "Devolución a {$data['pagado_por']} por pago de la cuenta #{$cuenta->id}.",
                    'creado_por' => $userId,
                ]);
            }

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
                $devolucion = CuentaPorPagar::query()->where('pago_origen_id', $pago->id)->first();
                if ($devolucion?->pagos()->where('estado', Pago::ESTADO_REGISTRADO)->exists()) {
                    throw ValidationException::withMessages(['pago' => 'No se puede anular el pago porque su devolución ya tiene pagos registrados.']);
                }
                $devolucion?->update(['estado' => CuentaPorPagar::ESTADO_ANULADA, 'saldo' => 0]);
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

            if ($cuenta->pagos()->where('estado', Pago::ESTADO_REGISTRADO)->exists()) {
                throw ValidationException::withMessages([
                    'cuenta_por_pagar_id' => 'Anula primero los pagos registrados antes de anular la cuenta.',
                ]);
            }

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
            ->get()->sum(fn (Pago $pago) => (float) ($pago->monto_moneda_cuenta ?? $pago->monto));

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
            'cheques.banco',
            'prorrogas.creadoPor',
            'cuentaOrigen.comprobante',
            'devoluciones.pagoOrigen',
        ];
    }

    private function saldoActual(CuentaPorPagar $cuenta): float
    {
        $this->recalcular($cuenta);

        return (float) $cuenta->fresh()->saldo;
    }
}
