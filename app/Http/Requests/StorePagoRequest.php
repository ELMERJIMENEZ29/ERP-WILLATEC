<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_pago' => ['nullable', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'moneda_id' => ['nullable', 'integer', 'exists:monedas,id'],
            'tipo_cambio' => ['nullable', 'numeric', 'gt:0'],
            'metodo_pago' => ['nullable', 'string', 'max:50'],
            'pagado_por' => ['nullable', 'required_if:metodo_pago,efectivo_dev,tarjeta_dev', 'string', 'max:255'],
            'monto_desembolsado' => ['nullable', 'required_if:metodo_pago,efectivo_dev,tarjeta_dev', 'numeric', 'gt:0'],
            'numero_cuotas' => ['nullable', 'required_if:metodo_pago,tarjeta_dev', 'integer', 'min:1', 'max:120'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
