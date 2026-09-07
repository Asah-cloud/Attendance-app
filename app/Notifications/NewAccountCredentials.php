<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAccountCredentials extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $temporaryPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' account')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('An account has been created for you on '.config('app.name').'.')
            ->line('Email: '.$notifiable->email)
            ->line('Temporary password: '.$this->temporaryPassword)
            ->action('Sign in', route('login'))
            ->line('For security, you will be asked to choose your own password the first time you sign in.')
            ->salutation('Warm regards, '.config('mail.from.name'));
    }
}
