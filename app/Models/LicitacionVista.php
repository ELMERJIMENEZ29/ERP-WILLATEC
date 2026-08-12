<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicitacionVista extends Model
{
    protected $table = 'licitacion_vistas';

    protected $fillable = [
        'licitacion_id',
        'user_id',
        'visto_en',
    ];

    protected $casts = [
        'visto_en' => 'datetime',
    ];

    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
