<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MapearWooCommerceProductoRequest extends FormRequest
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
            'producto_id' => ['required', 'exists:productos,id'],
            'woocommerce_store_id' => ['nullable', 'integer', 'min:1'],
            'woo_product_id' => ['required', 'integer', 'min:1'],
            'woo_variation_id' => ['nullable', 'integer', 'min:1'],
            'woo_parent_id' => ['nullable', 'integer', 'min:1'],
            'woo_sku' => ['required', 'string', 'max:255'],
            'manage_stock' => ['nullable', 'boolean'],
        ];
    }
}
