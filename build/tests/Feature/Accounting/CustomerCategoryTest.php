<?php

use App\Facades\CompanyContext;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerCategory;
use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\FormulaEvaluator;
use App\Modules\FuelStation\Services\CustomerPeriodSummaryService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../FuelStation/CreditCloseFixtures.php';

function custCatBus(array $f, string $action, array $params): array
{
    CompanyContext::setContext($f['company']);

    return app(CommandBus::class)->dispatch($action, $params, null, true);
}

/** Make the fixture's user a signed-in owner member of its company, so its pages open. */
function custCatPageUser(array $f): User
{
    $user = $f['user'];
    $company = $f['company'];
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);
    $company->enableModule('fuel_station');

    return $user;
}

test('a category can be added, renamed and deleted, and names are unique per company', function () {
    $f = creditCloseFixture();

    $made = custCatBus($f, 'customer_category.create', ['name' => 'Transporters']);
    $id = $made['data']['id'];
    expect(CustomerCategory::where('company_id', $f['company']->id)->pluck('name')->all())->toBe(['Transporters']);

    expect(fn () => custCatBus($f, 'customer_category.create', ['name' => 'transporters']))->toThrow(ValidationException::class);

    custCatBus($f, 'customer_category.create', ['name' => 'Government']);
    expect(fn () => custCatBus($f, 'customer_category.update', ['id' => $id, 'name' => 'GOVERNMENT']))->toThrow(ValidationException::class);

    custCatBus($f, 'customer_category.update', ['id' => $id, 'name' => 'Trucking']);
    expect(CustomerCategory::find($id)->name)->toBe('Trucking');

    custCatBus($f, 'customer_category.delete', ['id' => $id]);
    expect(CustomerCategory::find($id))->toBeNull();
});

test('a category in use cannot be deleted', function () {
    $f = creditCloseFixture();
    $id = custCatBus($f, 'customer_category.create', ['name' => 'Transporters'])['data']['id'];
    $f['customer']->update(['category_id' => $id]);

    expect(fn () => custCatBus($f, 'customer_category.delete', ['id' => $id]))->toThrow(ValidationException::class, 'In use by 1 customer.');
    expect(CustomerCategory::find($id))->not->toBeNull();

    // Free again once the customer is out of it.
    $f['customer']->update(['category_id' => null]);
    custCatBus($f, 'customer_category.delete', ['id' => $id]);
    expect(CustomerCategory::find($id))->toBeNull();
});

test('a customer is put in a category when created or updated, and only one of its own company', function () {
    $f = creditCloseFixture();
    $id = custCatBus($f, 'customer_category.create', ['name' => 'Transporters'])['data']['id'];

    $created = custCatBus($f, 'customer.create', ['name' => 'Haulage Ltd', 'category_id' => $id]);
    expect(Customer::find($created['data']['id'])->category_id)->toBe($id);

    custCatBus($f, 'customer.update', ['id' => $f['customer']->id, 'category_id' => $id]);
    expect($f['customer']->fresh()->category_id)->toBe($id)
        ->and($f['customer']->fresh()->category->name)->toBe('Transporters');

    custCatBus($f, 'customer.update', ['id' => $f['customer']->id, 'category_id' => null]);
    expect($f['customer']->fresh()->category_id)->toBeNull();

    expect(fn () => custCatBus($f, 'customer.update', ['id' => $f['customer']->id, 'category_id' => (string) str()->uuid()]))
        ->toThrow(ValidationException::class);
});

test('the fuel customers list filters by category and shows each customer\'s category', function () {
    $f = creditCloseFixture();
    $id = custCatBus($f, 'customer_category.create', ['name' => 'Transporters'])['data']['id'];
    $f['customer']->update(['category_id' => $id]);
    Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Walk-in regular', 'base_currency' => 'PKR', 'is_active' => true]);
    $user = custCatPageUser($f);

    $this->actingAs($user)->get("/{$f['company']->slug}/fuel/credit-customers")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers', 2)
            ->has('categories', 1));

    $this->actingAs($user)->get("/{$f['company']->slug}/fuel/credit-customers?category_id={$id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers', 1)
            ->where('customers.0.name', 'Truck owner')
            ->where('customers.0.category', 'Transporters')
            ->where('filters.category_id', $id));
});

test('the calculator reads a customer category as the sum of its customers', function () {
    $f = creditCloseFixture();
    creditClosePost($f);
    $cid = $f['company']->id;
    $id = custCatBus($f, 'customer_category.create', ['name' => 'Transporters'])['data']['id'];
    $second = Customer::create(['company_id' => $cid, 'customer_number' => 'C-2', 'name' => 'Second', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);
    $f['customer']->update(['category_id' => $id]);
    $second->update(['category_id' => $id]);
    $outside = Customer::create(['company_id' => $cid, 'customer_number' => 'C-3', 'name' => 'Outside', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);

    $eval = fn (array $node) => app(FormulaEvaluator::class)->evaluate(new CalculatorContext($cid, $f['company']->slug), $node);
    $value = fn (string $metric, array $collection, array $when) => ['type' => 'value', 'metric' => $metric, 'collection' => $collection, 'when' => $when];
    $category = ['type' => 'customer_category', 'id' => $id];
    $range = ['from' => '2026-09-01', 'to' => '2026-09-30'];
    $summary = fn (Customer $c, string $from, string $to) => app(CustomerPeriodSummaryService::class)->run($cid, $c->id, $from, $to, $f['company']->slug)['money'];

    $bought = $eval($value('customer_bought', $category, $range));
    expect($bought['result'])->toBe(round((float) ($summary($f['customer'], '2026-09-01', '2026-09-30')['bought'] + $summary($second, '2026-09-01', '2026-09-30')['bought']), 4))
        ->and($bought['result'])->toBeGreaterThan(0)
        ->and($bought['unit'])->toBe('Rs');

    $owed = $eval($value('customer_owed', $category, ['on' => '2026-09-30']));
    expect($owed['result'])->toBe((float) $summary($f['customer'], '2026-09-30', '2026-09-30')['closing'] + (float) $summary($second, '2026-09-30', '2026-09-30')['closing'])
        ->and($owed['result'])->toBeGreaterThan(0)
        ->and($owed['parts'][0]['label'])->toContain('Transporters');

    // Nobody in the category: no figure to add, a note rather than a wrong zero.
    $empty = custCatBus($f, 'customer_category.create', ['name' => 'Empty'])['data']['id'];
    expect($eval($value('customer_paid', ['type' => 'customer_category', 'id' => $empty], $range))['result'])->toBe(0.0);

    // A product metric cannot be read for a customer category.
    expect(fn () => $eval($value('sales', $category, $range)))->toThrow(\InvalidArgumentException::class);
});
