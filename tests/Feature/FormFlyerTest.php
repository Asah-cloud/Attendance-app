<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use App\Support\FlyerPreview;
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

/** PNG bytes of a busy picture (many random coloured dots), which compresses poorly like a photo-heavy flyer. */
function busyPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($i = 0; $i < 6000; $i++) {
        imagefilledellipse($image, random_int(0, $width), random_int(0, $height), 40, 40, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
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

it('gives WhatsApp and phones a small copy of a large flyer and keeps the original for full size', function () {
    // A busy 2000x2500 PNG: well over the few hundred KB WhatsApp will show in a preview.
    $file = tempnam(sys_get_temp_dir(), 'flyer').'.png';
    file_put_contents($file, busyPng(2000, 2500));
    expect(filesize($file))->toBeGreaterThan(300 * 1024);

    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings([
        'flyers' => [new UploadedFile($file, 'quiz.png', 'image/png', null, true)],
    ]))->assertSessionHasNoErrors();

    $original = $this->form->fresh()->flyers()[0];
    $preview = FlyerPreview::pathFor($original);
    Storage::disk('public')->assertExists($preview);
    $size = getimagesize(Storage::disk('public')->path($preview));
    expect(Storage::disk('public')->size($preview))->toBeLessThanOrEqual(290 * 1024)
        ->and(max($size[0], $size[1]))->toBeLessThanOrEqual(1200)
        ->and($size['mime'])->toBe('image/jpeg');

    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertSee('<meta property="og:image" content="'.url(Storage::url($preview)).'">', false)
        ->assertSee('<img src="'.Storage::url($preview).'" alt="AWC TOURS 2026 flyer"', false)
        ->assertSee('<a href="'.Storage::url($original).'" target="_blank"', false);

    // Removing the flyer removes its copy too
    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['remove_flyers' => [$original]]));
    Storage::disk('public')->assertMissing([$original, $preview]);
    unlink($file);
});

it('makes the small copy the first time a flyer uploaded before this change is shown', function () {
    Storage::disk('public')->put('form-flyers/old.png', busyPng(1800, 2200));
    $this->form->update(['flyer_paths' => ['form-flyers/old.png']]);

    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))->assertOk()
        ->assertSee(Storage::url('form-flyers/previews/old.jpg'), false);
    Storage::disk('public')->assertExists('form-flyers/previews/old.jpg');
});

it('falls back to the site preview image when a form has no flyers', function () {
    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))
        ->assertSee('og-asah-apex-attendance', false)
        ->assertSee('<meta property="og:description" content="Apostolic World Conference 2026">', false);
});

it('shows form pages under the organiser\'s name with only a powered-by line from Asah Apex', function () {
    $this->event->company->update(['logo_path' => 'company-logos/church.png']);

    foreach ([route('forms.show', [$this->event->slug, $this->form->slug]), route('forms.thank-you', [$this->event->slug, $this->form->slug])] as $page) {
        $this->get($page)
            ->assertOk()
            ->assertSee('Flyer Co')
            ->assertSee(Storage::url('company-logos/church.png'), false)
            ->assertSee('Powered by')
            ->assertDontSee('Get started')
            ->assertDontSee('Better events begin with a clearer headcount.');
    }

    $this->get(route('home'))->assertOk()->assertSee('Get started')->assertDontSee('Powered by');
});

it('colours the form page background from its first flyer, with soft colour glows when it has none', function () {
    $page = route('forms.show', [$this->event->slug, $this->form->slug]);
    $this->get($page)->assertSee('bg-violet-400/20', false);

    $this->actingAs($this->manager)->patch(route('events.forms.update', [$this->event, $this->form]), flyerSettings(['flyers' => [UploadedFile::fake()->image('a.jpg')]]));
    $flyer = Storage::url($this->form->fresh()->flyers()[0]);

    foreach ([$page, route('forms.thank-you', [$this->event->slug, $this->form->slug])] as $url) {
        $this->get($url)
            ->assertSee('<img src="'.$flyer.'" alt="" class="absolute inset-0 h-full w-full scale-125 object-cover opacity-90 blur-3xl saturate-150">', false)
            ->assertDontSee('bg-violet-400/20', false);
    }
});
