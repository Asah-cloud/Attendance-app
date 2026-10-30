<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignInCodeNotification extends Notification
{
    public function __construct(public string $code, public int $minutes, public bool $invite = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');
        $lasts = $this->minutes >= 24 * 60 ? intdiv($this->minutes, 24 * 60).' days' : $this->minutes.' minutes';

        return (new MailMessage)
            ->subject($this->invite ? "Your {$app} sign-in code" : "{$this->code} is your {$app} sign-in code")
            ->greeting('Hello '.$notifiable->name.'!')
            ->when($this->invite, fn (MailMessage $mail) => $mail->line("You've been added to the team on {$app}. You don't need a password: sign in with this code."))
            ->line('Your sign-in code is:')
            ->line('**'.chunk_split($this->code, 3, ' ').'**')
            ->action('Sign in', route('login.code', ['email' => $notifiable->email]))
            ->line("The code works once and lasts {$lasts}. Next time, ask for a new code on the sign-in page.")
            ->line("If you weren't expecting this, you can ignore this email.")
            ->salutation('Warm regards, '.config('mail.from.name'));
    }
}
