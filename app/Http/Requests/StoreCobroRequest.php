<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCobroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_cobro' => ['nullable', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'moneda_id' => ['nullable', 'integer', 'exists:monedas,id'],
            'metodo_cobro' => ['nullable', 'string', 'max:50'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
