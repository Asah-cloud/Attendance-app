<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Services\ApplicationCache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function cacheManager(string $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role);

    return $user;
}

it('lets a manager clear cached attendance totals for their event', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = cacheManager('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    app(ApplicationCache::class)->rememberEvent($event->id, 'attendance-stats:v2:day:1', fn () => ['totalMembers' => 626, 'presentCount' => 0]);

    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Guest']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    $this->actingAs($manager)
        ->post(route('events.cache.clear', $event))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->get(route('events.attendance', $event))->assertOk()->assertSee('>1<', false)->assertDontSee('>626<', false);
});

it('lets an admin clear an event cache and blocks unauthorized company users', function () {
    $company = Company::create(['name' => 'Acme']);
    $otherCompany = Company::create(['name' => 'Other']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);

    $this->actingAs(cacheManager('manager', $otherCompany))->post(route('events.cache.clear', $event))->assertForbidden();
    $this->actingAs(cacheManager('usher', $company))->post(route('events.cache.clear', $event))->assertForbidden();
    $this->actingAs(cacheManager('admin'))->post(route('events.cache.clear', $event))->assertRedirect()->assertSessionHas('success');
});
