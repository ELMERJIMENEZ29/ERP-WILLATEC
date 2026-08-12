<?php

namespace App\Http\Requests;

use App\Models\RequerimientoCompra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequerimientoCompraRequest extends FormRequest
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
            'origen_tipo' => ['required', 'string', Rule::in(RequerimientoCompra::origenes())],
            'oc_recibida_id' => ['nullable', 'integer', 'exists:oc_recibidas,id'],
            'prioridad' => ['nullable', 'string', Rule::in([
                RequerimientoCompra::PRIORIDAD_BAJA,
                RequerimientoCompra::PRIORIDAD_NORMAL,
                RequerimientoCompra::PRIORIDAD_ALTA,
                RequerimientoCompra::PRIORIDAD_URGENTE,
            ])],
            'asignado_a' => ['nullable', 'integer', 'exists:users,id'],
            'observacion' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.oc_recibida_item_id' => ['nullable', 'integer', 'exists:oc_recibida_items,id'],
            'items.*.cotizacion_item_id' => ['nullable', 'integer', 'exists:cotizacion_items,id'],
            'items.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'items.*.producto_externo_id' => ['nullable', 'integer', 'exists:productos_externos,id'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad_requerida' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
