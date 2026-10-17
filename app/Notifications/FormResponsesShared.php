<?php

namespace App\Notifications;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells someone they can now see a form's responses, with a temporary password if their account is new. */
class FormResponsesShared extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Form $form, public ?string $temporaryPassword = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->form->event;
        $mail = (new MailMessage)
            ->subject('Responses shared with you: '.$this->form->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line(($event->company?->name ?? $event->title).' has shared the responses to "'.$this->form->title.'" ('.$event->title.') with you.')
            ->line('You can view the responses and download them as Excel or PDF.');

        if ($this->temporaryPassword) {
            $mail->line('An account has been created for you.')
                ->line('Email: '.$notifiable->email)
                ->line('Temporary password: '.$this->temporaryPassword)
                ->line('You will be asked to choose your own password the first time you sign in.');
        }

        return $mail->action('View responses', route('shared-forms.show', $this->form))
            ->salutation('Warm regards, '.config('mail.from.name'));
    }
}
