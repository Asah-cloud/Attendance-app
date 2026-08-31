<?php

use App\Models\Company;
use App\Models\Event;

it('publishes a robots.txt that points to the sitemap and allows crawling everything', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
        ->assertSee('Allow: /', false)
        ->assertDontSee('Disallow', false);
});

it('publishes a sitemap listing only the marketing pages, each with a lastmod date', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(url('/').'</loc>', false)
        ->assertSee(url('/pricing').'</loc>', false)
        ->assertSee('<lastmod>', false);
});

it('lets search engines index the marketing pages with page-specific titles and descriptions', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('noindex', false)
        ->assertSee('<title>Asah Apex Attendance — Attendance, made effortless</title>', false)
        ->assertSee('og:title', false);

    $this->get(route('pricing'))
        ->assertOk()
        ->assertDontSee('noindex', false)
        ->assertSee('<title>Pricing — Asah Apex Attendance</title>', false)
        ->assertSee('property="og:title" content="Pricing', false);
});

it('blocks search engines from indexing a public event registration page', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Annual Meetup',
        'event_date' => now()->addWeek(),
        'registration_enabled' => true,
    ]);

    $this->get(route('events.register', $event))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});

it('blocks search engines from indexing auth pages', function () {
    $this->get(route('login'))->assertOk()->assertSee('noindex, nofollow', false);
});

it('blocks search engines from indexing the authenticated app', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = \App\Models\User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    \Spatie\Permission\Models\Role::findOrCreate('manager');
    $manager->assignRole('manager');

    $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertSee('noindex, nofollow', false);
});
