<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailDomainDnsInstructions extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Action required: verify '.$this->company->resend_domain_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your custom email sender request has been created. Add the DNS records below wherever your domain is managed. You can forward this email to your web developer or IT team.')
            ->line('Domain: '.$this->company->resend_domain_name);

        foreach ($this->company->resend_domain_records ?? [] as $record) {
            $line = ($record['type'] ?? 'Record').' | '.($record['name'] ?? '').' | '.($record['value'] ?? '');
            if (isset($record['priority'])) {
                $line .= ' | Priority: '.$record['priority'];
            }
            $mail->line($line);
        }

        return $mail
            ->action('View DNS instructions', route('organization.branding.edit').'#email-domain-setup')
            ->line('After adding every record, return to Organization Settings and click “Check verification”.')
            ->salutation('Warm regards, '.config('mail.from.name'));
    }
}
