<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudSobrecompra extends Model
{
    protected $table = 'solicitudes_sobrecompra';

    protected $fillable = ['numero', 'estado', 'justificacion', 'comentario_resolucion', 'payload_compra', 'solicitado_por', 'resuelto_por', 'resuelto_en', 'compra_id', 'utilizado_en'];

    protected function casts(): array
    {
        return ['payload_compra' => 'array', 'resuelto_en' => 'datetime', 'utilizado_en' => 'datetime'];
    }

    public function solicitante() { return $this->belongsTo(User::class, 'solicitado_por'); }
    public function resolvedor() { return $this->belongsTo(User::class, 'resuelto_por'); }
    public function compra() { return $this->belongsTo(Compra::class); }
}
