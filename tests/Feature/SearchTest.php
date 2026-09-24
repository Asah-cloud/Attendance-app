<?php

use App\Models\Company;
use App\Models\Participant;
use App\Models\User;
use App\Support\Search;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function searchNames(?string $term, array $columns = ['name'], array $phoneColumns = []): array
{
    return Search::apply(Participant::query(), $term, $columns, $phoneColumns)->orderBy('name')->pluck('name')->all();
}

function searchPeople(Company $company): void
{
    foreach ([
        ['John Mensah', 'john@example.com', '024 123 4567'],
        ['Ama Owusu', 'ama@example.com', '0551234567'],
        ['100%_Fun Group', null, null],
        ['Kofi Boateng', null, '+233 20 555 0000'],
    ] as [$name, $email, $phone]) {
        Participant::create(['company_id' => $company->id, 'name' => $name, 'email' => $email, 'phone' => $phone]);
    }
}

it('matches regardless of letter case', function () {
    searchPeople(Company::create(['name' => 'Search Co']));

    expect(searchNames('JOHN'))->toBe(['John Mensah'])
        ->and(searchNames('john'))->toBe(['John Mensah'])
        ->and(searchNames('mEnSaH'))->toBe(['John Mensah']);
});

it('requires every word but accepts them in any order and with stray spaces', function () {
    searchPeople(Company::create(['name' => 'Search Co']));

    expect(searchNames('mensah john'))->toBe(['John Mensah'])
        ->and(searchNames('  john   mensah  '))->toBe(['John Mensah'])
        ->and(searchNames('john owusu'))->toBe([]);
});

it('returns everything for an empty search and treats percent and underscore literally', function () {
    searchPeople(Company::create(['name' => 'Search Co']));

    expect(searchNames(''))->toHaveCount(4)
        ->and(searchNames('   '))->toHaveCount(4)
        ->and(searchNames('%'))->toBe(['100%_Fun Group'])
        ->and(searchNames('_'))->toBe(['100%_Fun Group'])
        ->and(searchNames('0%_f'))->toBe(['100%_Fun Group'])
        ->and(searchNames('\\'))->toBe([]);
});

it('searches several columns and finds phone numbers however they were typed or stored', function () {
    searchPeople(Company::create(['name' => 'Search Co']));
    $columns = ['name', 'email'];

    expect(searchNames('ama@example', $columns))->toBe(['Ama Owusu'])
        ->and(searchNames('0241234567', $columns, ['phone']))->toBe(['John Mensah'])
        ->and(searchNames('241234567', $columns, ['phone']))->toBe(['John Mensah'])
        ->and(searchNames('024-123-4567', $columns, ['phone']))->toBe(['John Mensah'])
        ->and(searchNames('233205550000', $columns, ['phone']))->toBe(['Kofi Boateng'])
        ->and(searchNames('0205550000', $columns, ['phone']))->toBe(['Kofi Boateng']);
});

it('ignores absurdly long searches instead of erroring', function () {
    searchPeople(Company::create(['name' => 'Search Co']));

    expect(searchNames(str_repeat('a ', 500)))->toBeArray();
    expect(Search::words(str_repeat('word ', 50)))->toHaveCount(6);
});

it('finds duplicate-merge candidates whatever case the manager types', function () {
    $company = Company::create(['name' => 'Merge Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    Participant::create(['company_id' => $company->id, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0201112222']);

    $this->actingAs($manager)->get(route('participants.duplicates.index', ['q' => 'jane']))
        ->assertOk()->assertSee('Jane Doe');
    $this->actingAs($manager)->get(route('participants.duplicates.index', ['q' => 'DOE JANE']))
        ->assertOk()->assertSee('Jane Doe');
    $this->actingAs($manager)->get(route('participants.duplicates.index', ['q' => '0201112222']))
        ->assertOk()->assertSee('Jane Doe');
});

it('finds companies on the pricing page whatever case is typed', function () {
    Company::create(['name' => 'Acme Ministries']);
    Company::create(['name' => 'Other Church']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('pricing.companies.index', ['search' => 'ACME']))
        ->assertOk()->assertSee('Acme Ministries')->assertDontSee('Other Church');
});
