<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class RecepcionCompra extends Model
{
    use Auditable, LogsActivity;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $table = 'recepciones_compra';

    protected $fillable = [
        'numero',
        'compra_id',
        'proveedor_id',
        'fecha_recepcion',
        'estado',
        'observacion',
        'recibido_por',
        'confirmado_en',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecepcionItem::class);
    }

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date:Y-m-d',
            'confirmado_en' => 'datetime',
        ];
    }
}
