<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Database\Seeders\Support\CompanyPurger;
use Illuminate\Support\Facades\DB;

/**
 * The purge the demo and scenario seeders use to rebuild their companies.
 *
 * It used to exist twice, and both copies deleted fuel items before tanks. Deleting an item
 * nulls the tank's linked_item_id, which a tank may not have, so the step failed on every fuel
 * company - and both copies swallowed every error, so it failed silently and left the company
 * half removed. Found while removing a real company from production, where a dry run refused
 * to proceed on exactly this.
 */
function purgeableFuelCompany(string $slug): Company
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create(['name' => 'Purge target', 'slug' => $slug, 'owner_id' => $user->id, 'base_currency' => 'PKR']);

    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    Account::create([
        'company_id' => $company->id, 'code' => '1050', 'name' => 'Cash', 'type' => 'asset',
        'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true,
    ]);

    $item = Item::create([
        'company_id' => $company->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product',
        'unit_of_measure' => 'liter', 'currency' => 'PKR',
    ]);

    // A real tank - the kind the old order could not remove, because a tank must keep its item.
    Warehouse::create([
        'company_id' => $company->id, 'code' => 'T1', 'name' => 'Petrol tank',
        'warehouse_type' => 'tank', 'capacity' => 21500, 'linked_item_id' => $item->id,
    ]);

    return $company;
}

function rowsFor(string $companyId): int
{
    return collect(DB::select("
        select c.table_schema || '.' || c.table_name as t
        from information_schema.columns c
        join information_schema.tables tb on tb.table_schema = c.table_schema and tb.table_name = c.table_name
        where c.column_name = 'company_id' and tb.table_type = 'BASE TABLE'
          and c.table_schema not in ('pg_catalog', 'information_schema')
    "))->sum(fn ($r) => DB::table($r->t)->where('company_id', $companyId)->count());
}

test('a fuel company with a tank is removed completely', function () {
    $company = purgeableFuelCompany('demo-purge-'.str()->lower(str()->random(6)));

    $removed = app(CompanyPurger::class)->purge($company->slug);

    expect($removed)->toBeGreaterThan(0)
        ->and(DB::table('auth.companies')->where('id', $company->id)->exists())->toBeFalse()
        ->and(rowsFor($company->id))->toBe(0);
});

test('it leaves every other company alone', function () {
    $target = purgeableFuelCompany('scenario-purge-'.str()->lower(str()->random(6)));
    $bystander = purgeableFuelCompany('demo-keep-'.str()->lower(str()->random(6)));
    $before = rowsFor($bystander->id);

    app(CompanyPurger::class)->purge($target->slug);

    expect(rowsFor($bystander->id))->toBe($before)
        ->and($before)->toBeGreaterThan(0);
});

test('it refuses a company that is not a demo or scenario one, and touches nothing', function () {
    $real = purgeableFuelCompany('real-station-'.str()->lower(str()->random(6)));
    $before = rowsFor($real->id);

    // Removing a real company means removing audit history. That is done deliberately, with a
    // backup and a dry run - never as a side effect of re-seeding.
    expect(fn () => app(CompanyPurger::class)->purge($real->slug))
        ->toThrow(RuntimeException::class, 'Refusing to purge');

    expect(rowsFor($real->id))->toBe($before);
});

test('a slug that does not exist is a no-op', function () {
    expect(app(CompanyPurger::class)->purge('demo-does-not-exist'))->toBe(0);
});

test('protective triggers are back on afterwards', function () {
    $company = purgeableFuelCompany('demo-triggers-'.str()->lower(str()->random(6)));

    app(CompanyPurger::class)->purge($company->slug);

    // The purge switches user triggers off for its own transaction only. Anything still off
    // afterwards would leave audit history deletable for everyone.
    expect(DB::selectOne("select count(*) as n from pg_trigger where not tgisinternal and tgenabled <> 'O'")->n)->toBe(0);
});
