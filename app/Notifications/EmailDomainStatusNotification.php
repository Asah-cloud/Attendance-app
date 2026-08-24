<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailDomainStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company, public string $type) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->message());

        if ($this->type === 'verified') {
            $mail->line('Future event emails can now be sent from '.$this->company->email_from_address.'.');
        }

        return $mail
            ->action($this->type === 'verified' ? 'Open your dashboard' : 'Review DNS setup', $this->actionUrl())
            ->salutation('Warm regards, '.config('mail.from.name'));
    }

    private function subject(): string
    {
        return match ($this->type) {
            'verified' => 'Your company email sender is now active',
            'failed' => 'Action required: email domain verification failed',
            'delayed' => 'Your email domain is still waiting for verification',
            default => 'Reminder: complete your company email setup',
        };
    }

    private function message(): string
    {
        return match ($this->type) {
            'verified' => 'Resend has verified '.$this->company->resend_domain_name.' and your requested sender identity is approved.',
            'failed' => 'Resend could not verify '.$this->company->resend_domain_name.'. Please review the DNS records in Organization Settings or ask your domain administrator for help.',
            'delayed' => 'Your request has been waiting for more than 72 hours. Some DNS records may be missing or incorrect. Please review them and check verification again.',
            default => 'Your email sender request is still waiting for DNS setup. Add all the DNS records shown in Organization Settings, then check verification.',
        };
    }

    private function actionUrl(): string
    {
        return $this->type === 'verified'
            ? route('dashboard')
            : route('organization.branding.edit').'#email-domain-setup';
    }
}
