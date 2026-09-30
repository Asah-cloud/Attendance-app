<?php

namespace App\Notifications;

use App\Models\EventAttendeeCharge;
use App\Notifications\Concerns\UsesAttendanceChannels;
use App\Services\EventBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventInvoiceReady extends Notification implements ShouldQueue
{
    use Queueable, UsesAttendanceChannels;

    public function __construct(public EventAttendeeCharge $charge) {}

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->charge->event;
        $amount = number_format($this->charge->amount_minor / 100, 2);

        $mail = (new MailMessage)
            ->subject('Invoice '.$this->charge->invoice_number.' - '.$event->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Invoice '.$this->charge->invoice_number.' for "'.$event->title.'" is ready: '.$this->charge->currency.' '.$amount.'.');

        if ($this->charge->discount_minor > 0) {
            $mail->line('This includes a discount of '.$this->charge->currency.' '.number_format($this->charge->discount_minor / 100, 2).'.');
        }

        $mail->action('Review and pay', route('events.billing.show', $event))
            ->salutation('Warm regards, '.config('mail.from.name'));

        $pdf = app(EventBillingService::class)->renderInvoicePdf($this->charge);

        return $mail->attachData($pdf->output(), $this->charge->invoice_number.'.pdf', ['mime' => 'application/pdf']);
    }

    public function toArkesel(object $notifiable): string
    {
        $event = $this->charge->event;
        $amount = number_format($this->charge->amount_minor / 100, 2);

        return 'Invoice '.$this->charge->invoice_number.' for "'.$event->title.'" is ready: '.$this->charge->currency.' '.$amount.'. '.route('events.billing.show', $event);
    }
}
