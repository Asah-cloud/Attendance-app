<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Sends push notifications to browsers, and forgets the ones that have been switched off. */
class WebPushSender
{
    public function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $payload
     * @return int how many were delivered
     */
    public function send(Collection $subscriptions, array $payload, bool $urgent = false): int
    {
        if (! $this->configured() || $subscriptions->isEmpty()) {
            return 0;
        }

        $push = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 86400, 'urgency' => $urgent ? 'high' : 'normal']);

        $byEndpoint = $subscriptions->keyBy('endpoint');
        foreach ($subscriptions as $subscription) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]), json_encode($payload));
        }

        $delivered = 0;
        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;
            } elseif ($report->isSubscriptionExpired()) {
                $byEndpoint[$report->getEndpoint()]?->delete();
            } else {
                Log::warning('Push notification failed', ['reason' => $report->getReason()]);
            }
        }

        return $delivered;
    }
}
