<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaPorPagarProrroga extends Model
{
    protected $table = 'cuenta_por_pagar_prorrogas';

    protected $fillable = ['cuenta_por_pagar_id', 'fecha_anterior', 'fecha_nueva', 'motivo', 'creado_por'];

    protected function casts(): array
    {
        return ['fecha_anterior' => 'date:Y-m-d', 'fecha_nueva' => 'date:Y-m-d'];
    }

    public function cuentaPorPagar()
    {
        return $this->belongsTo(CuentaPorPagar::class);
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
