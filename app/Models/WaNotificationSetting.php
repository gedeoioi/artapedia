<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaNotificationSetting extends Model
{
    protected $fillable = [
        'name', 'is_active', 'recipient', 'template',
        'schedule', 'api_url', 'api_token', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_sent_at' => 'datetime'];
    }
}
