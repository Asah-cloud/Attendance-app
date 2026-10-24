<?php

namespace App\Providers;

use App\Listeners\LogResendEmailEvents;
use App\Listeners\RecordLoginActivity;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\SubscriptionPayment;
use App\Services\ApplicationCache;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Paginator::useTailwind();

        EventFacade::subscribe(LogResendEmailEvents::class);
        EventFacade::subscribe(RecordLoginActivity::class);

        $invalidateEvent = function (Attendance|EventRegistration $record): void {
            $event = Event::query()->find($record->event_id);
            app(ApplicationCache::class)->invalidateEvent($record->event_id, $event?->company_id);
        };

        Attendance::saved($invalidateEvent);
        Attendance::deleted($invalidateEvent);
        EventRegistration::saved($invalidateEvent);
        EventRegistration::deleted($invalidateEvent);

        Event::saved(function (Event $event): void {
            $cache = app(ApplicationCache::class);
            $cache->invalidateEvent($event->id, $event->company_id);

            if ($event->wasChanged('company_id')) {
                $cache->invalidateCompany((int) $event->getOriginal('company_id'));
            }
        });
        Event::deleted(fn (Event $event) => app(ApplicationCache::class)->invalidateEvent($event->id, $event->company_id));

        Company::saved(fn (Company $company) => app(ApplicationCache::class)->invalidateCompany($company->id));
        Company::deleted(fn (Company $company) => app(ApplicationCache::class)->invalidateCompany($company->id));
        Participant::saved(function (Participant $participant): void {
            $cache = app(ApplicationCache::class);
            $cache->invalidateCompany($participant->company_id);
            // Reports group people by these fields, so refresh the events they are registered for.
            if (! $participant->wasRecentlyCreated && $participant->wasChanged(['name', 'phone', 'gender', 'category', 'room_group', 'department', 'is_support_staff'])) {
                $participant->registrations()->pluck('event_id')->each(fn ($eventId) => $cache->invalidateEvent((int) $eventId));
            }
        });
        Participant::deleted(fn (Participant $participant) => app(ApplicationCache::class)->invalidateCompany($participant->company_id));
        SubscriptionPayment::saved(fn (SubscriptionPayment $payment) => app(ApplicationCache::class)->invalidateCompany($payment->company_id));
        SubscriptionPayment::deleted(fn (SubscriptionPayment $payment) => app(ApplicationCache::class)->invalidateCompany($payment->company_id));
    }
}
