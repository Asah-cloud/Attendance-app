<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\EventRegistration;
use App\Models\Feature;
use App\Models\User;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventAttendeeChargeReady;
use App\Notifications\EventAttendeeChargeRefundIssued;
use App\Notifications\EventInvoiceReady;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventBillingService
{
    public function __construct(private AttendeePricingResolver $pricing, private PaystackService $paystack) {}

    public function estimate(Event $event): array
    {
        $registeredCount = $this->registeredCount($event);
        $calc = $this->pricing->calculate($event->company, $registeredCount, $event);

        return array_merge($calc, ['registered_count' => $registeredCount]);
    }

    /**
     * @param  array<int, string>  $featureKeys  Advanced features the manager selected for this event.
     */
    public function finalize(Event $event, array $featureKeys = []): EventAttendeeCharge
    {
        if ($existing = EventAttendeeCharge::where('event_id', $event->id)->first()) {
            return $existing;
        }

        $charge = DB::transaction(function () use ($event, $featureKeys): EventAttendeeCharge {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($existing = EventAttendeeCharge::where('event_id', $lockedEvent->id)->first()) {
                return $existing;
            }

            $company = $lockedEvent->company;
            $registeredCount = $this->registeredCount($lockedEvent);
            $calc = $this->pricing->calculate($company, $registeredCount, $lockedEvent);

            $features = $this->resolveFeatures($featureKeys);
            $featuresAmount = (int) $features->sum('cost_minor');
            $featureBreakdown = $this->featureBreakdown($features);

            $charge = EventAttendeeCharge::create([
                'event_id' => $lockedEvent->id,
                'company_id' => $company->id,
                'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
                'registered_count' => $registeredCount,
                'tier_breakdown' => $calc['breakdown'],
                'amount_minor' => $calc['amount_minor'] + $featuresAmount,
                'features_amount_minor' => $featuresAmount,
                'feature_breakdown' => $featureBreakdown,
                'currency' => config('plans.currency'),
                'finalized_at' => now(),
            ]);

            foreach ($features as $feature) {
                $lockedEvent->features()->create([
                    'feature_key' => $feature->key,
                    'name' => $feature->name,
                    'cost_minor' => $feature->cost_minor,
                ]);
            }

            return $charge;
        });

        $this->notifyManagers($event, new EventAttendeeChargeReady($charge));

        return $charge;
    }

    /**
     * A manager (or admin) asking for an invoice, instead of an instant bill.
     * The attendee total is locked in immediately like finalize(), but the
     * charge starts in pending_review — nothing is payable and no features
     * are granted until an admin approves it via approveInvoice().
     *
     * @param  array<int, string>  $featureKeys
     */
    public function requestInvoice(Event $event, array $featureKeys = []): EventAttendeeCharge
    {
        if ($existing = EventAttendeeCharge::where('event_id', $event->id)->first()) {
            return $existing;
        }

        $charge = DB::transaction(function () use ($event, $featureKeys): EventAttendeeCharge {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($existing = EventAttendeeCharge::where('event_id', $lockedEvent->id)->first()) {
                return $existing;
            }

            $company = $lockedEvent->company;
            $registeredCount = $this->registeredCount($lockedEvent);
            $calc = $this->pricing->calculate($company, $registeredCount, $lockedEvent);

            $features = $this->resolveFeatures($featureKeys);
            $featuresAmount = (int) $features->sum('cost_minor');

            return EventAttendeeCharge::create([
                'event_id' => $lockedEvent->id,
                'company_id' => $company->id,
                'status' => EventAttendeeCharge::STATUS_PENDING_REVIEW,
                'registered_count' => $registeredCount,
                'tier_breakdown' => $calc['breakdown'],
                'amount_minor' => $calc['amount_minor'] + $featuresAmount,
                'features_amount_minor' => $featuresAmount,
                'feature_breakdown' => $this->featureBreakdown($features),
                'currency' => config('plans.currency'),
                'finalized_at' => now(),
            ]);
        });

        return $charge;
    }

    /**
     * An admin reviewing a requested invoice: they may drop or add advanced
     * features and apply a flat discount before the bill becomes payable.
     * The attendee subtotal itself is never edited directly here — it was
     * locked in at request time from real registration data.
     *
     * @param  array<int, string>  $featureKeys  The final set of features to bill.
     */
    public function approveInvoice(EventAttendeeCharge $charge, User $reviewer, array $featureKeys, int $discountMinor, ?string $discountReason): EventAttendeeCharge
    {
        $discountMinor = max(0, $discountMinor);

        $charge = DB::transaction(function () use ($charge, $reviewer, $featureKeys, $discountMinor, $discountReason): EventAttendeeCharge {
            $locked = EventAttendeeCharge::query()->lockForUpdate()->findOrFail($charge->id);
            abort_unless($locked->needsInvoice(), 422, 'This bill already has an invoice, or cannot be invoiced.');

            $event = Event::query()->lockForUpdate()->findOrFail($locked->event_id);
            $attendeeSubtotal = $locked->amount_minor - $locked->features_amount_minor;

            $features = $this->resolveFeatures($featureKeys);
            $featuresAmount = (int) $features->sum('cost_minor');
            $total = max(0, $attendeeSubtotal + $featuresAmount - $discountMinor);

            $event->features()->delete();
            foreach ($features as $feature) {
                $event->features()->create([
                    'feature_key' => $feature->key,
                    'name' => $feature->name,
                    'cost_minor' => $feature->cost_minor,
                ]);
            }

            $locked->update([
                'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
                'features_amount_minor' => $featuresAmount,
                'feature_breakdown' => $this->featureBreakdown($features),
                'discount_minor' => $discountMinor,
                'discount_reason' => $discountMinor > 0 ? $discountReason : null,
                'amount_minor' => $total,
                'invoice_number' => $locked->invoice_number ?? $this->nextInvoiceNumber(),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $locked->fresh();
        });

        $this->notifyManagers($charge->event, new EventInvoiceReady($charge));
        $charge->update(['invoice_emailed_at' => now()]);

        return $charge;
    }

    public function resendInvoiceEmail(EventAttendeeCharge $charge): void
    {
        abort_if(! $charge->hasApprovedInvoice(), 422, 'This invoice is not ready to send yet.');

        $this->notifyManagers($charge->event, new EventInvoiceReady($charge));
        $charge->update(['invoice_emailed_at' => now()]);
    }

    public function renderInvoicePdf(EventAttendeeCharge $charge)
    {
        $charge->loadMissing(['event', 'company']);

        return Pdf::loadView('events.billing.invoice-pdf', ['charge' => $charge, 'event' => $charge->event, 'company' => $charge->company])
            ->setPaper('a4');
    }

    private function resolveFeatures(array $featureKeys)
    {
        return Feature::query()
            ->where('tier', Feature::TIER_ADVANCED)
            ->where('is_active', true)
            ->whereIn('key', $featureKeys)
            ->get();
    }

    private function featureBreakdown($features): array
    {
        return $features->map(fn (Feature $feature) => [
            'key' => $feature->key,
            'name' => $feature->name,
            'cost_minor' => $feature->cost_minor,
        ])->values()->all();
    }

    private function nextInvoiceNumber(): string
    {
        $sequence = (int) (EventAttendeeCharge::whereNotNull('invoice_number')->count()) + 1;

        return 'INV-'.now()->format('Y').'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Start a Paystack checkout for a finalized bill and return the
     * authorization_url to redirect the manager to.
     */
    public function startCheckout(EventAttendeeCharge $charge, string $callbackUrl): string
    {
        $reference = DB::transaction(function () use ($charge): string {
            $locked = EventAttendeeCharge::query()->lockForUpdate()->findOrFail($charge->id);
            abort_unless(
                in_array($locked->status, [EventAttendeeCharge::STATUS_PENDING_PAYMENT, EventAttendeeCharge::STATUS_PAYMENT_FAILED], true),
                422,
                'This bill cannot be paid in its current state.'
            );

            $reference = 'EVB-'.$locked->event_id.'-'.Str::upper(Str::random(16));
            $locked->update([
                'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
                'payment_reference' => $reference,
            ]);

            return $reference;
        });

        $charge->loadMissing('company');

        $data = $this->paystack->initialize(
            amountMinor: $charge->amount_minor,
            currency: $charge->currency,
            email: $charge->company->email ?: 'billing@'.Str::slug($charge->company->name).'.example',
            reference: $reference,
            callbackUrl: $callbackUrl,
            metadata: ['flow' => 'event_billing', 'charge_id' => $charge->id, 'event_id' => $charge->event_id],
        );

        return $data['authorization_url'];
    }

    /**
     * Mark a charge paid. Idempotent — safe to call from both the browser
     * callback and the webhook, whichever wins the race.
     */
    public function confirmPayment(EventAttendeeCharge $charge): void
    {
        DB::transaction(function () use ($charge): void {
            $locked = EventAttendeeCharge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($locked->status === EventAttendeeCharge::STATUS_PAID) {
                return;
            }

            $locked->update([
                'status' => EventAttendeeCharge::STATUS_PAID,
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

        if (in_array($charge->status, [EventAttendeeCharge::STATUS_PENDING_REVIEW, EventAttendeeCharge::STATUS_PENDING_PAYMENT], true)) {
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

        $checkedInCount = $event->attendances()
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', false))
            ->distinct('participant_id')->count('participant_id');
        $calc = $this->pricing->calculate($event->company, $checkedInCount, $event);
        $attendeePaidMinor = $charge->amount_minor - $charge->features_amount_minor;
        $refundAmount = max(0, $attendeePaidMinor - $calc['amount_minor']);

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
        return $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', false))
            ->count();
    }

    private function notifyManagers(Event $event, Notification $notification): void
    {
        $event->loadMissing('company.users');
        foreach ($event->company->users->where('role', 'manager') as $manager) {
            NotifiesPerChannel::send($manager, $notification);
        }
    }
}
