<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id' => [
                'required',
                'integer',
                'exists:proveedores,id',
            ],

            'modalidad' => [
                'required',
                Rule::in([
                    'directa',
                    'oc_proveedor',
                ]),
            ],

            'oc_emitida_id' => [
                'nullable',
                'integer',
                'exists:oc_emitidas,id',
            ],

            'fecha_compra' => [
                'nullable',
                'date',
            ],

            'moneda_id' => [
                'nullable',
                'integer',
                'exists:monedas,id',
            ],

            'observacion' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.requerimiento_compra_item_id' => [
                'nullable',
                'integer',
                'exists:requerimiento_compra_items,id',
            ],

            'items.*.oc_emitida_item_id' => [
                'nullable',
                'integer',
                'exists:oc_emitida_items,id',
            ],

            'items.*.producto_id' => [
                'nullable',
                'integer',
                'exists:productos,id',
            ],

            'items.*.producto_externo_id' => [
                'nullable',
                'integer',
                'exists:productos_externos,id',
            ],

            'items.*.descripcion' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'items.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.costo_unitario_estimado' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.moneda_id' => [
                'nullable',
                'integer',
                'exists:monedas,id',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {

                $modalidad = $this->input('modalidad');
                $ocEmitidaId = $this->input('oc_emitida_id');

                if (
                    $modalidad === 'oc_proveedor' &&
                    ! $ocEmitidaId
                ) {
                    $validator->errors()->add(
                        'oc_emitida_id',
                        'La modalidad OC proveedor requiere una OC emitida.'
                    );
                }

                if (
                    $modalidad === 'directa' &&
                    $ocEmitidaId
                ) {
                    $validator->errors()->add(
                        'oc_emitida_id',
                        'Una compra directa no debe tener OC emitida.'
                    );
                }
            },
        ];
    }
}
