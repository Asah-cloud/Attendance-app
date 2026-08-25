<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\EventRegistration;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventAttendeeChargeReady;
use App\Notifications\EventAttendeeChargeRefundIssued;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventBillingService
{
    public function __construct(private AttendeePricingResolver $pricing) {}

    public function estimate(Event $event): array
    {
        $registeredCount = $this->registeredCount($event);
        $calc = $this->pricing->calculate($event->company, $registeredCount);

        return array_merge($calc, ['registered_count' => $registeredCount]);
    }

    public function finalize(Event $event): EventAttendeeCharge
    {
        if ($existing = EventAttendeeCharge::where('event_id', $event->id)->first()) {
            return $existing;
        }

        $charge = DB::transaction(function () use ($event): EventAttendeeCharge {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($existing = EventAttendeeCharge::where('event_id', $lockedEvent->id)->first()) {
                return $existing;
            }

            $company = $lockedEvent->company;
            $registeredCount = $this->registeredCount($lockedEvent);
            $calc = $this->pricing->calculate($company, $registeredCount);

            return EventAttendeeCharge::create([
                'event_id' => $lockedEvent->id,
                'company_id' => $company->id,
                'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
                'registered_count' => $registeredCount,
                'tier_breakdown' => $calc['breakdown'],
                'amount_minor' => $calc['amount_minor'],
                'currency' => config('plans.currency'),
                'finalized_at' => now(),
            ]);
        });

        $this->notifyManagers($event, new EventAttendeeChargeReady($charge));

        return $charge;
    }

    public function pay(EventAttendeeCharge $charge): void
    {
        DB::transaction(function () use ($charge): void {
            $locked = EventAttendeeCharge::query()->lockForUpdate()->findOrFail($charge->id);
            abort_unless($locked->status === EventAttendeeCharge::STATUS_PENDING_PAYMENT, 422, 'This bill cannot be paid in its current state.');

            $locked->update([
                'status' => EventAttendeeCharge::STATUS_PAID,
                'payment_reference' => 'TEST-'.Str::upper(Str::random(20)),
                'paid_at' => now(),
            ]);
        });
    }

    public function voidForCancellation(Event $event): void
    {
        $charge = EventAttendeeCharge::where('event_id', $event->id)->first();
        if (! $charge) {
            return;
        }

        if ($charge->status === EventAttendeeCharge::STATUS_PENDING_PAYMENT) {
            $charge->update(['status' => EventAttendeeCharge::STATUS_VOIDED]);

            return;
        }

        if ($charge->status === EventAttendeeCharge::STATUS_PAID) {
            $charge->update([
                'checked_in_count' => 0,
                'refund_breakdown' => $charge->tier_breakdown,
                'refund_amount_minor' => $charge->amount_minor,
                'status' => EventAttendeeCharge::STATUS_REFUND_DUE,
                'reconciled_at' => now(),
            ]);
            $this->notifyManagers($event, new EventAttendeeChargeRefundIssued($charge->fresh()));
        }
    }

    public function reconcile(EventAttendeeCharge $charge): void
    {
        abort_unless($charge->status === EventAttendeeCharge::STATUS_PAID, 422, 'This bill is not awaiting reconciliation.');
        $event = $charge->event;
        abort_unless($event->status === 'closed', 422, 'This event has not closed yet.');

        $checkedInCount = $event->checkedInParticipantCount();
        $calc = $this->pricing->calculate($event->company, $checkedInCount);
        $refundAmount = max(0, $charge->amount_minor - $calc['amount_minor']);

        $charge->update([
            'checked_in_count' => $checkedInCount,
            'refund_breakdown' => $calc['breakdown'],
            'refund_amount_minor' => $refundAmount,
            'status' => $refundAmount > 0 ? EventAttendeeCharge::STATUS_REFUND_DUE : EventAttendeeCharge::STATUS_RECONCILED,
            'reconciled_at' => now(),
        ]);

        if ($refundAmount > 0) {
            $this->notifyManagers($event, new EventAttendeeChargeRefundIssued($charge->fresh()));
        }
    }

    public function markRefunded(EventAttendeeCharge $charge): void
    {
        abort_unless($charge->status === EventAttendeeCharge::STATUS_REFUND_DUE, 422, 'This bill has no refund pending.');
        $charge->update(['status' => EventAttendeeCharge::STATUS_REFUNDED, 'refunded_at' => now()]);
    }

    public function finalizeDue(): void
    {
        Event::query()
            ->whereNull('cancelled_at')
            ->whereDate('event_date', '<=', now()->toDateString())
            ->doesntHave('attendeeCharge')
            ->chunkById(100, function ($events): void {
                foreach ($events as $event) {
                    $this->finalize($event);
                }
            });
    }

    public function reconcileDue(): void
    {
        EventAttendeeCharge::query()
            ->where('status', EventAttendeeCharge::STATUS_PAID)
            ->whereNull('reconciled_at')
            ->with('event')
            ->chunkById(100, function ($charges): void {
                foreach ($charges as $charge) {
                    if ($charge->event && $charge->event->status === 'closed') {
                        $this->reconcile($charge);
                    }
                }
            });
    }

    private function registeredCount(Event $event): int
    {
        return $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)->count();
    }

    private function notifyManagers(Event $event, Notification $notification): void
    {
        $event->loadMissing('company.users');
        foreach ($event->company->users->where('role', 'manager') as $manager) {
            NotifiesPerChannel::send($manager, $notification);
        }
    }
}
