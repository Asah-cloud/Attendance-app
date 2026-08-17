<?php

use App\Models\Company;
use App\Models\EventRegistration;
use App\Notifications\CompanySubscriptionNotification;
use App\Notifications\RegistrationLifecycleNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    EventRegistration::query()
        ->where('status', EventRegistration::STATUS_CONFIRMED)
        ->whereNull('reminder_sent_at')
        ->whereHas('event', fn ($query) => $query
            ->whereNull('cancelled_at')
            ->whereDate('event_date', now()->addDay()->toDateString()))
        ->with(['event', 'participant'])
        ->chunkById(100, function ($registrations): void {
            foreach ($registrations as $registration) {
                $registration->participant->notify(new RegistrationLifecycleNotification($registration, 'reminder'));
                $registration->update(['reminder_sent_at' => now()]);
            }
        });
})->hourly()->name('send-event-reminders')->withoutOverlapping();

Schedule::call(function (): void {
    Company::query()
        ->whereDate('subscription_ends_at', now()->addDays(7)->toDateString())
        ->whereNull('subscription_expiry_warning_sent_at')
        ->with('users')
        ->chunkById(100, function ($companies): void {
            foreach ($companies as $company) {
                foreach ($company->users->where('role', 'manager') as $manager) {
                    $manager->notify(new CompanySubscriptionNotification($company, false));
                }
                $company->update(['subscription_expiry_warning_sent_at' => now()]);
            }
        });

    Company::query()
        ->whereDate('subscription_ends_at', '<', now()->toDateString())
        ->whereNull('subscription_expired_notice_sent_at')
        ->with('users')
        ->chunkById(100, function ($companies): void {
            foreach ($companies as $company) {
                foreach ($company->users->where('role', 'manager') as $manager) {
                    $manager->notify(new CompanySubscriptionNotification($company, true));
                }
                $company->update(['subscription_expired_notice_sent_at' => now()]);
            }
        });
})->dailyAt('08:00')->name('send-subscription-lifecycle-notices')->withoutOverlapping();
