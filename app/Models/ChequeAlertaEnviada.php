<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChequeAlertaEnviada extends Model
{
    protected $table = 'cheque_alertas_enviadas';

    protected $fillable = ['cheque_id', 'fecha_vencimiento', 'dias_antes', 'sent_at'];

    protected function casts(): array
    {
        return ['fecha_vencimiento' => 'date:Y-m-d', 'sent_at' => 'datetime'];
    }
}
