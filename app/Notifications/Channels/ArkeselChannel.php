<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ArkeselChannel
{
    public function send(object $notifiable, Notification $notification): ?array
    {
        $message = $notification->toArkesel($notifiable);
        $recipient = method_exists($notifiable, 'routeNotificationForArkesel')
            ? $notifiable->routeNotificationForArkesel($notification)
            : ($notifiable->phone ?? null);

        if (! $recipient || ! config('services.arkesel.key')) {
            return null;
        }

        $payload = [
            'sender' => config('services.arkesel.sender'),
            'message' => $message,
            'recipients' => [$recipient],
            'sandbox' => (bool) config('services.arkesel.sandbox'),
        ];

        if (config('services.arkesel.callback_url')) {
            $payload['callback_url'] = config('services.arkesel.callback_url');
        }

        $response = Http::asJson()
            ->withHeader('api-key', config('services.arkesel.key'))
            ->timeout(10)
            ->retry(3, 500)
            ->post(config('services.arkesel.url'), $payload);

        if ($response->failed()) {
            throw new RuntimeException('Arkesel rejected the SMS request: '.$response->body());
        }

        return $response->json();
    }
}
