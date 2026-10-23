<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Cheque extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'banco_id', 'numero', 'proveedor_id', 'moneda_id', 'monto', 'fecha_emision',
        'fecha_cheque', 'fecha_entrega', 'fecha_cobro', 'estado', 'evidencia_path',
        'evidencia_nombre', 'observacion', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_emision' => 'date',
            'fecha_cheque' => 'date',
            'fecha_entrega' => 'date',
            'fecha_cobro' => 'date',
        ];
    }

    public function banco(): BelongsTo { return $this->belongsTo(Banco::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function moneda(): BelongsTo { return $this->belongsTo(Moneda::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
    public function aplicaciones(): BelongsToMany
    {
        return $this->belongsToMany(Compra::class, 'cheque_aplicaciones')
            ->withPivot(['monto_aplicado'])->withTimestamps();
    }
}
