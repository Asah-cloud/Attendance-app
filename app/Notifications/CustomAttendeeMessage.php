<?php

namespace App\Notifications;

use App\Notifications\Channels\ArkeselChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A manager-composed, free-text message. Sent synchronously (no
 * ShouldQueue) from inside SendCustomAttendeeMessageJob, which is already
 * queued per recipient — that lets the job record success/failure
 * immediately instead of racing a second, independently-queued job.
 */
class CustomAttendeeMessage extends Notification
{
    public function __construct(
        public string $body,
        public ?string $subject,
        private string $channel,
        private ?string $smsSenderId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [$this->channel === 'sms' ? ArkeselChannel::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject ?: 'A message for you');

        foreach (preg_split('/\r?\n/', trim($this->body)) as $line) {
            $mail->line($line);
        }

        return $mail;
    }

    public function smsSenderId(): ?string
    {
        return $this->smsSenderId;
    }

    public function toArkesel(object $notifiable): string
    {
        return $this->body;
    }
}
