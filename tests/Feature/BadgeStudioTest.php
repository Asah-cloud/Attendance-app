<?php

use App\Jobs\FinalizeBadgeExport;
use App\Jobs\GenerateBadgeExportBatch;
use App\Models\BadgeExport;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\BadgePdfService;
use App\Support\BadgeDesign;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\FontMetrics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function badgeStudioFixture(): array
{
    Role::findOrCreate('manager');
    $company = Company::create(['name' => 'Badge Company']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Badge Event', 'event_date' => now()->addWeek()]);
    unlockAllEventFeatures($event);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return [$event, $manager];
}

function badgeRegistration(Event $event, string $name = 'Alex Morgan', string $category = 'Delegate', string $status = 'confirmed', bool $staff = false)
{
    $participant = Participant::create([
        'company_id' => $event->company_id,
        'name' => $name,
        'category' => $category,
        'is_support_staff' => $staff,
        'staff_code' => $staff ? 'STF-'.fake()->unique()->numerify('######') : null,
        'staff_qr_token' => $staff ? str_repeat('s', 40).fake()->unique()->numerify('########') : null,
    ]);

    return $event->registrations()->create(['participant_id' => $participant->id, 'status' => $status]);
}

it('keeps attendee and event staff badge studios, people, designs, and qr previews separate', function () {
    [$event, $manager] = badgeStudioFixture();
    $attendee = badgeRegistration($event, 'Kojo Attendee', 'Delegate');
    $staff = badgeRegistration($event, 'Ama Staff', 'Usher', 'confirmed', true);

    $this->actingAs($manager)->get(route('events.badges', $event))
        ->assertOk()
        ->assertSee('Attendee badge studio')
        ->assertSee('Kojo Attendee')
        ->assertDontSee('Ama Staff');

    $this->get(route('events.staff-badges', $event))
        ->assertOk()
        ->assertSee('Event staff badge studio')
        ->assertSee('Ama Staff')
        ->assertDontSee('Kojo Attendee');

    $this->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6',
        'badge_design' => 'default',
        'badge_primary_color' => '#AA0000',
    ])->assertSessionHasNoErrors();

    $this->patch(route('events.staff-badges.settings', $event), [
        'badge_size' => 'A5',
        'badge_design' => 'category',
        'badge_primary_color' => '#0000AA',
    ])->assertSessionHasNoErrors();

    $event->refresh();
    expect($event->badge_size)->toBe('A6')
        ->and($event->badge_primary_color)->toBe('#AA0000')
        ->and($event->staff_badge_settings['badge_size'])->toBe('A5')
        ->and($event->staff_badge_settings['badge_primary_color'])->toBe('#0000AA');

    $this->get(route('events.badges.qr', [$event, $attendee]))->assertOk();
    $this->get(route('events.badges.qr', [$event, $staff]))->assertNotFound();
    $this->get(route('events.staff-badges.qr', [$event, $staff]))->assertOk();
    $this->get(route('events.staff-badges.qr', [$event, $attendee]))->assertNotFound();
});

it('saves a full background design and movable fields with optional name initials', function () {
    Storage::fake('public');
    [$event,$manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['name'] = array_replace($fields['name'], ['x' => 9, 'w' => 80, 'size' => 22, 'align' => 'center']);
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_layout' => 'background',
        'badge_fields' => $fields, 'badge_font' => 'DejaVu Serif', 'badge_name_format' => 'initials',
        'badge_image' => UploadedFile::fake()->image('company.png', 1240, 1748),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $event->refresh();
    expect($event->badge_fields['name']['x'])->toBe(9)
        ->and($event->badge_fields['name']['align'])->toBe('center')
        ->and($event->badge_font)->toBe('DejaVu Serif');
    Storage::disk('public')->assertExists($event->badge_image_path);
    $registration = badgeRegistration($event, 'Asah Ayensu Kofi Isaac');
    expect(BadgeDesign::values($event, $registration)['name'])->toBe('Asah A. K. Isaac');
});

it('saves custom text on a badge field and renders it on the badge', function () {
    [$event, $manager] = badgeStudioFixture();
    badgeRegistration($event);
    $fields = BadgeDesign::defaults();
    $fields['custom'] = array_replace($fields['custom'], ['visible' => true, 'text' => 'Sponsored by Acme']);

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $event->refresh();
    expect($event->badge_fields['custom']['text'])->toBe('Sponsored by Acme')
        ->and($event->badge_fields['custom']['visible'])->toBeTrue();

    $this->actingAs($manager)->get(route('events.badges', $event))
        ->assertOk()
        ->assertSee('Sponsored by Acme');
});

it('rejects custom badge text over the length limit', function () {
    [$event, $manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['custom'] = array_replace($fields['custom'], ['text' => str_repeat('a', 201)]);

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertSessionHasErrors('badge_fields.custom.text');
});

it('prints only the bare room name on the badge, without block or floor', function () {
    [$event] = badgeStudioFixture();
    $event->update(['accommodation_published' => true]);
    $site = $event->accommodationSites()->create(['name' => 'Main']);
    $block = $site->blocks()->create(['name' => 'Old Block']);
    $floor = $block->floors()->create(['name' => 'First']);
    $room = $floor->rooms()->create(['name' => 'OB-105', 'capacity' => 2]);
    $registration = badgeRegistration($event);
    $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual']);

    expect(BadgeDesign::values($event, $registration->fresh())['room'])->toBe('OB-105');
});

it('makes the imported area or group available as an optional badge field', function () {
    [$event] = badgeStudioFixture();
    $registration = badgeRegistration($event);
    $registration->update(['room_group' => 'Area 7 / Young Adults']);

    expect(BadgeDesign::LABELS['group'])->toBe('Area / Group')
        ->and(BadgeDesign::defaults()['group']['visible'])->toBeFalse()
        ->and(BadgeDesign::values($event, $registration->fresh())['group'])->toBe('Area 7 / Young Adults');
});

it('offers the attendee\'s country as an optional badge field', function () {
    [$event, $manager] = badgeStudioFixture();
    $chosen = badgeRegistration($event, 'Grace Thompson');
    $chosen->participant->update(['phone' => '0241234567', 'country' => 'United Kingdom']);
    $fromPhone = badgeRegistration($event, 'Chidi Okeke');
    $fromPhone->participant->update(['phone' => '+2348031234567']);
    $unknown = badgeRegistration($event, 'No Details');

    expect(BadgeDesign::LABELS['country'])->toBe('Country')
        ->and(BadgeDesign::defaults()['country']['visible'])->toBeFalse()
        // The country entered wins over the phone's; nothing is printed when neither tells.
        ->and(BadgeDesign::values($event, $chosen->fresh())['country'])->toBe('United Kingdom')
        ->and(BadgeDesign::values($event, $fromPhone->fresh())['country'])->toBe('Nigeria')
        ->and(BadgeDesign::values($event, $unknown->fresh())['country'])->toBe('');

    $fields = BadgeDesign::defaults();
    $fields['country'] = array_replace($fields['country'], ['visible' => true, 'case' => 'upper']);
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($event->fresh()->badge_fields['country']['visible'])->toBeTrue();
    $this->actingAs($manager)->get(route('events.badges', $event))->assertOk()
        ->assertSee('data-field="country"', false)->assertSee('United Kingdom');
    $this->get(route('events.badges.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('lets a manager pick a country and print only those badges', function () {
    [$event, $manager] = badgeStudioFixture();
    $ghana = badgeRegistration($event, 'Kofi Mensah');
    $ghana->participant->update(['phone' => '0241234567']);
    $jamaica = badgeRegistration($event, 'Marcia Brown');
    $jamaica->participant->update(['country' => 'Jamaica']);
    $unknown = badgeRegistration($event, 'No Details');

    $this->actingAs($manager)->get(route('events.badges', $event))->assertOk()
        ->assertSee('<label for="print-country">Country</label>', false)
        ->assertSeeInOrder(['All countries', 'Country not given', 'Ghana', 'Jamaica'])
        ->assertSee('data-name="Marcia Brown" data-category="Delegate" data-gender="Unspecified" data-country="Jamaica"', false)
        ->assertSee('data-name="No Details" data-category="Delegate" data-gender="Unspecified" data-country="Country not given"', false);

    // The studio sends the ticked people of the chosen country; only their badges print.
    $response = $this->post(route('events.badges.pdf', $event), ['attendees' => [$jamaica->id], 'paper' => 'badge']);
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(app(BadgePdfService::class)->registrations($event, ['attendees' => [$jamaica->id]], false)->pluck('participant.name')->all())->toBe(['Marcia Brown']);
});

it('lets a manager search and filter the people to preview and print by gender, category and country', function () {
    [$event, $manager] = badgeStudioFixture();
    $ama = badgeRegistration($event, 'Ama Serwaa', 'Speaker');
    $ama->participant->update(['gender' => 'Female', 'country' => 'Ghana']);
    $kofi = badgeRegistration($event, 'Kofi Mensah');
    $kofi->participant->update(['gender' => 'Male']);

    $this->actingAs($manager)->get(route('events.badges', $event))->assertOk()
        ->assertSee('id="preview-search"', false)
        ->assertSeeInOrder(['id="preview-gender"', 'All genders', 'Female', 'Male', 'id="preview-category"', 'Delegate', 'Speaker', 'id="preview-country"', 'Country not given', 'Ghana'], false)
        ->assertSee('data-gender="Female" data-category="Speaker" data-country="Ghana">Ama Serwaa</option>', false)
        ->assertSee('<label for="print-gender">Gender</label>', false)
        ->assertSee('data-name="Kofi Mensah" data-category="Delegate" data-gender="Male"', false);
});

it('leaves out the preview search when there is no one to choose between', function () {
    [$event, $manager] = badgeStudioFixture();
    badgeRegistration($event, 'Ama Serwaa');

    $this->actingAs($manager)->get(route('events.badges', $event))->assertOk()->assertDontSee('id="preview-search"', false);
});

it('lets a manager select Poppins and prints a working PDF with it', function () {
    [$event, $manager] = badgeStudioFixture();
    badgeRegistration($event);
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_font' => 'Poppins',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($event->fresh()->badge_font)->toBe('Poppins');

    $this->get(route('events.badges', $event))->assertOk()->assertSee('Poppins');
    $this->get(route('events.badges.pdf', $event))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('serves the Poppins font files for the badge studio preview but rejects anything else', function () {
    [$event, $manager] = badgeStudioFixture();

    $this->actingAs($manager)->get(route('events.badges.font', [$event, 'poppins']))
        ->assertOk()->assertHeader('content-type', 'font/ttf');
    $this->get(route('events.badges.font', [$event, 'poppins-Bold']))->assertOk();
    $this->get(route('events.badges.font', [$event, '../../../.env']))->assertNotFound();
});

it('lets a manager give a field a custom colour and bold weight, or leave it to inherit the default', function () {
    [$event, $manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['category'] = array_replace($fields['category'], ['color' => '#FF0000', 'bold' => false]);
    $fields['room'] = array_replace($fields['room'], ['bold' => true]);
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $event->refresh();
    expect($event->badge_fields['category']['color'])->toBe('#FF0000')
        ->and($event->badge_fields['category']['bold'])->toBeFalse()
        ->and($event->badge_fields['room']['bold'])->toBeTrue()
        ->and($event->badge_fields['room']['color'])->toBeNull()
        ->and($event->badge_fields['name']['bold'])->toBeTrue()
        ->and($event->badge_fields['name']['color'])->toBeNull();
});

it('saves text case, a border and a fill per field, and draws them on the badge and in the PDF', function () {
    [$event, $manager] = badgeStudioFixture();
    badgeRegistration($event, 'Ama Serwaa');
    $fields = BadgeDesign::defaults();
    $fields['name'] = array_replace($fields['name'], ['case' => 'upper', 'border' => 'rounded', 'border_color' => '#1D4ED8', 'border_width' => 1, 'fill' => '#FEF3C7']);
    $fields['event'] = array_replace($fields['event'], ['case' => 'title', 'border' => 'square']);

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $saved = $event->fresh()->badge_fields;
    expect(array_intersect_key($saved['name'], array_flip(['case', 'border', 'border_color', 'border_width', 'fill'])))
        ->toBe(['case' => 'upper', 'border' => 'rounded', 'border_color' => '#1D4ED8', 'border_width' => 1, 'fill' => '#FEF3C7'])
        // Untouched fields keep the defaults: as typed, no border, no fill.
        ->and(array_intersect_key($saved['meta'], array_flip(['case', 'border', 'fill'])))->toBe(['case' => 'none', 'border' => 'none', 'fill' => null]);

    expect(BadgeDesign::textStyle('name', $saved['name']))->toBe('text-transform:uppercase;border:1mm solid #1D4ED8;border-radius:2mm;background:#FEF3C7;padding:0.8mm 1.5mm;')
        // A border with no colour of its own takes the text colour.
        ->and(BadgeDesign::textStyle('event', $saved['event']))->toBe('text-transform:capitalize;border:0.5mm solid currentColor;border-radius:0;padding:0.8mm 1.5mm;')
        ->and(BadgeDesign::textStyle('meta', $saved['meta']))->toBe('text-transform:none;')
        // The category keeps its colour unless given a fill; pictures get none of it.
        ->and(BadgeDesign::textStyle('category', $saved['category'], '#0F766E'))->toBe('text-transform:none;background:#0F766E;')
        ->and(BadgeDesign::textStyle('qr', array_replace($saved['qr'], ['border' => 'rounded'])))->toBe('');

    $this->actingAs($manager)->get(route('events.badges', $event))->assertOk()
        ->assertSee('text-transform:uppercase;border:1mm solid #1D4ED8;border-radius:2mm;background:#FEF3C7', false)
        ->assertSee('Make all text UPPER CASE')->assertSee('Rounded');
    $this->get(route('events.badges.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('keeps designs saved before text case and borders existed looking the same', function () {
    [$event, $manager] = badgeStudioFixture();
    $old = collect(BadgeDesign::defaults())->map(fn ($field) => array_diff_key($field, array_flip(['case', 'border', 'border_color', 'border_width', 'fill'])))->all();
    $event->update(['badge_fields' => $old]);

    $fields = BadgeDesign::fields($event->fresh());
    expect($fields['name']['case'])->toBe('none')->and($fields['name']['border'])->toBe('none')
        ->and(BadgeDesign::textStyle('name', $fields['name']))->toBe('text-transform:none;');
});

it('makes one name bigger with the other names on a smaller line, on the badge and in the PDF', function () {
    [$event, $manager] = badgeStudioFixture();
    badgeRegistration($event, 'Adwoa Agyei Mensah');
    $fields = BadgeDesign::defaults();
    $fields['name'] = array_replace($fields['name'], ['size' => 30, 'emphasis' => 'first', 'small_size' => 13]);

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $saved = $event->fresh()->badge_fields['name'];
    expect($saved['emphasis'])->toBe('first')->and((float) $saved['small_size'])->toBe(13.0)
        ->and(BadgeDesign::nameParts('Adwoa Agyei Mensah', $saved))->toBe(['Adwoa', 'Agyei Mensah'])
        ->and(BadgeDesign::nameParts('Adwoa Agyei Mensah', ['emphasis' => 'last']))->toBe(['Mensah', 'Adwoa Agyei'])
        // One-word names and the default print as a single line.
        ->and(BadgeDesign::nameParts('Adwoa', $saved))->toBeNull()
        ->and(BadgeDesign::nameParts('Adwoa Agyei Mensah', BadgeDesign::defaults()['name']))->toBeNull();

    $this->actingAs($manager)->get(route('events.badges', $event))->assertOk()
        ->assertSee('<span class="name-big" style="display:block;line-height:1.1">Adwoa</span>', false)
        ->assertSee('font-size:13pt">Agyei Mensah</span>', false)
        ->assertSee('Last name bigger');
    $this->get(route('events.badges.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('rejects an unknown name emphasis or an out of range smaller name size', function (string $property, mixed $value) {
    [$event, $manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['name'][$property] = $value;

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertSessionHasErrors("badge_fields.name.{$property}");
})->with([['emphasis', 'middle'], ['small_size', 3], ['small_size', 60]]);

it('rejects an unknown text case, border style or border thickness', function (string $property, mixed $value) {
    [$event, $manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['name'][$property] = $value;

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertSessionHasErrors("badge_fields.name.{$property}");
})->with([['case', 'shouting'], ['border', 'dotted'], ['border_width', 5], ['fill', 'yellow'], ['border_color', 'blue']]);

it('rejects an invalid field colour', function () {
    [$event, $manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['name']['color'] = 'not-a-colour';
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertSessionHasErrors('badge_fields.name.color');
});

it('rejects out of bounds fields and an undersized or hidden QR code', function (string $key, array $changes) {
    [$event,$manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields[$key] = array_replace($fields[$key], $changes);
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertSessionHasErrors('badge_fields');
    expect($event->fresh()->badge_fields)->toBeNull();
})->with([
    ['name', ['x' => 90]], ['qr', ['visible' => false]], ['qr', ['w' => 10]],
]);

it('rejects unsupported field names and style values', function () {
    [$event,$manager] = badgeStudioFixture();
    $fields = BadgeDesign::defaults();
    $fields['name']['align'] = 'url(javascript:alert(1))';
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6', 'badge_design' => 'default', 'badge_fields' => $fields,
    ])->assertSessionHasErrors('badge_fields.name.align');
});

it('reuses a company design with an independent copy of the artwork', function () {
    Storage::fake('public');
    [$event,$manager] = badgeStudioFixture();
    $template = Event::create(['company_id' => $event->company_id, 'title' => 'Template', 'event_date' => now(), 'badge_size' => 'A6', 'badge_layout' => 'background', 'badge_fields' => BadgeDesign::defaults(), 'badge_image_path' => 'badge-images/original.png']);
    Storage::disk('public')->put('badge-images/original.png', 'artwork');
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), ['template_id' => $template->id])->assertRedirect()->assertSessionHasNoErrors();
    $event->refresh();
    expect($event->badge_image_path)->not->toBe($template->badge_image_path);
    expect(Storage::disk('public')->get($event->badge_image_path))->toBe('artwork');
    $this->patch(route('events.badges.settings', $event), ['badge_size' => 'A6', 'badge_design' => 'default', 'badge_layout' => 'minimal', 'remove_badge_image' => true])->assertSessionHasNoErrors();
    Storage::disk('public')->assertExists($template->badge_image_path);
});

it('points the PDF at the artwork file once instead of embedding a copy on every badge', function () {
    Storage::fake('public');
    [$event, $manager] = badgeStudioFixture();
    $artwork = UploadedFile::fake()->image('art.jpg', 600, 800)->store('badge-images', 'public');
    $event->update(['badge_size' => 'A6', 'badge_layout' => 'background', 'badge_fields' => BadgeDesign::defaults(), 'badge_image_path' => $artwork]);
    $registrations = collect(['Ama Mensah', 'Kofi Boateng', 'Esi Owusu'])->map(fn ($name) => badgeRegistration($event, $name)->load('participant'));

    $html = view('events.badges-pdf', ['event' => $event, 'registrations' => $registrations, 'categoryColors' => collect(), 'fields' => BadgeDesign::fields($event), 'paper' => 'badge', 'cutGuides' => false])->render();

    expect(substr_count($html, Storage::disk('public')->path($artwork)))->toBe(3)
        ->and($html)->not->toContain(base64_encode(Storage::disk('public')->get($artwork)));
    $this->actingAs($manager)->post(route('events.badges.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('does not allow copying another company template', function () {
    [$event,$manager] = badgeStudioFixture();
    [$other] = badgeStudioFixture();
    $other->update(['badge_fields' => BadgeDesign::defaults()]);
    $this->actingAs($manager)->patch(route('events.badges.settings', $event), ['template_id' => $other->id])->assertNotFound();
});

it('prints only selected confirmed attendees with stable category colours', function () {
    [$event,$manager] = badgeStudioFixture();
    $first = badgeRegistration($event, 'Alpha', 'Alpha');
    $second = badgeRegistration($event, 'Beta', 'Beta');
    $pending = badgeRegistration($event, 'Pending', 'Beta', 'pending');
    [$other] = badgeStudioFixture();
    $foreign = badgeRegistration($other);
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setOption')->with('fontHeightRatio', 1000 / 1164)->andReturnSelf();
    $fontMetrics = Mockery::mock(FontMetrics::class);
    $fontMetrics->shouldReceive('registerFont')->twice();
    $dompdf = Mockery::mock(Dompdf\Dompdf::class);
    $dompdf->shouldReceive('getFontMetrics')->andReturn($fontMetrics);
    $pdf->shouldReceive('getDomPDF')->andReturn($dompdf);
    Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($second) {
        expect($view)->toBe('events.badges-pdf');
        expect($data['registrations']->pluck('id')->all())->toBe([$second->id]);
        expect($data['categoryColors']['Beta'])->toBe('#0F766E');
        expect($data['paper'])->toBe('a4');

        return true;
    })->andReturn($pdf);
    $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
    $pdf->shouldReceive('download')->andReturn(response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));
    $this->actingAs($manager)->postJson(route('events.badges.pdf', $event), ['attendees' => [$second->id, $pending->id, $foreign->id], 'paper' => 'a4'])->assertOk();
});

it('returns one sample and rejects empty or invalid print selections', function () {
    [$event,$manager] = badgeStudioFixture();
    badgeRegistration($event);
    badgeRegistration($event, 'Second Person');
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setOption')->with('fontHeightRatio', 1000 / 1164)->andReturnSelf();
    $fontMetrics = Mockery::mock(FontMetrics::class);
    $fontMetrics->shouldReceive('registerFont')->twice();
    $dompdf = Mockery::mock(Dompdf\Dompdf::class);
    $dompdf->shouldReceive('getFontMetrics')->andReturn($fontMetrics);
    $pdf->shouldReceive('getDomPDF')->andReturn($dompdf);
    Pdf::shouldReceive('loadView')->once()->withArgs(fn ($view, $data) => $data['registrations']->count() === 1)->andReturn($pdf);
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('download')->andReturn(response('%PDF-1.4'));
    $this->actingAs($manager)->postJson(route('events.badges.pdf', $event), ['sample' => true])->assertOk();
    $this->postJson(route('events.badges.pdf', $event), ['attendees' => []])->assertUnprocessable();
    $this->postJson(route('events.badges.pdf', $event), ['category' => 'Missing'])->assertUnprocessable();
});

it('serves QR previews only for confirmed attendees of the authorized event', function () {
    [$event,$manager] = badgeStudioFixture();
    $registration = badgeRegistration($event);
    [$other] = badgeStudioFixture();
    $foreign = badgeRegistration($other);
    $pending = badgeRegistration($event, 'Pending', 'Delegate', 'pending');
    $this->actingAs($manager)->get(route('events.badges.qr', [$event, $registration]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('events.badges.qr', [$event, $foreign]))->assertNotFound();
    $this->get(route('events.badges.qr', [$event, $pending]))->assertNotFound();
    $this->get(route('events.badges.qr', [$other, $foreign]))->assertForbidden();
});

it('queues large badge exports in bounded batches and protects their progress', function () {
    Bus::fake();
    [$event, $manager] = badgeStudioFixture();
    $ids = collect(range(1, 101))->map(fn ($number) => badgeRegistration($event, "Person {$number}")->id)->all();

    $response = $this->actingAs($manager)->postJson(route('events.badges.exports.store', $event), [
        'attendees' => $ids,
        'paper' => 'a4',
        'cut_guides' => true,
    ])->assertAccepted();

    $export = BadgeExport::firstOrFail();
    expect($export->total_batches)->toBe(2)
        ->and($export->options['paper'])->toBe('a4');
    Bus::assertChained([
        GenerateBadgeExportBatch::class,
        GenerateBadgeExportBatch::class,
        FinalizeBadgeExport::class,
    ]);
    expect((new GenerateBadgeExportBatch($export->id, [$ids[0]], 1))->queue)->toBe('badges')
        ->and((new FinalizeBadgeExport($export->id))->queue)->toBe('badges');
    $this->getJson($response->json('status_url'))->assertOk()->assertJson(['status' => 'queued', 'completed' => 0, 'total' => 2]);

    [, $otherManager] = badgeStudioFixture();
    $this->actingAs($otherManager)->getJson(route('badge-exports.show', $export))->assertForbidden();
});
