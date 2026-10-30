<?php

namespace App\Notifications;

use App\Models\AttendanceReset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the company's managers that someone reset (or undid a reset of) an event's attendance. */
class AttendanceWasReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AttendanceReset $reset, public bool $undone = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->reset->event;
        $who = $this->undone ? $this->reset->undoneByName() : ($this->reset->user?->name ?? 'Someone');
        $checkIns = $this->reset->removed_count.' check-in'.($this->reset->removed_count === 1 ? '' : 's');

        return (new MailMessage)
            ->subject(($this->undone ? 'Attendance reset undone - ' : 'Attendance was reset - ').$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->undone
                ? "{$who} undid the attendance reset for \"{$event->title}\" ({$this->reset->label()}). The {$checkIns} are back."
                : "{$who} reset the attendance for \"{$event->title}\" ({$this->reset->label()}) and removed {$checkIns}.")
            ->line('Time: '.now()->timezone(config('app.timezone'))->format('j M Y, H:i').'.')
            ->when(! $this->undone, fn (MailMessage $mail) => $mail->line("If this wasn't expected, open the event's attendance page and choose Reset attendance → Undo."))
            ->action('Open attendance', route('events.attendance', $event))
            ->salutation('Warm regards, '.config('mail.from.name'));
    }
}
