<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    public function allows(User $user, string $eventKey, string $channel = 'email'): bool
    {
        return NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->exists();
    }
}
