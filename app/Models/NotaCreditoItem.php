<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaCreditoItem extends Model
{
    protected $table = 'nota_credito_items';

    protected $fillable = ['nota_credito_id', 'comprobante_item_id', 'cantidad', 'monto'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'monto' => 'decimal:2'];
    }

    public function notaCredito()
    {
        return $this->belongsTo(NotaCredito::class);
    }

    public function comprobanteItem()
    {
        return $this->belongsTo(ComprobanteItem::class);
    }
}
