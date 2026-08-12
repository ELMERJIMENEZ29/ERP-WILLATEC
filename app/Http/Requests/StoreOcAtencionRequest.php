<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOcAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_atencion' => ['nullable', 'date'],
            'tipo_atencion' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.oc_recibida_item_id' => ['required', 'integer', 'exists:oc_recibida_items,id'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.producto_serie_ids' => ['nullable', 'array'],
            'items.*.producto_serie_ids.*' => ['integer', 'exists:producto_series,id'],
        ];
    }
}
