<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjustarStockRequest extends FormRequest
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
            'nuevo_stock' => ['required', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
