<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\ArkeselChannel;

trait UsesAttendanceChannels
{
    private ?string $lockedChannel = null;

    public function via(object $notifiable): array
    {
        return $this->lockedChannel !== null
            ? [$this->lockedChannel]
            : $this->eligibleChannels($notifiable);
    }

    /**
     * The channels this notification would use for $notifiable, ignoring
     * any channel lock. Used by NotifiesPerChannel to dispatch each
     * eligible channel as its own queued job, so one channel failing
     * (e.g. an SMS provider being out of balance) can never affect
     * whether the others already succeeded, or cause them to resend.
     */
    public function eligibleChannels(object $notifiable): array
    {
        $channels = [];

        if (! empty($notifiable->email) && ! str_ends_with($notifiable->email, '@example.invalid')) {
            $channels[] = 'mail';
        }

        if (config('services.arkesel.enabled') && ! empty($notifiable->phone)) {
            $channels[] = ArkeselChannel::class;
        }

        return $channels;
    }

    public function onChannel(string $channel): static
    {
        $clone = clone $this;
        $clone->lockedChannel = $channel;

        return $clone;
    }
}
