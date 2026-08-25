<?php

namespace App\Notifications;

use App\Models\MealDistribution;
use App\Notifications\Concerns\UsesAttendanceChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MealStockLow extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public MealDistribution $distribution) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->distribution->event;

        return (new MailMessage)
            ->subject('Low food stock - '.$this->distribution->name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('"'.$this->distribution->name.'" for '.$event->title.' is running low: '.$this->distribution->remainingPortions().' of '.$this->distribution->total_portions.' portions remain.')
            ->action('View food distribution', route('events.meals.index', $event))
            ->salutation('Warm regards, '.config('mail.from.name'));
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->distribution->event;

        return 'Low stock: "'.$this->distribution->name.'" ('.$event->title.') has '.$this->distribution->remainingPortions().' portion(s) left. '.route('events.meals.index', $event);
    }
}
