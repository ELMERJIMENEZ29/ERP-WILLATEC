<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WooCommerceSyncLog extends Model
{
    protected $table = 'woocommerce_sync_logs';

    protected $fillable = [
        'tipo',
        'direccion',
        'endpoint',
        'payload',
        'response',
        'status_code',
        'estado',
        'mensaje_error',
        'referencia_tipo',
        'referencia_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
        ];
    }
}
