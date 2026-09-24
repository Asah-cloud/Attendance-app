<?php

namespace App\Notifications;

use App\Models\RoomAssignment;
use App\Notifications\Concerns\UsesAttendanceChannels;
use App\Services\CompanyMail;
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

        return CompanyMail::make(
            $event,
            'Your room for '.$event->title,
            'Hello '.$notifiable->name.'!',
            [
                'Your accommodation has been assigned.',
                'Room: '.$this->assignment->room->label(),
                $site->address ? 'Address: '.$site->address : null,
                $site->check_in_instructions ? 'Check-in: '.$site->check_in_instructions : null,
            ],
            'View room and registration',
            route('registrations.confirmation', $this->assignment->registration->management_token),
        );
    }

    public function toArkesel(object $notifiable): string
    {
        $this->assignment->loadMissing(['registration.event', 'room.floor.block.site']);

        return 'Hello '.$notifiable->name.'! Your room for '.$this->assignment->registration->event->title.' is '.$this->assignment->room->label().'. Details: '.route('registrations.confirmation', $this->assignment->registration->management_token);
    }

    public function smsSenderId(): ?string
    {
        return $this->assignment->registration->event->company?->approvedSmsSenderId();
    }
}
