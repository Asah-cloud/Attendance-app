<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventSlugRedirect;

beforeEach(function () {
    $this->company = Company::create(['name' => 'Slug Co']);
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Book Launch', 'event_date' => now()->addWeek(), 'registration_enabled' => true]);
});

it('renames an event\'s link to match its new title and keeps the old one as a redirect', function () {
    $this->event->update(['title' => 'APOSTOLIC WORLD CONFERENCE 2026']);

    expect($this->event->fresh()->slug)->toBe('apostolic-world-conference-2026')
        ->and(EventSlugRedirect::where('slug', 'book-launch')->value('event_id'))->toBe($this->event->id);
});

it('leaves the link alone when other event details change', function () {
    $this->event->update(['location' => 'Accra']);

    expect($this->event->fresh()->slug)->toBe('book-launch')
        ->and(EventSlugRedirect::count())->toBe(0);
});

it('sends an old form link to the same form under the new event name', function () {
    $form = $this->event->forms()->create(['title' => 'AWC TOURS 2026', 'is_open' => true]);
    $this->event->update(['title' => 'APOSTOLIC WORLD CONFERENCE 2026']);

    $this->get('/forms/book-launch/awc-tours-2026?ref=qr')
        ->assertRedirect(url('/forms/apostolic-world-conference-2026/awc-tours-2026?ref=qr'))
        ->assertStatus(301);
    $this->get(route('forms.show', ['apostolic-world-conference-2026', $form->slug]))
        ->assertOk()
        ->assertSee('AWC TOURS 2026');
});

it('redirects old registration links and still accepts a form posted to the old link', function () {
    $this->event->update(['title' => 'APOSTOLIC WORLD CONFERENCE 2026']);

    $this->get('/events/book-launch/register')
        ->assertRedirect(url('/events/apostolic-world-conference-2026/register'));
    $this->post('/events/book-launch/register', [])->assertStatus(302)->assertSessionHasErrors();
    $this->get('/events/never-existed/register')->assertNotFound();
});

it('never gives a new event a link name another event used before a rename', function () {
    $this->event->update(['title' => 'Renamed Event']);
    $newcomer = Event::create(['company_id' => $this->company->id, 'title' => 'Book Launch', 'event_date' => now()]);

    expect($newcomer->slug)->toBe('book-launch-2');
    $this->get('/events/book-launch/register')
        ->assertRedirect(url('/events/renamed-event/register'));
});

it('takes back its own old link name when renamed back', function () {
    $this->event->update(['title' => 'Renamed Event']);
    $this->event->update(['title' => 'Book Launch']);

    expect($this->event->fresh()->slug)->toBe('book-launch')
        ->and(EventSlugRedirect::pluck('slug')->all())->toBe(['renamed-event']);
});
