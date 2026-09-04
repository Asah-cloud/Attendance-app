<?php

namespace App\Notifications;

use App\Models\RoomAssignment;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoomAssigned extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public RoomAssignment $assignment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $this->assignment->loadMissing(['registration.event.company', 'room.floor.block.site']);
        $event = $this->assignment->registration->event;
        $site = $this->assignment->room->floor->block->site;
        $mail = (new MailMessage)->subject('Your room for '.$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your accommodation has been assigned.')
            ->line('Room: '.$this->assignment->room->label())
            ->when($site->address, fn ($message) => $message->line('Address: '.$site->address))
            ->when($site->check_in_instructions, fn ($message) => $message->line('Check-in: '.$site->check_in_instructions))
            ->action('View room and registration', route('registrations.confirmation', $this->assignment->registration->registration_code));

        $company = $event->company;

        return $company?->approvedEmailFromAddress() ? $mail->from($company->approvedEmailFromAddress(), $company->email_from_name ?: $company->name) : $mail;
    }

    public function toArkesel(object $notifiable): string
    {
        $this->assignment->loadMissing(['registration.event', 'room.floor.block.site']);

        return 'Hello '.$notifiable->name.'! Your room for '.$this->assignment->registration->event->title.' is '.$this->assignment->room->label().'. Details: '.route('registrations.confirmation', $this->assignment->registration->registration_code);
    }

    public function smsSenderId(): ?string
    {
        return $this->assignment->registration->event->company?->approvedSmsSenderId();
    }
}
