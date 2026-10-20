<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class MobileNotificationService
{
    public function remember(User $recipient, string $id, string $title, string $body, int $event): void
    {
        DB::table('notifications')->insertOrIgnore(['id' => $id, 'type' => 'event_reminder',
            'notifiable_type' => $recipient->getMorphClass(), 'notifiable_id' => $recipient->id,
            'data' => json_encode(['title' => $title, 'body' => $body, 'event_id' => $event], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now()]);
    }
}
