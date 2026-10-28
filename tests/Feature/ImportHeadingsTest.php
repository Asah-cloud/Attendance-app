<?php

use App\Imports\UsersImport;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('manager');
    Storage::fake('local');
    $this->company = Company::create(['name' => 'Headings Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Headings Event', 'event_date' => now()]);
});

function importHeadingsCsv($test, string ...$rows): void
{
    $test->actingAs($test->manager)
        ->post(route('events.import.store', $test->event), ['file' => UploadedFile::fake()->createWithContent('list.csv', implode("\n", $rows))])
        ->assertSessionHas('success');
}

it('reads columns by their headings when the file has no ID column', function () {
    importHeadingsCsv($this,
        'Name,Gender,Room Group or Area,Category,Phone,Email,Country',
        'Samuel Debrah,Male,Atobiase,PARTICIPANT,,,GHANA',
        'Samuel Nii Quaye Acquaye,Male,,PARTICIPANT,,,GHANA',
        'Ama Owusu,Female,Kpedze,PARTICIPANT,0201112222,ama@example.com,ghana',
    );

    $people = Participant::where('company_id', $this->company->id)->orderBy('id')->get();
    expect($people->pluck('name')->all())->toBe(['Samuel Debrah', 'Samuel Nii Quaye Acquaye', 'Ama Owusu'])
        ->and($people->pluck('gender')->all())->toBe(['Male', 'Male', 'Female'])
        ->and($people->pluck('country')->unique()->all())->toBe(['Ghana'])
        ->and($people[0]->room_group)->toBe('Atobiase')
        ->and($people[2]->phone)->toBe('201112222')
        ->and($people[2]->email)->toBe('ama@example.com')
        ->and($this->event->registrations()->count())->toBe(3)
        ->and($this->event->registrations()->pluck('category')->unique()->all())->toBe(['PARTICIPANT']);
});

it('ignores an email cell that is not an email address instead of merging people', function () {
    importHeadingsCsv($this,
        'ID,Name,Gender,Room Group,Category,Phone,Email',
        '1,Kofi Mensah,Male,,Participant,,GHANA',
        '2,Yaw Boateng,,,Participant,,GHANA',
    );

    expect(Participant::where('company_id', $this->company->id)->pluck('name')->sort()->values()->all())->toBe(['Kofi Mensah', 'Yaw Boateng'])
        ->and(Participant::where('email', 'GHANA')->orWhere('email', 'ghana')->exists())->toBeFalse();
});

it('keeps the fixed column order for lists with Name in column B, adding Country from its heading', function () {
    importHeadingsCsv($this,
        'S/N,Name,Gender,Room Group,Category,Phone,Email,Country',
        '7,Esi Owusu,Female,Group A,Usher,0241112222,,Nigeria',
    );
    $esi = Participant::sole();
    expect($esi->member_id)->toBe($this->company->id.':event'.$this->event->id.':7')
        ->and($esi->room_group)->toBe('Group A')
        ->and($esi->country)->toBe('Nigeria');

    // A list without headings is read in the fixed order, as before.
    importHeadingsCsv($this, '8,Kwame Asante,Male,,Member,0241113333,');
    expect(Participant::where('name', 'Kwame Asante')->value('member_id'))->toBe($this->company->id.':event'.$this->event->id.':8');
});

it('only takes a first row for headings when it names the Name column and another', function () {
    expect(UsersImport::headingColumns(['Name', 'Phone', 'Country']))->toBe(['name' => 0, 'phone' => 1, 'country' => 2])
        ->and(UsersImport::headingColumns(['1', 'Ama Owusu', 'Female']))->toBeNull()
        ->and(UsersImport::headingColumns(['Name']))->toBeNull()
        ->and(UsersImport::headingColumns(['Full Name', 'Sex', 'Mobile No.', 'E-mail']))->toBe(['name' => 0, 'gender' => 1, 'phone' => 2, 'email' => 3]);
});
