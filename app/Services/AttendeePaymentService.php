<?php

namespace App\Services;

use App\Models\AttendeePayment;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\FormResponse;
use App\Models\PlatformSetting;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventRegistrationSubmitted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Attendee payments on forms and the registration form. Each payment goes to the event's
 * organisation through its Paystack sub-account; when the platform fee is switched on, the
 * platform's share is taken off each payment, and the organisation bears Paystack's fees.
 */
class AttendeePaymentService
{
    public const FEE_ENABLED = 'attendee_payment_fee_enabled';

    public const FEE_PERCENT = 'attendee_payment_fee_percent';

    public function __construct(private PaystackService $paystack) {}

    public static function feeEnabled(): bool
    {
        return PlatformSetting::get(self::FEE_ENABLED) === '1';
    }

    public static function feePercent(): float
    {
        return (float) (PlatformSetting::get(self::FEE_PERCENT) ?? 0);
    }

    /** The platform's share of a payment, in pesewas (0 while the fee is off). */
    public static function feeFor(int $amountMinor): int
    {
        if (! self::feeEnabled() || self::feePercent() <= 0) {
            return 0;
        }

        return min($amountMinor - 1, (int) round($amountMinor * self::feePercent() / 100));
    }

    /** Record a payment for a form response or registration and return Paystack's checkout link. */
    public function start(EventRegistration|FormResponse $payable, Event $event, int $amountMinor, string $email): string
    {
        $event->loadMissing('company');
        $payment = AttendeePayment::create([
            'company_id' => $event->company_id,
            'event_id' => $event->id,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'reference' => 'ATP-'.$event->id.'-'.Str::upper(Str::random(20)),
            'amount_minor' => $amountMinor,
            'platform_fee_minor' => self::feeFor($amountMinor),
            'currency' => config('plans.currency'),
            'payer_email' => $email,
        ]);
        $payable->update(['payment_status' => 'unpaid']);

        return $this->checkout($payment);
    }

    /**
     * Paystack's checkout link for another go at a payment that hasn't gone through. Paystack
     * won't take a reference twice, so each try is a new payment; the earlier one stays pending
     * in case it still completes (a late Mobile Money approval), and settles the same response.
     */
    public function retry(AttendeePayment $payment): string
    {
        $payment = $payment->replicate(['reference', 'status', 'channel', 'paid_at'])->fill([
            'reference' => 'ATP-'.$payment->event_id.'-'.Str::upper(Str::random(20)),
            'status' => AttendeePayment::STATUS_PENDING,
            'platform_fee_minor' => self::feeFor($payment->amount_minor),
        ]);
        $payment->save();

        return $this->checkout($payment);
    }

    private function checkout(AttendeePayment $payment): string
    {
        $payment->loadMissing('company');
        $subaccount = $payment->company?->payout_subaccount_code;
        if (! $subaccount) {
            throw new RuntimeException('This organisation has no payout account.');
        }

        $data = $this->paystack->initialize(
            amountMinor: $payment->amount_minor,
            currency: $payment->currency,
            email: $payment->payer_email,
            reference: $payment->reference,
            callbackUrl: route('payments.return', $payment->reference),
            metadata: ['flow' => 'attendee_payment', 'payment_id' => $payment->id, 'event_id' => $payment->event_id],
            extra: array_filter([
                'subaccount' => $subaccount,
                'bearer' => 'subaccount',
                'transaction_charge' => $payment->platform_fee_minor ?: null,
                'channels' => ['mobile_money', 'card'],
            ]),
        );

        return $data['authorization_url'];
    }

    /** Ask Paystack how a payment went and record it; true once it's paid. */
    public function verify(AttendeePayment $payment): bool
    {
        if ($payment->status === AttendeePayment::STATUS_PAID) {
            return true;
        }

        try {
            $data = $this->paystack->verify($payment->reference);
        } catch (RuntimeException) {
            return false;
        }

        return $this->settle($payment, $data);
    }

    /** Record Paystack's answer (from verify or the webhook); true once it's paid. */
    public function settle(AttendeePayment $payment, array $data): bool
    {
        $paid = ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? 0) === $payment->amount_minor
            && ($data['currency'] ?? null) === $payment->currency;

        if (! $paid) {
            if (in_array($data['status'] ?? null, ['failed', 'abandoned', 'reversed'], true)) {
                $payment->update(['status' => AttendeePayment::STATUS_FAILED]);
            }

            return false;
        }

        $newlyPaid = DB::transaction(function () use ($payment, $data): bool {
            $locked = AttendeePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === AttendeePayment::STATUS_PAID) {
                return false;
            }
            $locked->update(['status' => AttendeePayment::STATUS_PAID, 'paid_at' => now(), 'channel' => $data['channel'] ?? null]);
            $locked->payable?->update(['payment_status' => 'paid', 'amount_paid_minor' => $locked->amount_minor]);

            return true;
        });
        $payment->refresh();

        if ($newlyPaid) {
            $this->afterPaid($payment->payable);
        }

        return true;
    }

    /** A registration's "we've got your registration" message waits until it's paid for. */
    private function afterPaid(?Model $payable): void
    {
        if ($payable instanceof EventRegistration) {
            $payable->load(['event.company', 'participant']);
            NotifiesPerChannel::send($payable->participant, new EventRegistrationSubmitted($payable), $payable->event->attendeeNotificationChannels());
        }
    }
}
