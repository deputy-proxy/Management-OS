<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'user_id', 'event_key', 'event_id', 'channel', 'status',
        'sent_at', 'last_error', 'payload',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'payload' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
