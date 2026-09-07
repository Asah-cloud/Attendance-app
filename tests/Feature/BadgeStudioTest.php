<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Support\BadgeDesign;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function badgeStudioFixture(): array
{
    Role::findOrCreate('manager');
    $company = Company::create(['name' => 'Badge Company']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Badge Event', 'event_date' => now()->addWeek()]);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return [$event, $manager];
}

function badgeRegistration(Event $event, string $name = 'Alex Morgan', string $category = 'Delegate', string $status = 'confirmed')
{
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => $name, 'category' => $category]);

    return $event->registrations()->create(['participant_id' => $participant->id, 'status' => $status]);
}

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
