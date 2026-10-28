<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\ParticipantImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    Storage::fake('local');

    $this->company = Company::create(['name' => 'Undo Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Undo Event', 'event_date' => now()]);
    // Already on this event before the import, and must stay.
    $this->issac = Participant::create(['company_id' => $this->company->id, 'name' => 'Issac Kwabia', 'phone' => '545829491', 'gender' => 'Male']);
    $this->event->registrations()->create(['participant_id' => $this->issac->id, 'status' => 'confirmed']);
    // Known to the company from another event; the import adds him to this one too.
    $this->kofi = Participant::create(['company_id' => $this->company->id, 'name' => 'Kofi Mensah', 'phone' => '241234567', 'gender' => 'Male']);
    $this->otherEvent = Event::create(['company_id' => $this->company->id, 'title' => 'Earlier Event', 'event_date' => now()]);
    $this->otherEvent->registrations()->create(['participant_id' => $this->kofi->id, 'status' => 'confirmed']);
});

function undoCsv(string ...$rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('wrong-list.csv', implode("\n", ['ID,Name,Gender,Room Group,Category,Phone,Email', ...$rows]));
}

function importWrongList($test): ParticipantImport
{
    $test->actingAs($test->manager)
        ->post(route('events.import.store', $test->event), ['file' => undoCsv(
            '1,Ama Owusu,Female,,Participant,0201112222,',
            '2,Yaw Boateng,Male,,Participant,0203334444,',
            '3,Kofi Mensah,Male,,Participant,0241234567,',
        )])->assertSessionHas('success');

    return ParticipantImport::sole();
}

it('records an import and offers to undo it on the attendance page', function () {
    $import = importWrongList($this);

    expect($import->file_name)->toBe('wrong-list.csv')
        ->and($import->user_id)->toBe($this->manager->id)
        ->and($import->added_count)->toBe(2)
        ->and($import->addedToEventCount())->toBe(3)
        ->and($import->participant_ids)->toHaveCount(2);

    $this->get(route('events.attendance', $this->event))->assertOk()
        ->assertSee('data-last-import', false)
        ->assertSee('wrong-list.csv')
        ->assertSee('added 3 people')
        ->assertSee('Recent imports')
        ->assertSee('Undo import');
});

it('removes only the people an import added, keeping anyone who has checked in', function () {
    $import = importWrongList($this);
    $yaw = Participant::where('name', 'Yaw Boateng')->sole();
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $yaw->id, 'day' => 1, 'status' => 'present']);

    $this->delete(route('events.import.undo', [$this->event, $import]))
        ->assertSessionHas('success', 'Import of wrong-list.csv undone: 2 people removed. 1 kept because they have already checked in.');

    expect($this->event->registrations()->with('participant')->get()->pluck('participant.name')->sort()->values()->all())->toBe(['Issac Kwabia', 'Yaw Boateng'])
        // A person the import created is deleted; one known from another event only leaves this one.
        ->and(Participant::where('name', 'Ama Owusu')->exists())->toBeFalse()
        ->and($this->kofi->fresh())->not->toBeNull()
        ->and($this->otherEvent->registrations()->count())->toBe(1)
        ->and($import->fresh()->undone_at)->not->toBeNull()
        ->and($import->fresh()->undone_by)->toBe($this->manager->id);

    $this->get(route('events.attendance', $this->event))->assertOk()
        ->assertDontSee('data-last-import', false)
        ->assertSee('Undone');
});

it('says so when an import that updated people already on the list is undone', function () {
    importWrongList($this);
    // A second list adds one new person and updates Issac, who was already on the event.
    $this->post(route('events.import.store', $this->event), ['file' => undoCsv('9,Esi Quaye,Female,,Participant,0209990000,', '8,Issac Kwabia,Male,,Speaker,0545829491,')]);
    $second = ParticipantImport::latest('id')->first();

    $this->delete(route('events.import.undo', [$this->event, $second]))
        ->assertSessionHas('success', 'Import of wrong-list.csv undone: 1 person removed. Changes it made to people already on the list were not reversed.');
    expect($this->event->registrations()->count())->toBe(4);
});

it('undoes an import only once', function () {
    $import = importWrongList($this);
    $this->delete(route('events.import.undo', [$this->event, $import]));

    $this->delete(route('events.import.undo', [$this->event, $import]))
        ->assertSessionHas('error', 'That import (wrong-list.csv) has already been undone.');
});

it('does not undo an import through another event or for another company', function () {
    $import = importWrongList($this);

    $this->delete(route('events.import.undo', [$this->otherEvent, $import]))->assertNotFound();

    $stranger = User::factory()->create(['company_id' => Company::create(['name' => 'Other Co'])->id, 'role' => 'manager']);
    $stranger->assignRole('manager');
    $this->actingAs($stranger)->delete(route('events.import.undo', [$this->event, $import]))->assertForbidden();

    expect($import->fresh()->undone_at)->toBeNull()->and($this->event->registrations()->count())->toBe(4);
});

it('does not record an import that added no one', function () {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => undoCsv('1,Issac Kwabia,Male,,Participant,0545829491,')])
        ->assertSessionHas('success');

    expect(ParticipantImport::count())->toBe(0);
});
