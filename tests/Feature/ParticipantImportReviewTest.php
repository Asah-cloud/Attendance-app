<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    Storage::fake('local');

    $this->company = Company::create(['name' => 'Review Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Review Event', 'event_date' => now()]);
    $this->issac = Participant::create(['company_id' => $this->company->id, 'name' => 'Issac Kwabia', 'phone' => '545829491', 'gender' => 'Male']);
    $this->event->registrations()->create(['participant_id' => $this->issac->id, 'status' => 'confirmed']);
});

function reviewCsv(string ...$rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('list.csv', implode("\n", ['ID,Name,Gender,Room Group,Category,Phone,Email', ...$rows]));
}

it('holds back an import whose row would rename someone and lists that row for review', function () {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Duncan Amissah,Male,,Participant,0545829491,', '2,Ama Owusu,Female,,Participant,0241112222,')])
        ->assertSessionHas('import_review');

    $review = session('participant-import-review.'.$this->event->id);
    expect($review['rows'])->toHaveCount(1)
        ->and($review['rows'][0])->toMatchArray(['row' => 2, 'name' => 'Duncan Amissah', 'existing_name' => 'Issac Kwabia', 'matched_by' => 'phone'])
        // The trial saved nothing - not even the row that needs no review.
        ->and($this->issac->fresh()->name)->toBe('Issac Kwabia')
        ->and(Participant::count())->toBe(1);

    $this->get(route('events.attendance', $this->event))
        ->assertOk()
        ->assertSee('Check 1 row before importing')
        ->assertSee('Duncan Amissah')
        ->assertSee('Issac Kwabia')
        ->assertSee('name="decisions[2]" value="new" checked', false);
});

it('imports a reviewed row as the manager chose', function (string $decision, string $issacName, int $participants) {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Duncan Amissah,Male,,Participant,0545829491,', '2,Ama Owusu,Female,,Participant,0241112222,')]);

    $this->post(route('events.import.confirm', $this->event), ['decisions' => [2 => $decision]])
        ->assertSessionHas('success');

    expect($this->issac->fresh()->name)->toBe($issacName)
        ->and(Participant::count())->toBe($participants)
        ->and(Participant::where('name', 'Ama Owusu')->exists())->toBeTrue()
        ->and($this->event->confirmedParticipants()->count())->toBe($participants)
        ->and(session()->has('participant-import-review.'.$this->event->id))->toBeFalse()
        ->and(Storage::disk('local')->allFiles('participant-imports'))->toBe([]);
})->with([
    'update the existing person' => ['update', 'Duncan Amissah', 2],
    'add a new person' => ['new', 'Issac Kwabia', 3],
    'skip the row' => ['skip', 'Issac Kwabia', 2],
]);

it('adds a reviewed row as a new person when no choice was sent', function () {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Duncan Amissah,Male,,Participant,0545829491,')]);
    $this->post(route('events.import.confirm', $this->event))
        ->assertSessionHas('success', 'Import complete: 1 added, 0 updated. No email or SMS notifications were sent.');

    expect($this->issac->fresh()->name)->toBe('Issac Kwabia')
        ->and(Participant::where('name', 'Duncan Amissah')->value('phone'))->toBe('545829491');
});

it('flags rows in the same file that share one phone under different names', function () {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Kofi Mensah,Male,,Participant,0209998888,', '2,Yaw Boateng,Male,,Participant,0209998888,')])
        ->assertSessionHas('import_review');

    expect(collect(session('participant-import-review.'.$this->event->id)['rows'])->pluck('name')->all())->toBe(['Yaw Boateng']);
});

it('imports straight away and notifies nobody early when no row needs review', function () {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Issac Kwabia,Male,Achimota,Participant,0545829491,', '2,Ama Owusu,Female,,Participant,0241112222,')])
        ->assertSessionMissing('import_review')
        ->assertSessionHas('success', 'Import complete: 1 added, 1 updated. No email or SMS notifications were sent.');

    expect($this->issac->fresh()->room_group)->toBe('Achimota')
        ->and(Storage::disk('local')->allFiles('participant-imports'))->toBe([]);
    Notification::assertNothingSent();
});

it('cancels a reviewed import without saving anything', function () {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Duncan Amissah,Male,,Participant,0545829491,', '2,Ama Owusu,Female,,Participant,0241112222,')]);

    $this->post(route('events.import.cancel', $this->event))
        ->assertSessionHas('success', 'Import cancelled. Nothing was changed.');
    $this->post(route('events.import.confirm', $this->event))
        ->assertSessionHas('error', 'That import has expired. Please upload the file again.');

    expect(Participant::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('participant-imports'))->toBe([]);
});

it('keeps another company\'s manager from confirming the import', function () {
    $this->actingAs($this->manager)
        ->post(route('events.import.store', $this->event), ['file' => reviewCsv('1,Duncan Amissah,Male,,Participant,0545829491,')]);

    $other = User::factory()->create(['company_id' => Company::create(['name' => 'Other'])->id, 'role' => 'manager']);
    $other->assignRole('manager');
    $this->actingAs($other)->post(route('events.import.confirm', $this->event))->assertForbidden();
});
