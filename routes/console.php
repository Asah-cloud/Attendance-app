<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\CompanySubscriptionNotification;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Services\ConfirmationReminderSender;
use App\Services\EmailDomainLifecycleManager;
use App\Services\EventBillingService;
use App\Services\RegistrationLifecycleService;
use App\Services\RoomAllocationService;
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
                app(RegistrationLifecycleService::class)->notify($registration, 'reminder');
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
                    NotifiesPerChannel::send($manager, new CompanySubscriptionNotification($company, false));
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
                    NotifiesPerChannel::send($manager, new CompanySubscriptionNotification($company, true));
                }
                $company->update(['subscription_expired_notice_sent_at' => now()]);
            }
        });
})->dailyAt('08:00')->name('send-subscription-lifecycle-notices')->withoutOverlapping();

Schedule::call(fn () => app(ConfirmationReminderSender::class)->sendDue())
    ->dailyAt('09:00')->name('send-confirmation-reminders')->withoutOverlapping();

Schedule::call(fn () => app(EmailDomainLifecycleManager::class)->processPending())
    ->hourly()->name('check-email-domain-verifications')->withoutOverlapping();

Schedule::call(fn () => app(EventBillingService::class)->finalizeDue())
    ->dailyAt('06:00')->name('finalize-event-attendee-charges')->withoutOverlapping();

Schedule::call(fn () => app(EventBillingService::class)->reconcileDue())
    ->dailyAt('07:00')->name('reconcile-event-attendee-charges')->withoutOverlapping();

Schedule::call(function (): void {
    Event::query()
        ->where('accommodation_enabled', true)
        ->whereNotNull('accommodation_self_select_closes_at')
        ->where('accommodation_self_select_closes_at', '<', now())
        ->whereHas('registrations', fn ($query) => $query
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->where('accommodation_required', true)
            ->whereDoesntHave('roomAssignment'))
        ->chunkById(50, function ($events): void {
            foreach ($events as $event) {
                app(RoomAllocationService::class)->commit($event, null);
            }
        });
})->hourly()->name('allocate-accommodation-after-self-select')->withoutOverlapping();
