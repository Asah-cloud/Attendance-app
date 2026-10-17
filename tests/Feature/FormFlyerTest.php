<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    Storage::fake('public');

    $company = Company::create(['name' => 'Flyer Co']);
    $this->manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $company->id, 'title' => 'Apostolic World Conference 2026', 'event_date' => now()->addWeek()]);
    $this->form = $this->event->forms()->create(['title' => 'AWC TOURS 2026', 'is_open' => true]);
});

function flyerSettings(array $extra = []): array
{
    return ['title' => 'AWC TOURS 2026', 'is_open' => '1'] + $extra;
}

it('lets a manager add flyers that show above the questions on the public form', function () {
    $this->actingAs($this->manager)
        ->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings([
            'flyers' => [UploadedFile::fake()->image('tours.jpg', 1080, 1350), UploadedFile::fake()->image('prices.png')],
        ]))
        ->assertSessionHasNoErrors();

    $flyers = $this->form->fresh()->flyers();
    expect($flyers)->toHaveCount(2);
    Storage::disk('public')->assertExists($flyers);

    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertOk()
        ->assertSeeInOrder([Storage::url($flyers[0]), Storage::url($flyers[1]), 'Submit'], false)
        ->assertSee('lg:grid-cols-2', false)
        ->assertSee('aria-roledescription="carousel"', false)
        ->assertSee('aria-label="Next flyer"', false);
});

it('keeps the single-column form when there are no flyers', function () {
    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertOk()
        ->assertDontSee('aria-roledescription="carousel"', false)
        ->assertSee('max-w-2xl', false);
});

it('does not show slide controls for a single flyer', function () {
    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['flyers' => [UploadedFile::fake()->image('a.jpg')]]));

    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertSee('aria-roledescription="carousel"', false)
        ->assertDontSee('aria-label="Next flyer"', false);
});

it('removes a chosen flyer and keeps the rest in order', function () {
    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings([
        'flyers' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
    ]));
    [$a, $b, $c] = $this->form->fresh()->flyers();

    $this->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['remove_flyers' => [$b]]))
        ->assertSessionHasNoErrors();

    expect($this->form->fresh()->flyers())->toBe([$a, $c]);
    Storage::disk('public')->assertMissing($b);
});

it('keeps flyers when other settings are saved', function () {
    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['flyers' => [UploadedFile::fake()->image('a.jpg')]]));
    $this->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['description' => 'Pick a tour']));

    expect($this->form->fresh()->flyers())->toHaveCount(1)
        ->and($this->form->fresh()->description)->toBe('Pick a tour');
});

it('refuses more than five flyers, files that are not images, and images over 5 MB', function () {
    $this->actingAs($this->manager);
    $url = route('events.forms.update', [$this->event, $this->form]);

    $this->patch($url, flyerSettings(['flyers' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 6))]))
        ->assertSessionHasErrors(['flyers' => 'A form can show up to 5 flyers. Remove one before adding another.']);
    $this->patch($url, flyerSettings(['flyers' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]]))
        ->assertSessionHasErrors('flyers.0');
    $this->patch($url, flyerSettings(['flyers' => [UploadedFile::fake()->image('huge.jpg')->size(6000)]]))
        ->assertSessionHasErrors(['flyers.0' => 'Each flyer must be an image of 5 MB or less.']);

    expect($this->form->fresh()->flyers())->toBe([]);
    expect(Storage::disk('public')->allFiles('form-flyers'))->toBe([]);
});

it('deletes a form\'s flyers along with the form', function () {
    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['flyers' => [UploadedFile::fake()->image('a.jpg')]]));
    $path = $this->form->fresh()->flyers()[0];

    $this->delete(route('events.forms.destroy', [$this->event, $this->form]));

    Storage::disk('public')->assertMissing($path);
});

it('shows the flyer upload on the form settings page', function () {
    $this->actingAs($this->manager)
        ->get(route('events.forms.edit', [$this->event, $this->form]))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="flyers[]"', false);
});

it('uses the first flyer and the form\'s own details for the shared-link preview', function () {
    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings([
        'description' => 'Choose your tour',
        'flyers' => [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.jpg')],
    ]));
    $first = url(Storage::url($this->form->fresh()->flyers()[0]));

    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertSee('<meta property="og:image" content="'.$first.'">', false)
        ->assertSee('<meta name="twitter:image" content="'.$first.'">', false)
        ->assertSee('<meta property="og:description" content="Choose your tour">', false)
        ->assertDontSee('og-asah-apex-attendance', false);
});

it('falls back to the site preview image when a form has no flyers', function () {
    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertSee('og-asah-apex-attendance', false)
        ->assertSee('<meta property="og:description" content="Apostolic World Conference 2026">', false);
});
