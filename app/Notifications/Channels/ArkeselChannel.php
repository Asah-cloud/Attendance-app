<?php

namespace App\Notifications\Channels;

use App\Services\ArkeselBalanceAlerter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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

        try {
            $response = Http::asJson()
                ->withHeader('api-key', config('services.arkesel.key'))
                ->timeout(10)
                ->retry(3, 500)
                ->post(config('services.arkesel.url'), $payload);
        } catch (RequestException $exception) {
            $this->alertIfBalanceIssue($exception->response);
            throw $exception;
        }

        if ($response->failed()) {
            $this->alertIfBalanceIssue($response);
            throw new RuntimeException('Arkesel rejected the SMS request: '.$response->body());
        }

        return $response->json();
    }

    private function alertIfBalanceIssue(?Response $response): void
    {
        if ($response?->status() !== 402) {
            return;
        }

        app(ArkeselBalanceAlerter::class)->alertOnce($response->json('message') ?? $response->body());
    }
}
