<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecepcionCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_recepcion' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.compra_item_id' => ['required', 'integer', 'exists:compra_items,id'],
            'items.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:5000'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.costo_unitario_provisional' => ['nullable', 'numeric', 'min:0'],
            'items.*.moneda_id' => ['nullable', 'integer', 'exists:monedas,id'],
        ];
    }
}
