<?php

use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function planManagementAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('lists the seeded plans and lets an admin create a new one', function () {
    $admin = planManagementAdmin();

    $this->actingAs($admin)
        ->get(route('pricing.plans.index'))
        ->assertOk()
        ->assertSee('Starter')
        ->assertSee('Business')
        ->assertSee('Enterprise');

    $this->actingAs($admin)
        ->post(route('pricing.plans.store'), [
            'name' => 'Nonprofit',
            'price' => '49.50',
            'event_limit' => 8,
            'participant_limit' => 1000,
            'description' => 'Discounted plan for registered nonprofits.',
            'features' => "Everything in Starter\nDiscounted per-attendee rate",
            'featured' => '0',
        ])
        ->assertRedirect(route('pricing.plans.index'));

    $plan = Plan::where('name', 'Nonprofit')->firstOrFail();
    expect($plan->key)->toBe('nonprofit')
        ->and($plan->price_minor)->toBe(4950)
        ->and($plan->event_limit)->toBe(8)
        ->and($plan->features)->toBe(['Everything in Starter', 'Discounted per-attendee rate']);

    $this->get(route('pricing'))->assertOk()->assertSee('Nonprofit')->assertSee('GHS 50');
});

it('lets an admin edit a plan\'s price, limits, and features without changing its key', function () {
    $admin = planManagementAdmin();
    $plan = Plan::where('key', 'starter')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('pricing.plans.update', $plan), [
            'name' => 'Starter',
            'price' => '120.00',
            'event_limit' => 4,
            'participant_limit' => 600,
            'description' => $plan->description,
            'features' => "Updated feature one\nUpdated feature two",
            'featured' => '0',
        ])
        ->assertRedirect(route('pricing.plans.index'));

    $plan->refresh();
    expect($plan->key)->toBe('starter')
        ->and($plan->price_minor)->toBe(12000)
        ->and($plan->event_limit)->toBe(4)
        ->and($plan->features)->toBe(['Updated feature one', 'Updated feature two']);
});

it('prevents deleting a plan that a company is still assigned to, but allows it once the company moves off it', function () {
    $admin = planManagementAdmin();
    $plan = Plan::where('key', 'enterprise')->firstOrFail();
    $company = Company::create(['name' => 'Enterprise Co', 'plan_key' => 'enterprise']);

    $this->actingAs($admin)
        ->delete(route('pricing.plans.destroy', $plan))
        ->assertRedirect();

    expect(Plan::find($plan->id))->not->toBeNull();

    $company->update(['plan_key' => 'starter']);

    $this->actingAs($admin)
        ->delete(route('pricing.plans.destroy', $plan))
        ->assertRedirect(route('pricing.plans.index'));

    expect(Plan::find($plan->id))->toBeNull();
});

it('deletes a plan\'s per-plan attendee pricing tiers along with it', function () {
    $admin = planManagementAdmin();
    $plan = Plan::where('key', 'business')->firstOrFail();
    AttendeePricingTier::create(['scope_type' => 'plan', 'plan_key' => 'business', 'band_from' => 0, 'band_to' => null, 'rate_minor' => 300]);

    $this->actingAs($admin)->delete(route('pricing.plans.destroy', $plan))->assertRedirect();

    $this->assertDatabaseMissing('attendee_pricing_tiers', ['scope_type' => 'plan', 'plan_key' => 'business']);
});

it('prevents a manager from managing plans', function () {
    $company = Company::create(['name' => 'One']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->get(route('pricing.plans.index'))->assertForbidden();
});
