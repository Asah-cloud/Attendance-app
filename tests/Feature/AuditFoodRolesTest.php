<?php

use App\Models\AuditApproval;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher', 'audit_head', 'audit_staff'] as $role) {
        Role::findOrCreate($role);
    }
    Notification::fake();
    $this->company = Company::create(['name' => 'Audit Company']);
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Audit Summit', 'event_date' => now(), 'food_registration_required' => true]);
    $this->head = auditUser('audit_head', $this->company, $this->event);
    $this->staff = auditUser('audit_staff', $this->company, $this->event);
    $this->manager = auditUser('manager', $this->company, $this->event);
    $this->meal = $this->event->mealDistributions()->create(['name' => 'Lunch', 'total_portions' => 20]);
    $this->point = $this->event->mealStations()->create(['name' => 'North sharing point']);
    $this->otherPoint = $this->event->mealStations()->create(['name' => 'South private point']);
    $this->point->staff()->attach($this->staff);
    $this->meal->stationAllocations()->create(['meal_station_id' => $this->point->id, 'allocated_portions' => 5]);
    $participant = Participant::create(['company_id' => $this->company->id, 'name' => 'Confirmed Guest', 'email' => 'private@example.com']);
    $this->registration = $this->event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
});

function auditUser(string $role, Company $company, Event $event): User
{
    $user = User::factory()->create(['company_id' => $company->id, 'role' => $role]);
    $user->assignRole($role);
    $user->events()->attach($event);

    return $user;
}

function auditCode($test, string $scope, ?User $approver = null): string
{
    $response = $test->actingAs($approver ?? $test->head)->post(route('audit.approvals.store', $test->event), [
        'user_id' => $test->staff->id, 'scope' => $scope, 'meal_id' => $test->meal->id,
        'registration_code' => $test->registration->registration_code, 'reason' => 'Reviewed serving correction',
    ])->assertRedirect()->assertSessionHas('approval_code');

    return session('approval_code');
}

it('gives audit heads food management without general event management', function () {
    $this->actingAs($this->head)->get(route('events.meals.index', $this->event))->assertOk()->assertSee('Assign Audit Staff');
    $this->post(route('events.meals.store', $this->event), ['name' => 'Dinner', 'total_portions' => 10, 'is_active' => 1])->assertRedirect();
    $this->get(route('events.meals.report', $this->event))->assertOk()->assertDontSee('private@example.com');
    $this->get(route('events.edit', $this->event))->assertForbidden();
    $this->get(route('events.accommodation.index', $this->event))->assertForbidden();
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('billing.index'))->assertForbidden();
    $this->get(route('events.attendance', $this->event))->assertForbidden();
});

it('shows audit workspaces and only assigned sharing point totals', function () {
    $this->actingAs($this->staff)->get(route('dashboard'))->assertOk()->assertSee('Food operations');
    $this->get(route('events.index'))->assertOk()->assertSee('Your assigned events');
    $this->get(route('events.meals.index', $this->event))->assertOk()->assertSee('North sharing point')->assertDontSee('South private point');
    $this->get(route('events.meals.status', [$this->event, $this->meal]))->assertOk()->assertJsonPath('total', 5);
    $this->get(route('events.meals.scanner', [$this->event, $this->meal]).'?q=Confirmed')->assertOk()->assertSee('Confirmed Guest')->assertDontSee('private@example.com')->assertDontSee('South private point');
    $this->get(route('events.meals.report', $this->event))->assertOk();
    $this->get(route('events.meals.report.csv', $this->event))->assertForbidden();
    $this->post(route('events.meals.store', $this->event), [])->assertForbidden();
    $this->post(route('events.meals.stations.staff', [$this->event, $this->point]), ['staff_ids' => [$this->staff->id]])->assertForbidden();
});

it('enforces sharing point assignment and meal eligibility on the server', function () {
    $url = route('events.meals.issue', [$this->event, $this->meal]);
    $payload = ['registration_code' => $this->registration->registration_code];
    $this->actingAs($this->staff)->postJson($url, $payload)->assertUnprocessable();
    $this->postJson($url, $payload + ['meal_station_id' => $this->otherPoint->id])->assertForbidden();
    $this->postJson($url, $payload + ['meal_station_id' => $this->point->id])->assertOk();
    $this->postJson($url, $payload + ['meal_station_id' => $this->point->id])->assertConflict();
    $this->registration->update(['status' => 'pending']);
    $this->postJson($url, $payload + ['meal_station_id' => $this->point->id])->assertUnprocessable();
});

it('blocks unassigned and cross company events even with a stale assignment', function () {
    $other = Event::create(['company_id' => $this->company->id, 'title' => 'Unassigned', 'event_date' => now()]);
    $this->actingAs($this->head)->get(route('events.meals.index', $other))->assertForbidden();
    $foreignCompany = Company::create(['name' => 'Foreign']);
    $foreign = Event::create(['company_id' => $foreignCompany->id, 'title' => 'Foreign event', 'event_date' => now()]);
    $this->head->events()->attach($foreign);
    $this->get(route('events.meals.index', $foreign))->assertForbidden();
    $this->get(route('dashboard'))->assertDontSee('Foreign event');
    $this->get(route('events.meals.scanner', [$this->event, $foreign->mealDistributions()->create(['name' => 'Foreign meal', 'total_portions' => 1])]))->assertNotFound();
});

it('preserves sharing point identity and prevents deleting its history', function () {
    $this->actingAs($this->head)->post(route('events.meals.stations.update', $this->event), ['stations' => "North sharing point\nSouth private point\nNew point"])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->event->mealStations()->where('name', 'North sharing point')->value('id'))->toBe($this->point->id);
    expect($this->point->staff()->count())->toBe(1);
    $this->post(route('events.meals.stations.update', $this->event), ['stations' => 'New point'])->assertSessionHasErrors('stations');
});

it('lets heads assign only audit staff already assigned to their event', function () {
    $unassigned = User::factory()->create(['company_id' => $this->company->id, 'role' => 'audit_staff']);
    $unassigned->assignRole('audit_staff');
    $this->actingAs($this->head)->post(route('events.meals.stations.staff', [$this->event, $this->otherPoint]), ['staff_ids' => [$unassigned->id]])->assertForbidden();
    $this->post(route('events.meals.stations.staff', [$this->event, $this->otherPoint]), ['staff_ids' => [$this->staff->id]])->assertRedirect();
    expect($this->otherPoint->staff()->whereKey($this->staff->id)->exists())->toBeTrue();
});

it('uses approval codes once for the exact staff member and food action', function () {
    $code = auditCode($this, 'override');
    $url = route('events.meals.issue', [$this->event, $this->meal]);
    $payload = ['registration_code' => $this->registration->registration_code, 'meal_station_id' => $this->point->id, 'override' => 1, 'override_reason' => 'Approved extra', 'approval_code' => $code];
    $otherStaff = auditUser('audit_staff', $this->company, $this->event);
    $this->point->staff()->attach($otherStaff);
    $this->actingAs($otherStaff)->postJson($url, $payload)->assertUnprocessable();
    $this->actingAs($this->staff)->postJson($url, $payload)->assertOk();
    $this->postJson($url, $payload)->assertUnprocessable();
    expect(AuditApproval::first()->used_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_approvals', ['approved_by' => $this->head->id, 'user_id' => $this->staff->id, 'reason' => 'Reviewed serving correction']);
});

it('rejects expired codes and revoked approvers', function () {
    $code = auditCode($this, 'override');
    $payload = ['registration_code' => $this->registration->registration_code, 'meal_station_id' => $this->point->id, 'override' => 1, 'override_reason' => 'Approved extra', 'approval_code' => $code];
    $this->travel(11)->minutes();
    $this->actingAs($this->staff)->postJson(route('events.meals.issue', [$this->event, $this->meal]), $payload)->assertUnprocessable();
    $this->travelBack();
    $this->head->events()->detach($this->event);
    $this->postJson(route('events.meals.issue', [$this->event, $this->meal]), $payload)->assertUnprocessable();
});

it('requires approval and a reason to reverse one portion', function () {
    $url = route('events.meals.issue', [$this->event, $this->meal]);
    $this->actingAs($this->staff)->postJson($url, ['registration_code' => $this->registration->registration_code, 'meal_station_id' => $this->point->id])->assertOk();
    $collection = $this->meal->collections()->firstOrFail();
    $reverse = route('events.meals.collections.reverse', [$this->event, $this->meal, $collection]);
    $this->deleteJson($reverse, ['reason' => 'Mistaken scan'])->assertUnprocessable();
    $code = auditCode($this, 'reverse');
    $this->actingAs($this->staff)->delete($reverse, ['reason' => 'Mistaken scan', 'approval_code' => $code])->assertRedirect();
    $this->assertDatabaseMissing('meal_collections', ['id' => $collection->id]);
    $this->assertDatabaseHas('meal_collection_audits', ['action' => 'reversed', 'reason' => 'Mistaken scan', 'performed_by' => $this->staff->id]);
});

it('allows only manager approval for a single read-only attendance summary', function () {
    $this->actingAs($this->head)->get(route('audit.approvals.index', $this->event))->assertOk();
    $this->post(route('audit.approvals.store', $this->event), ['user_id' => $this->staff->id, 'scope' => 'attendance_summary', 'reason' => 'Review'])->assertForbidden();
    $code = auditCode($this, 'attendance_summary', $this->manager);
    $this->actingAs($this->staff)->post(route('audit.attendance-summary', $this->event), ['approval_code' => $code])->assertOk()->assertSee('Confirmed Guest')->assertDontSee('private@example.com');
    $this->postJson(route('audit.attendance-summary', $this->event), ['approval_code' => $code])->assertUnprocessable();
    $this->post(route('events.attendance.store', $this->event), [])->assertForbidden();
    $this->get(route('events.registrations.index', $this->event))->assertForbidden();
});

it('lets managers create and manage audit roles without escalating privileges', function () {
    $this->actingAs($this->manager)->get(route('admin.register-person'))->assertOk()->assertSee('Audit Head')->assertSee('Audit Staff');
    $this->post(route('admin.register.store'), ['name' => 'New Auditor', 'email' => 'new-auditor@example.com', 'role' => 'audit_staff', 'company_id' => $this->company->id])->assertRedirect();
    $user = User::where('email', 'new-auditor@example.com')->firstOrFail();
    expect($user->hasRole('audit_staff'))->toBeTrue();
    expect($user->events()->whereKey($this->event->id)->exists())->toBeTrue();
    $this->get(route('admin.users.edit', $user))->assertOk();
    $this->post(route('admin.register.store'), ['name' => 'Escalation', 'email' => 'escalation@example.com', 'role' => 'admin', 'company_id' => $this->company->id])->assertForbidden();
});

it('does not accept a code for a different meal or participant', function () {
    $code = auditCode($this, 'override');
    $meal = $this->event->mealDistributions()->create(['name' => 'Dinner', 'total_portions' => 10]);
    $meal->stationAllocations()->create(['meal_station_id' => $this->point->id, 'allocated_portions' => 5]);
    $payload = ['registration_code' => $this->registration->registration_code, 'meal_station_id' => $this->point->id, 'override' => 1, 'override_reason' => 'Extra', 'approval_code' => $code];
    $this->actingAs($this->staff)->postJson(route('events.meals.issue', [$this->event, $meal]), $payload)->assertUnprocessable();
    $participant = Participant::create(['company_id' => $this->company->id, 'name' => 'Other Guest']);
    $other = $this->event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);
    $payload['registration_code'] = $other->registration_code;
    $this->postJson(route('events.meals.issue', [$this->event, $this->meal]), $payload)->assertUnprocessable();
    expect(AuditApproval::first()->used_at)->toBeNull();
});

it('scopes collection reports to sharing points and preserves assignments on profile edits', function () {
    $this->actingAs($this->manager)->postJson(route('events.meals.issue', [$this->event, $this->meal]), ['registration_code' => $this->registration->registration_code, 'meal_station_id' => $this->otherPoint->id])->assertOk();
    $this->actingAs($this->staff)->get(route('events.meals.report', $this->event))->assertOk()->assertDontSee('Confirmed Guest');
    $this->actingAs($this->manager)->put(route('admin.users.update', $this->staff), ['name' => 'Updated Auditor', 'email' => $this->staff->email, 'role' => 'audit_staff', 'company_id' => $this->company->id, 'event_ids' => [$this->event->id]])->assertRedirect();
    expect($this->point->staff()->whereKey($this->staff->id)->exists())->toBeTrue();
    $this->put(route('admin.users.update', $this->staff), ['name' => 'Updated Auditor', 'email' => $this->staff->email, 'role' => 'audit_staff', 'company_id' => $this->company->id, 'event_ids' => []])->assertRedirect();
    expect($this->point->staff()->whereKey($this->staff->id)->exists())->toBeFalse();
});
