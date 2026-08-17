<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarRecepcionCompraRequest extends FormRequest
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
            'items' => ['nullable', 'array'],
            'items.*.recepcion_item_id' => ['required_with:items', 'integer', 'exists:recepcion_items,id'],
            'items.*.series' => ['nullable', 'array'],
            'items.*.series.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
