<?php

namespace App\Http\Requests;

use App\Models\RequerimientoCompra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerarRequerimientoDesdeOcRequest extends FormRequest
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
            'prioridad' => ['nullable', 'string', Rule::in([
                RequerimientoCompra::PRIORIDAD_BAJA,
                RequerimientoCompra::PRIORIDAD_NORMAL,
                RequerimientoCompra::PRIORIDAD_ALTA,
                RequerimientoCompra::PRIORIDAD_URGENTE,
            ])],
            'asignado_a' => ['nullable', 'integer', 'exists:users,id'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
