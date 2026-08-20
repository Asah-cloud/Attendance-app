<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\EventRegistrationSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function eventLogoManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('stores an event logo when creating an event', function () {
    Storage::fake('public');
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventLogoManager($company);

    $this->actingAs($manager)->post(route('events.store'), [
        'title' => 'Annual Meetup',
        'event_date' => now()->addWeek()->toDateString(),
        'logo' => UploadedFile::fake()->image('event-logo.png', 300, 300),
    ])->assertRedirect('/events');

    $event = Event::where('title', 'Annual Meetup')->firstOrFail();
    expect($event->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($event->logo_path);
});

it('allows a manager to replace and then remove an event logo', function () {
    Storage::fake('public');
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventLogoManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);

    $this->actingAs($manager)->put(route('events.update', $event), [
        'title' => $event->title,
        'event_date' => $event->event_date->toDateString(),
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertRedirect('/events');

    $event->refresh();
    $firstLogoPath = $event->logo_path;
    expect($firstLogoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstLogoPath);

    $this->actingAs($manager)->put(route('events.update', $event), [
        'title' => $event->title,
        'event_date' => $event->event_date->toDateString(),
        'remove_logo' => '1',
    ])->assertRedirect('/events');

    expect($event->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($firstLogoPath);
});

it('shows the company and event logos on the public registration form and confirmation page', function () {
    Storage::fake('public');
    $company = Company::create(['name' => 'Acme Co', 'logo_path' => 'company-logos/acme.png']);
    Storage::disk('public')->put($company->logo_path, 'fake-image-bytes');
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Annual Meetup',
        'event_date' => now()->addWeek(),
        'registration_enabled' => true,
        'logo_path' => 'event-logos/meetup.png',
    ]);
    Storage::disk('public')->put($event->logo_path, 'fake-image-bytes');

    $companyLogoUrl = Storage::url($company->logo_path);
    $eventLogoUrl = Storage::url($event->logo_path);

    $this->get(route('events.register', $event))
        ->assertOk()
        ->assertSee($companyLogoUrl, false)
        ->assertSee($eventLogoUrl, false);

    Notification::fake();
    $this->post(route('events.register.store', $event), [
        'name' => 'Jane Attendee',
        'email' => 'jane@example.com',
        'phone' => '0201234567',
        'gender' => 'Female',
        'category' => 'Member',
        'consent' => '1',
    ])->assertRedirect();

    $registration = $event->registrations()->firstOrFail();

    $this->get(route('registrations.confirmation', $registration->registration_code))
        ->assertOk()
        ->assertSee($companyLogoUrl, false)
        ->assertSee($eventLogoUrl, false);
});

it('includes the company and event logo URLs in the registration confirmation email', function () {
    Storage::fake('public');
    Notification::fake();

    $company = Company::create(['name' => 'Acme Co', 'logo_path' => 'company-logos/acme.png']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Annual Meetup',
        'event_date' => now()->addWeek(),
        'registration_enabled' => true,
        'logo_path' => 'event-logos/meetup.png',
    ]);

    $this->post(route('events.register.store', $event), [
        'name' => 'Jane Attendee',
        'email' => 'jane@example.com',
        'phone' => '0201234567',
        'gender' => 'Female',
        'category' => 'Member',
        'consent' => '1',
    ])->assertRedirect();

    $participant = Participant::where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo(
        $participant,
        EventRegistrationSubmitted::class,
        function (EventRegistrationSubmitted $notification) use ($participant, $company, $event) {
            $mail = $notification->toMail($participant);

            return $mail->view === 'emails.registration'
                && $mail->viewData['companyLogoUrl'] === url(Storage::url($company->logo_path))
                && $mail->viewData['eventLogoUrl'] === url(Storage::url($event->logo_path));
        }
    );
});

it('shows the company name and event title as visible text in the email, not just as image alt text', function () {
    Storage::fake('public');
    Notification::fake();

    $company = Company::create(['name' => 'Acme Co', 'logo_path' => 'company-logos/acme.png']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Annual Meetup',
        'event_date' => now()->addWeek(),
        'registration_enabled' => true,
        'logo_path' => 'event-logos/meetup.png',
    ]);

    $this->post(route('events.register.store', $event), [
        'name' => 'Jane Attendee',
        'email' => 'jane@example.com',
        'phone' => '0201234567',
        'gender' => 'Female',
        'category' => 'Member',
        'consent' => '1',
    ])->assertRedirect();

    $participant = Participant::where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo(
        $participant,
        EventRegistrationSubmitted::class,
        function (EventRegistrationSubmitted $notification) use ($participant) {
            $mail = $notification->toMail($participant);
            $html = view($mail->view, $mail->viewData)->render();

            // Not just alt="Acme Co" / alt="Annual Meetup" - actual visible text nodes.
            return str_contains($html, '>Acme Co<')
                && str_contains($html, '>Annual Meetup<');
        }
    );
});

it('always sends absolute logo URLs in emails, since relative URLs never load in mail clients', function () {
    Storage::fake('public');
    Notification::fake();

    $company = Company::create(['name' => 'Acme Co', 'logo_path' => 'company-logos/acme.png']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Annual Meetup',
        'event_date' => now()->addWeek(),
        'registration_enabled' => true,
        'logo_path' => 'event-logos/meetup.png',
    ]);

    $this->post(route('events.register.store', $event), [
        'name' => 'Jane Attendee',
        'email' => 'jane@example.com',
        'phone' => '0201234567',
        'gender' => 'Female',
        'category' => 'Member',
        'consent' => '1',
    ])->assertRedirect();

    $participant = Participant::where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo(
        $participant,
        EventRegistrationSubmitted::class,
        function (EventRegistrationSubmitted $notification) use ($participant) {
            $mail = $notification->toMail($participant);

            return str_starts_with($mail->viewData['companyLogoUrl'], 'http')
                && str_starts_with($mail->viewData['eventLogoUrl'], 'http');
        }
    );
});
