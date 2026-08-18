<?php

namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Events\EmailBounced;
use Resend\Laravel\Events\EmailComplained;
use Resend\Laravel\Events\EmailDeliveryDelayed;
use Resend\Laravel\Events\EmailFailed;

class LogResendEmailEvents
{
    public function handleBounced(EmailBounced $event): void
    {
        $this->log('warning', 'Resend email bounced', $event->payload);
    }

    public function handleComplained(EmailComplained $event): void
    {
        $this->log('warning', 'Recipient marked a Resend email as spam', $event->payload);
    }

    public function handleFailed(EmailFailed $event): void
    {
        $this->log('error', 'Resend failed to send an email', $event->payload);
    }

    public function handleDeliveryDelayed(EmailDeliveryDelayed $event): void
    {
        $this->log('info', 'Resend email delivery delayed', $event->payload);
    }

    protected function log(string $level, string $message, array $payload): void
    {
        Log::log($level, $message, [
            'email_id' => $payload['data']['email_id'] ?? null,
            'to' => $payload['data']['to'] ?? null,
            'subject' => $payload['data']['subject'] ?? null,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            EmailBounced::class => 'handleBounced',
            EmailComplained::class => 'handleComplained',
            EmailFailed::class => 'handleFailed',
            EmailDeliveryDelayed::class => 'handleDeliveryDelayed',
        ];
    }
}
