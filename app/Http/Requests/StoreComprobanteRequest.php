<?php

namespace App\Http\Requests;

use App\Models\Comprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_operacion' => [
                'required',
                Rule::in([
                    Comprobante::TIPO_OPERACION_COMPRA,
                    Comprobante::TIPO_OPERACION_VENTA,
                    Comprobante::TIPO_OPERACION_GASTO,
                ]),
            ],
            'compra_id' => ['nullable', 'integer', 'exists:compras,id'],
            'oc_recibida_id' => ['nullable', 'integer', 'exists:oc_recibidas,id'],
            'cotizacion_id' => ['nullable', 'integer', 'exists:cotizaciones,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'categoria_gasto' => ['nullable', Rule::in(['combustible', 'servicios', 'viaticos', 'limpieza', 'oficina', 'otros'])],
            'centro_costo' => ['nullable', 'string', 'max:120'],
            'emisor_ruc' => ['nullable', 'string', 'max:20'],
            'emisor_nombre' => ['nullable', 'string', 'max:255'],
            'receptor_ruc' => ['nullable', 'string', 'max:20'],
            'receptor_nombre' => ['nullable', 'string', 'max:255'],
            'tipo_comprobante' => ['required', 'string', 'max:10'],
            'serie' => ['required', 'string', 'max:20'],
            'numero' => ['required', 'string', 'max:40'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'fecha_vencimiento_origen' => ['nullable', Rule::in(['xml', 'manual', 'contado'])],
            'condicion_pago' => ['nullable', Rule::in(['contado', 'cheque_30_dias', 'credito_15_dias', 'credito_30_dias', 'credito_45_dias', 'credito_60_dias', 'efectivo_dev', 'tarjeta_dev'])],
            'moneda_id' => ['nullable', 'integer', 'exists:monedas,id'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'igv' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'detraccion_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'detraccion_monto_original' => ['nullable', 'numeric', 'min:0'],
            'detraccion_codigo' => ['nullable', 'string', 'max:20'],
            'detraccion_cuenta' => ['nullable', 'string', 'max:80'],
            'xml_hash' => ['nullable', 'string', 'size:64'],
            'xml_path' => ['nullable', 'string', 'max:255'],
            'archivo_path' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.compra_item_id' => ['nullable', 'integer', 'exists:compra_items,id'],
            'items.*.cotizacion_item_id' => ['nullable', 'integer', 'exists:cotizacion_items,id'],
            'items.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'items.*.descripcion' => ['required', 'string', 'max:5000'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.valor_unitario' => ['nullable', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.igv' => ['nullable', 'numeric', 'min:0'],
            'items.*.total' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('tipo_operacion') === Comprobante::TIPO_OPERACION_COMPRA &&
                    ! $this->input('compra_id')
                ) {
                    $validator->errors()->add(
                        'compra_id',
                        'Un comprobante de compra debe estar vinculado a una compra.'
                    );
                }

                if ($this->input('tipo_operacion') === Comprobante::TIPO_OPERACION_GASTO && ! $this->input('categoria_gasto')) {
                    $validator->errors()->add('categoria_gasto', 'Selecciona una categoría para el gasto.');
                }

                if (
                    $this->input('tipo_operacion') === Comprobante::TIPO_OPERACION_VENTA &&
                    ! $this->input('oc_recibida_id') &&
                    ! $this->input('cotizacion_id') &&
                    ! $this->input('cliente_id')
                ) {
                    $validator->errors()->add(
                        'tipo_operacion',
                        'Un comprobante de venta debe estar vinculado a una OC recibida, cotizacion o cliente.'
                    );
                }
            },
        ];
    }
}
