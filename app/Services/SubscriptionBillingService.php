<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;

class SubscriptionBillingService
{
    /**
     * Apply a paid SubscriptionPayment to its Company. Idempotent — safe to
     * call from both the browser callback and the webhook, whichever wins
     * the race, without double-extending the subscription end date.
     */
    public function confirmPayment(SubscriptionPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === SubscriptionPayment::STATUS_PAID) {
                return;
            }

            $company = Company::query()->lockForUpdate()->findOrFail($locked->company_id);
            $today = now()->startOfDay();
            $currentEnd = $company->subscription_ends_at?->startOfDay();
            $extensionBase = $currentEnd && $currentEnd->gte($today) ? $currentEnd : $today;

            $company->update([
                'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
                'plan_key' => $locked->plan_key,
                'plan_price_minor' => $locked->amount_minor,
                'billing_currency' => $locked->currency,
                'payment_reference' => $locked->payment_reference,
                'subscription_started_at' => $company->subscription_started_at ?? now(),
                'subscription_ends_at' => $extensionBase->copy()->addMonths((int) config('plans.billing_period_months')),
                'subscription_auto_renews' => true,
                'subscription_cancelled_at' => null,
                'event_limit' => Plan::arrayByKey($locked->plan_key)['event_limit'],
            ]);

            $locked->update([
                'status' => SubscriptionPayment::STATUS_PAID,
                'paid_at' => now(),
            ]);
        });
    }
}
