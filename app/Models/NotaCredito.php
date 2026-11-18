<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaCredito extends Model
{
    protected $table = 'notas_credito';

    protected $fillable = ['comprobante_id', 'tipo_anulacion', 'serie', 'numero', 'fecha_emision', 'monto', 'xml_hash', 'xml_path', 'pdf_path', 'requiere_devolucion_fisica', 'devolucion_fisica_confirmada', 'motivo', 'creado_por'];

    protected function casts(): array
    {
        return ['fecha_emision' => 'date:Y-m-d', 'monto' => 'decimal:2', 'requiere_devolucion_fisica' => 'boolean', 'devolucion_fisica_confirmada' => 'boolean'];
    }

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function items()
    {
        return $this->hasMany(NotaCreditoItem::class);
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
