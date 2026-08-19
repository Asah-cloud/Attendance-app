<?php

use App\Models\Company;
use App\Models\Event;

it('publishes a robots.txt that points to the sitemap and allows the marketing pages', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap: https://attendance.asah-apex.com/sitemap.xml')
        ->toContain('Allow: /$')
        ->toContain('Allow: /pricing$');
});

it('publishes a sitemap listing the marketing pages', function () {
    $sitemap = file_get_contents(public_path('sitemap.xml'));

    expect($sitemap)->toContain('https://attendance.asah-apex.com/</loc>')
        ->toContain('https://attendance.asah-apex.com/pricing</loc>');
});

it('lets search engines index the marketing pages', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('noindex', false);
    $this->get(route('pricing'))->assertOk()->assertDontSee('noindex', false);
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
