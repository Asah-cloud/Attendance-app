<?php

namespace App\Notifications;

use App\Models\Company;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanySubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public Company $company, public bool $expired) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->expired ? 'Action needed: your subscription has expired' : 'A friendly subscription reminder')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->text())
            ->action('Manage my subscription', route('billing.index'))
            ->line('If you need any help, please contact our support team. We are happy to assist you.')
            ->salutation('Warm regards, Asah Apex');
    }

    public function toArkesel(object $notifiable): string
    {
        return $this->text().' '.route('billing.index');
    }

    private function text(): string
    {
        return $this->expired
            ? 'Your '.$this->company->name.' subscription has expired. Please renew it to restore event management access. We look forward to continuing to serve your team.'
            : 'A friendly reminder: your '.$this->company->name.' subscription expires on '.$this->company->subscription_ends_at->format('M j, Y').'. Renew early to keep your event services running without interruption.';
    }
}
