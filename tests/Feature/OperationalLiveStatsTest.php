<?php

use App\Livewire\AccommodationStats;
use App\Livewire\ArrivalStats;
use App\Livewire\MealDistributionStats;
use App\Livewire\MealOverviewStats;
use App\Livewire\RegistrationStats;
use App\Livewire\StaffCheckInStats;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealDistribution;
use App\Models\Participant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('manager');
});

it('renders live operational totals from current database state', function () {
    $company = Company::create(['name' => 'Operations']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Live Operations', 'event_date' => now()]);
    $attendee = Participant::create(['company_id' => $company->id, 'name' => 'Guest']);
    $registration = EventRegistration::create([
        'event_id' => $event->id,
        'participant_id' => $attendee->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
        'accommodation_required' => true,
    ]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $attendee->id, 'day' => 0]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $meal->collections()->create([
        'event_registration_id' => $registration->id,
        'participant_id' => $attendee->id,
        'issued_by' => $manager->id,
        'quantity' => 1,
        'collected_at' => now(),
    ]);

    $this->actingAs($manager);

    Livewire::test(ArrivalStats::class, ['event' => $event])->assertSee('Yet to arrive')->assertSee('1');
    Livewire::test(StaffCheckInStats::class, ['event' => $event])->assertSee('Assigned staff');
    Livewire::test(MealOverviewStats::class, ['event' => $event])->assertSee('confirmed participants');
    Livewire::test(MealDistributionStats::class, ['event' => $event, 'meal' => $meal])->assertSee('9 left')->assertSee('Issued');
    Livewire::test(AccommodationStats::class, ['event' => $event])->assertSee('Need rooms')->assertSee('1');
    Livewire::test(RegistrationStats::class, ['event' => $event])->assertSee('Confirmed')->assertSee('1');
});
