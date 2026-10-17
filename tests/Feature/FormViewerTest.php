<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use App\Notifications\FormResponsesShared;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher', 'form_viewer'] as $role) {
        Role::findOrCreate($role);
    }
    Notification::fake();

    $this->company = Company::create(['name' => 'Viewer Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Apostolic World Conference 2026', 'event_date' => now()->addWeek()]);
    $this->form = $this->event->forms()->create(['title' => 'AWC TOURS 2026', 'is_open' => true]);
    $this->form->fields()->create(['field_key' => 'tour', 'label' => 'Which tour?', 'field_type' => 'text', 'display_order' => 1, 'is_active' => true]);
    $this->form->responses()->create(['answers' => ['tour' => 'Accra city tour']]);
    $this->otherForm = $this->event->forms()->create(['title' => 'Feedback', 'is_open' => true]);
});

function shareWith(string $email, ?string $name = null)
{
    return test()->actingAs(test()->manager)->post(route('events.forms.viewers.store', [test()->event, test()->form]), ['viewer_email' => $email, 'viewer_name' => $name]);
}

it('creates a responses-only account for a new email and emails a temporary password', function () {
    shareWith('Ama@Example.com', 'Ama Owusu')->assertSessionHas('success');

    $viewer = User::where('email', 'ama@example.com')->firstOrFail();
    expect($viewer->name)->toBe('Ama Owusu')
        ->and($viewer->isFormViewerOnly())->toBeTrue()
        ->and($viewer->must_change_password)->toBeTrue()
        ->and($this->form->viewers()->whereKey($viewer->id)->exists())->toBeTrue();
    Notification::assertSentTo($viewer, FormResponsesShared::class, fn ($n) => $n->temporaryPassword !== null && $n->form->is($this->form));
});

it('gives an existing user access without a new password', function () {
    $existing = User::factory()->create(['email' => 'kofi@example.com']);

    shareWith('kofi@example.com');

    expect($this->form->viewers()->whereKey($existing->id)->exists())->toBeTrue()
        ->and(User::where('email', 'kofi@example.com')->count())->toBe(1);
    Notification::assertSentTo($existing, FormResponsesShared::class, fn ($n) => $n->temporaryPassword === null);
});

it('lets a viewer see and export only the forms shared with them', function () {
    shareWith('ama@example.com');
    $viewer = User::where('email', 'ama@example.com')->firstOrFail();
    $viewer->update(['must_change_password' => false]);
    $this->actingAs($viewer);

    $this->get(route('shared-forms.index'))->assertOk()->assertSee('AWC TOURS 2026')->assertDontSee('Feedback');
    $this->get(route('shared-forms.show', $this->form))->assertOk()->assertSee('Accra city tour')
        ->assertSee(route('shared-forms.export', $this->form), false)
        ->assertDontSee(route('events.forms.edit', [$this->event, $this->form]), false);
    $this->get(route('shared-forms.export', $this->form))->assertOk()->assertDownload('awc-tours-2026-responses.xlsx');
    $this->get(route('shared-forms.pdf', $this->form))->assertOk()->assertDownload('awc-tours-2026-responses.pdf');

    $this->get(route('shared-forms.show', $this->otherForm))->assertNotFound();
    $this->get(route('shared-forms.export', $this->otherForm))->assertNotFound();
});

it('keeps a viewer out of everything else in the system', function () {
    shareWith('ama@example.com');
    $viewer = User::where('email', 'ama@example.com')->firstOrFail();
    $viewer->update(['must_change_password' => false]);
    $this->actingAs($viewer);

    foreach ([route('dashboard'), route('events.index'), route('events.forms.index', $this->event), route('events.forms.responses', [$this->event, $this->form]), route('events.attendance', $this->event)] as $page) {
        $this->get($page)->assertRedirect(route('shared-forms.index'));
    }
    $this->delete(route('events.forms.destroy', [$this->event, $this->form]))->assertForbidden();
    $this->post(route('events.forms.viewers.store', [$this->event, $this->form]), ['viewer_email' => 'x@example.com'])->assertForbidden();
    expect($this->form->fresh())->not->toBeNull();

    $this->get(route('profile.edit'))->assertOk();
    $this->get(route('forms.show', [$this->event->slug, $this->form->slug]))->assertOk();
});

it('stops access as soon as the manager removes it', function () {
    shareWith('ama@example.com');
    $viewer = User::where('email', 'ama@example.com')->firstOrFail();
    $viewer->update(['must_change_password' => false]);

    $this->delete(route('events.forms.viewers.destroy', [$this->event, $this->form, $viewer]))->assertSessionHas('success');

    $this->actingAs($viewer)->get(route('shared-forms.show', $this->form))->assertNotFound();
    $this->get(route('shared-forms.index'))->assertOk()->assertSee('No forms are shared with you right now.');
});

it('sends a new viewer to choose a password first', function () {
    shareWith('ama@example.com');
    $viewer = User::where('email', 'ama@example.com')->firstOrFail();

    $this->actingAs($viewer)->get(route('shared-forms.index'))->assertRedirect(route('password.force.edit'));
});

it('leaves managers\' access unchanged and keeps other companies\' managers out', function () {
    $this->actingAs($this->manager)->get(route('events.forms.edit', [$this->event, $this->form]))
        ->assertOk()->assertSee('Who can see responses');
    $this->get(route('shared-forms.show', $this->form))->assertOk();

    $outsider = User::factory()->create(['company_id' => Company::create(['name' => 'Other'])->id, 'role' => 'manager']);
    $outsider->assignRole('manager');
    $this->actingAs($outsider)->get(route('shared-forms.show', $this->form))->assertNotFound();
    $this->post(route('events.forms.viewers.store', [$this->event, $this->form]), ['viewer_email' => 'x@example.com'])->assertForbidden();
});
