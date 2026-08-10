<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CotizacionItemProveedor extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'cotizacion_item_proveedores';

    protected $fillable = [
        'cotizacion_item_id',
        'proveedor_id',
        'nombre',
        'link',
        'precio',
        'notas',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function cotizacionItem(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    protected function auditModelName(): string
    {
        return 'Proveedor de item de cotizacion';
    }
}
