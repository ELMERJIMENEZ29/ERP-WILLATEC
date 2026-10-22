<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SnakeScore extends Model
{
    protected $fillable = ['user_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
