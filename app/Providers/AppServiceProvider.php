<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\SubscriptionPayment;
use App\Services\ApplicationCache;
use Illuminate\Pagination\Paginator;
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
        Participant::saved(fn (Participant $participant) => app(ApplicationCache::class)->invalidateCompany($participant->company_id));
        Participant::deleted(fn (Participant $participant) => app(ApplicationCache::class)->invalidateCompany($participant->company_id));
        SubscriptionPayment::saved(fn (SubscriptionPayment $payment) => app(ApplicationCache::class)->invalidateCompany($payment->company_id));
        SubscriptionPayment::deleted(fn (SubscriptionPayment $payment) => app(ApplicationCache::class)->invalidateCompany($payment->company_id));
    }
}
