<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'system_enabled',
        'email_enabled',
        'sound_enabled',
        'browser_enabled',
        'modules',
        'custom_sound_url',
    ];

    protected function casts(): array
    {
        return [
            'system_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'browser_enabled' => 'boolean',
            'modules' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
