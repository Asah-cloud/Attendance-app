<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Notification;

class NotifiesPerChannel
{
    /**
     * Send $notification to $notifiable with each eligible channel
     * (mail, SMS, ...) dispatched as its own queued job, instead of one
     * job that sends to every channel in sequence. That way a failure on
     * one channel can't mark a channel that already succeeded as failed,
     * and can't cause it to be resent on retry.
     */
    public static function send(object $notifiable, Notification $notification, ?array $allowedChannels = null): void
    {
        foreach ($notification->eligibleChannels($notifiable) as $channel) {
            if ($allowedChannels !== null && ! in_array($channel, $allowedChannels, true)) {
                continue;
            }
            $notifiable->notify($notification->onChannel($channel));
        }
    }
}
