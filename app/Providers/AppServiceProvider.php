<?php

namespace App\Providers;

use App\Listeners\LogResendEmailEvents;
use App\Listeners\RecordLoginActivity;
use App\Models\AccommodationBlock;
use App\Models\AccommodationFloor;
use App\Models\AccommodationRoom;
use App\Models\AccommodationSite;
use App\Models\Attendance;
use App\Models\BadgePrint;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\RoomAssignment;
use App\Models\SubscriptionPayment;
use App\Services\ApplicationCache;
use App\Services\LiveVersion;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LiveVersion::class);
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
            // Reports show and group people by their details (name, gender, country...), so any
            // change refreshes the events they are registered for.
            if (! $participant->wasRecentlyCreated && $participant->wasChanged()) {
                $participant->registrations()->pluck('event_id')->each(fn ($eventId) => $cache->invalidateEvent((int) $eventId));
            }
        });
        Participant::deleted(fn (Participant $participant) => app(ApplicationCache::class)->invalidateCompany($participant->company_id));

        $this->touchLivePagesOnChange();
        SubscriptionPayment::saved(fn (SubscriptionPayment $payment) => app(ApplicationCache::class)->invalidateCompany($payment->company_id));
        SubscriptionPayment::deleted(fn (SubscriptionPayment $payment) => app(ApplicationCache::class)->invalidateCompany($payment->company_id));
    }

    /**
     * Move the live marker (LiveVersion) of the event a change belongs to, so pages open on it
     * redraw. Bulk query updates skip model events; TouchLiveVersion covers those requests.
     */
    private function touchLivePagesOnChange(): void
    {
        $live = fn () => app(LiveVersion::class);
        $this->app->terminating(fn () => $live()->flush());
        Queue::after(fn () => $live()->flush());
        $participant = function (Participant $participant) use ($live): void {
            $live()->touchCompany($participant->company_id);
            $participant->registrations()->pluck('event_id')->each(fn ($eventId) => $live()->touchEvent((int) $eventId));
        };
        Participant::saved($participant);
        // Before deleting, while the registrations still say which events they were on.
        Participant::deleting($participant);

        foreach ([EventRegistration::class, BadgePrint::class, AccommodationSite::class] as $model) {
            $model::saved(fn ($record) => $live()->touchEvent($record->event_id));
            $model::deleted(fn ($record) => $live()->touchEvent($record->event_id));
        }
        Event::saved(fn (Event $event) => $live()->touchEvent($event->id));

        $roomEvent = fn (RoomAssignment $assignment) => $live()->touchEvent(EventRegistration::query()->whereKey($assignment->event_registration_id)->value('event_id'));
        RoomAssignment::saved($roomEvent);
        RoomAssignment::deleted($roomEvent);
        $inventory = [
            AccommodationBlock::class => fn ($block) => $block->site?->event_id,
            AccommodationFloor::class => fn ($floor) => $floor->block?->site?->event_id,
            AccommodationRoom::class => fn ($room) => $room->floor?->block?->site?->event_id,
        ];
        foreach ($inventory as $model => $eventOf) {
            $model::saved(fn ($record) => $live()->touchEvent($eventOf($record)));
            $model::deleted(fn ($record) => $live()->touchEvent($eventOf($record)));
        }
    }
}
