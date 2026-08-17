<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\ArkeselChannel;

trait UsesAttendanceChannels
{
    public function via(object $notifiable): array
    {
        $channels = [];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        if (config('services.arkesel.enabled') && ! empty($notifiable->phone)) {
            $channels[] = ArkeselChannel::class;
        }

        return $channels;
    }
}
