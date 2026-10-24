<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Services\AttendanceReportData;
use App\Services\ParticipantRegistrationService;
use App\Support\BadgeDesign;
use Spatie\Permission\Models\Role;

function registerFor(Event $event, array $details): array
{
    return app(ParticipantRegistrationService::class)->register($event, $details + ['name' => 'Rev. Kwesi Mensah', 'phone' => '0243000111'], 'import');
}

it('keeps an earlier event\'s category and area when the same person is imported into a later event', function () {
    $company = Company::create(['name' => 'TAC']);
    $conference = Event::create(['company_id' => $company->id, 'title' => 'World Conference', 'event_date' => now()->addWeek()]);
    $orientation = Event::create(['company_id' => $company->id, 'title' => 'Orientation', 'event_date' => now()->subDays(5)]);

    [$person, $first] = registerFor($conference, ['category' => 'PARTICIPANT', 'room_group' => 'Nyanyano/Kasoa']);
    [$same, $second] = registerFor($orientation, ['category' => 'Pastor', 'room_group' => 'Essam-Debisso']);

    expect($same->id)->toBe($person->id)
        // Each event keeps what its own list said.
        ->and($first->fresh()->participant->category)->toBe('PARTICIPANT')
        ->and($first->fresh()->participant->room_group)->toBe('Nyanyano/Kasoa')
        ->and($second->fresh()->participant->category)->toBe('Pastor')
        ->and($second->fresh()->participant->room_group)->toBe('Essam-Debisso')
        // Event attendee lists, reports and badges read the event's values.
        ->and($conference->confirmedParticipants()->first()->category)->toBe('PARTICIPANT')
        ->and(app(AttendanceReportData::class)->forPeriod($conference, 'all')['absentUsers']->first()->room_group)->toBe('Nyanyano/Kasoa')
        ->and(BadgeDesign::values($conference, $first->fresh())['category'])->toBe('PARTICIPANT')
        // Outside any event (the directory) the person shows their latest details.
        ->and($person->fresh()->category)->toBe('Pastor');
});

it('records a blank area on an import as no area for that event only', function () {
    $company = Company::create(['name' => 'TAC']);
    $earlier = Event::create(['company_id' => $company->id, 'title' => 'Earlier', 'event_date' => now()->subMonth()]);
    $later = Event::create(['company_id' => $company->id, 'title' => 'Later', 'event_date' => now()]);

    [, $old] = registerFor($earlier, ['category' => 'Pastor', 'room_group' => 'Takoradi']);
    [, $new] = registerFor($later, ['category' => 'PARTICIPANT', 'room_group' => null]);

    expect($new->fresh()->room_group)->toBeNull()
        ->and($old->fresh()->room_group)->toBe('Takoradi');
});

it('lets the registrations page change one event\'s category without touching another event', function () {
    $company = Company::create(['name' => 'TAC']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    Role::findOrCreate('manager');
    $manager->assignRole('manager');
    $a = Event::create(['company_id' => $company->id, 'title' => 'A', 'event_date' => now()->addDays(3)]);
    $b = Event::create(['company_id' => $company->id, 'title' => 'B', 'event_date' => now()->addDays(9)]);
    $person = Participant::create(['company_id' => $company->id, 'name' => 'Ama', 'category' => 'Member', 'gender' => 'Female']);
    $inA = $a->registrations()->create(['participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    $inB = $b->registrations()->create(['participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    $this->actingAs($manager)->patch(route('events.registrations.participant.update', [$a, $inA]), [
        'name' => 'Ama', 'gender' => 'Female', 'phone' => '0200000000', 'category' => 'Wife',
    ])->assertSessionHas('success');

    expect($inA->fresh()->category)->toBe('Wife')
        ->and($inB->fresh()->category)->toBe('Member');
});
