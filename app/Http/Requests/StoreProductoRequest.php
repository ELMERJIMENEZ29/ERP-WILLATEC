<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'sku' => [
                Rule::requiredIf($this->boolean('controla_stock', true)),
                'nullable',
                'string',
                'max:100',
                'unique:productos,sku',
            ],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:100'],
            'codigo_barras' => ['nullable', 'string', 'max:100'],
            'serie' => ['nullable', 'string', 'max:100'],
            'factura_numero' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'tipo_producto' => ['nullable', Rule::in(['stock', 'servicio', 'externo', 'personalizado'])],
            'controla_stock' => ['nullable', 'boolean'],
            'stock_actual' => ['nullable', 'numeric', 'min:0'],
            'stock_reservado' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'precio_referencial' => ['nullable', 'numeric', 'min:0'],
            'moneda_id' => ['nullable', 'exists:monedas,id'],
            'unidad_medida' => ['nullable', 'string', 'max:50'],
            'estado' => ['nullable', Rule::in(['nuevo', 'usado'])],
            'stock' => ['nullable', 'integer', 'min:0'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'imagen' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ];
    }
}
