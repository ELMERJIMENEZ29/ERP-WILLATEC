<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\OcRecibida;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComprobanteService
{
    public function __construct(
        private readonly CuentaPorPagarService $cuentaPorPagarService,
        private readonly CuentaPorCobrarService $cuentaPorCobrarService
    ) {}

    public function crear(array $data, ?int $userId = null): Comprobante
    {
        return DB::transaction(function () use ($data, $userId) {
            if (($data['tipo_operacion'] ?? null) === Comprobante::TIPO_OPERACION_COMPRA) {
                $compra = Compra::query()->with('proveedor')->findOrFail($data['compra_id']);
                $data['proveedor_id'] = $compra->proveedor_id;
                $data['moneda_id'] ??= $compra->moneda_id;
            }

            if (($data['tipo_operacion'] ?? null) === Comprobante::TIPO_OPERACION_VENTA && ! empty($data['oc_recibida_id'])) {
                $oc = OcRecibida::query()->findOrFail($data['oc_recibida_id']);
                $data['cliente_id'] = $oc->cliente_id;
                $data['cotizacion_id'] ??= $oc->cotizacion_id;
            }

            $this->validarDuplicado($data);

            $items = $data['items'];
            unset($data['items']);

            $comprobante = Comprobante::create([
                ...$data,
                'subtotal' => $data['subtotal'] ?? 0,
                'igv' => $data['igv'] ?? 0,
                'estado' => Comprobante::ESTADO_REGISTRADO,
                'creado_por' => $userId,
            ]);

            foreach ($items as $item) {
                $cantidad = (float) $item['cantidad'];
                $valorUnitario = (float) ($item['valor_unitario'] ?? 0);
                $subtotal = $item['subtotal'] ?? round($cantidad * $valorUnitario, 2);
                $igv = $item['igv'] ?? 0;

                $comprobante->items()->create([
                    'compra_item_id' => $item['compra_item_id'] ?? null,
                    'cotizacion_item_id' => $item['cotizacion_item_id'] ?? null,
                    'producto_id' => $item['producto_id'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $cantidad,
                    'valor_unitario' => $valorUnitario,
                    'subtotal' => $subtotal,
                    'igv' => $igv,
                    'total' => $item['total'] ?? round((float) $subtotal + (float) $igv, 2),
                ]);
            }

            return $comprobante->fresh($this->relaciones());
        });
    }

    public function anular(Comprobante $comprobante): Comprobante
    {
        return DB::transaction(function () use ($comprobante) {
            $comprobante = Comprobante::query()
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if ($comprobante->estado === Comprobante::ESTADO_ANULADO) {
                return $comprobante->fresh($this->relaciones());
            }

            $comprobante->loadMissing(['cuentaPorPagar.pagos', 'cuentaPorCobrar.cobros']);

            if ($comprobante->cuentaPorPagar?->pagos()->where('estado', 'registrado')->exists()) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Anula primero los pagos registrados de la cuenta por pagar.',
                ]);
            }

            if ($comprobante->cuentaPorCobrar?->cobros()->where('estado', 'registrado')->exists()) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Anula primero los cobros registrados de la cuenta por cobrar.',
                ]);
            }

            if ($comprobante->cuentaPorPagar) {
                $this->cuentaPorPagarService->anularCuenta($comprobante->cuentaPorPagar);
            }

            if ($comprobante->cuentaPorCobrar) {
                $this->cuentaPorCobrarService->anularCuenta($comprobante->cuentaPorCobrar);
            }

            $comprobante->estado = Comprobante::ESTADO_ANULADO;
            $comprobante->save();

            return $comprobante->fresh($this->relaciones());
        });
    }

    public function relaciones(): array
    {
        return [
            'compra.proveedor',
            'ocRecibida',
            'cotizacion',
            'cliente',
            'proveedor',
            'moneda',
            'creadoPor',
            'items.compraItem',
            'items.cotizacionItem',
            'items.producto',
            'cuentaPorPagar:id,comprobante_id,estado',
            'cuentaPorCobrar:id,comprobante_id,estado',
        ];
    }

    private function validarDuplicado(array $data): void
    {
        if (! empty($data['xml_hash'])) {
            $existeHash = Comprobante::query()
                ->where('xml_hash', $data['xml_hash'])
                ->exists();

            if ($existeHash) {
                throw ValidationException::withMessages([
                    'xml_hash' => 'Ya existe un comprobante registrado con este XML.',
                ]);
            }
        }

        if (empty($data['emisor_ruc'])) {
            return;
        }

        $existe = Comprobante::query()
            ->where('emisor_ruc', $data['emisor_ruc'])
            ->where('tipo_comprobante', $data['tipo_comprobante'])
            ->where('serie', $data['serie'])
            ->where('numero', $data['numero'])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'numero' => 'Ya existe un comprobante con el mismo emisor, tipo, serie y numero.',
            ]);
        }
    }
}
