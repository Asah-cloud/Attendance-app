<?php

namespace App\Notifications;

use App\Models\EventRegistration;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoomSelectionInvite extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventRegistration $registration) {}

    public function toMail(object $notifiable): MailMessage
    {
        $this->registration->loadMissing('event.company');
        $event = $this->registration->event;
        $mail = (new MailMessage)
            ->subject('Choose your room for '.$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You can now choose your own room for '.$event->title.'.')
            ->line('Selection closes '.$event->accommodation_self_select_closes_at->format('D j M Y, g:ia').'. You can change your choice until then.')
            ->action('Choose your room', route('registrations.room.select', $this->registration->registration_code));

        $company = $event->company;

        return $company?->approvedEmailFromAddress()
            ? $mail->from($company->approvedEmailFromAddress(), $company->email_from_name ?: $company->name)
            : $mail;
    }

    public function toArkesel(object $notifiable): string
    {
        $this->registration->loadMissing('event');
        $event = $this->registration->event;

        return 'Hello '.$notifiable->name.'! Choose your room for '.$event->title.' before '
            .$event->accommodation_self_select_closes_at->format('D j M g:ia').': '
            .route('registrations.room.select', $this->registration->registration_code);
    }

    public function smsSenderId(): ?string
    {
        return $this->registration->event->company?->approvedSmsSenderId();
    }
}
