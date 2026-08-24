<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = config("plans.plans.{$this->company->plan_key}.name", ucfirst((string) $this->company->plan_key));

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Welcome! Your '.$this->company->name.' attendance workspace has been created successfully.')
            ->line('Plan: '.$planName)
            ->line('Your team can now create events, register attendees, send confirmations, and manage attendance from one place.')
            ->action('Open your dashboard', route('dashboard'))
            ->line('If you need help getting started, reply to this email and our team will assist you.')
            ->salutation('Warm regards, '.config('mail.from.name'));
    }
}
