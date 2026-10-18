<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Extensions\StatementKinds;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Reports\AmanatStatementKind;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An owner of a company with one customer. $fuelStation turns the fuel_station module on.
 */
function statementKindsFixture(bool $fuelStation): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Statement Kinds Test',
        'slug' => 'stmt-kinds-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    enterCompany($company);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if ($fuelStation) {
        $company->enableModule('fuel_station');
    }

    $buyer = Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-1',
        'name' => 'Haji Saab',
        'customer_type' => 'business',
        'base_currency' => 'PKR',
    ]);

    return ['company' => $company, 'user' => $user, 'buyer' => $buyer];
}

test('a general company has no extra statement kinds or links, and amanat is rejected', function () {
    $f = statementKindsFixture(fuelStation: false);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/statements")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/reports/Statement', false)
            ->where('extraKinds', [])
            ->where('kindLinks', [])
            ->missing('options.amanat'));

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/statements?kind=amanat&id=all")
        ->assertSessionHasErrors('kind');
});

test('a fuel company gets the amanat kind and the stock link', function () {
    $f = statementKindsFixture(fuelStation: true);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/statements?kind=amanat&id=all")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/reports/Statement', false)
            ->has('extraKinds', 1)
            ->where('extraKinds.0.key', 'amanat')
            ->where('extraKinds.0.label', 'Amanat')
            ->has('kindLinks', 1)
            ->where('kindLinks.0.key', 'stock')
            ->where('kindLinks.0.label', 'Stock')
            ->where('kindLinks.0.href', fn ($href) => str_starts_with($href, "/{$f['company']->slug}/fuel/reports/stock-statement?start_date="))
            ->where('filters.kind', 'amanat')
            ->where('filters.id', 'all')
            ->has('options.amanat', 0));
});

test('amanat holders list under Amanat, and a holder who is not a credit customer is hidden from Customer', function () {
    $f = statementKindsFixture(fuelStation: true);
    $company = $f['company'];

    $holderOnly = Customer::create([
        'company_id' => $company->id, 'customer_number' => 'AM-1', 'name' => 'Holder Only',
        'customer_type' => 'business', 'base_currency' => 'PKR',
    ]);
    $both = Customer::create([
        'company_id' => $company->id, 'customer_number' => 'AM-2', 'name' => 'Holder And Buyer',
        'customer_type' => 'business', 'base_currency' => 'PKR',
    ]);
    CustomerProfile::create(['company_id' => $company->id, 'customer_id' => $holderOnly->id, 'is_amanat_holder' => true, 'is_credit_customer' => false]);
    CustomerProfile::create(['company_id' => $company->id, 'customer_id' => $both->id, 'is_amanat_holder' => true, 'is_credit_customer' => true]);

    $kind = new AmanatStatementKind;
    expect($kind->options($company)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$holderOnly->id, $both->id])->sort()->values()->all())
        ->and($kind->hiddenCustomerIds($company))->toBe([$holderOnly->id]);

    $this->actingAs($f['user'])->get("/{$company->slug}/reports/statements?kind=customer")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('options.amanat', 2)
            ->where('options.customer', fn ($customers) => collect($customers)->pluck('id')->contains($both->id)
                && collect($customers)->pluck('id')->contains($f['buyer']->id)
                && ! collect($customers)->pluck('id')->contains($holderOnly->id)));

    $this->actingAs($f['user'])->get("/{$company->slug}/reports/statements?kind=amanat&id={$holderOnly->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.money_in', 'Deposited')
            ->where('columns.money_out', 'Paid out')
            ->where('columns.balance', 'We hold')
            ->where('filters.id', $holderOnly->id));
});

test('registering a kind or link under the same key replaces it', function () {
    $f = statementKindsFixture(fuelStation: true);

    try {
        StatementKinds::register(new AmanatStatementKind);
        StatementKinds::register(new AmanatStatementKind);
        StatementKinds::registerLink('stock', 'Stock', fn ($c) => true, fn ($c, $from, $to) => '/first');
        StatementKinds::registerLink('stock', 'Stock', fn ($c) => true, fn ($c, $from, $to) => '/second');

        $kinds = app(StatementKinds::class);
        expect(array_keys($kinds->availableFor($f['company'])))->toBe(['amanat'])
            ->and($kinds->links($f['company'], '2026-09-01', '2026-09-30'))
            ->toBe([['key' => 'stock', 'label' => 'Stock', 'href' => '/second']]);
    } finally {
        // Static registry: put the fuel provider's registrations back for later tests.
        StatementKinds::flush();
        StatementKinds::register(new AmanatStatementKind);
        StatementKinds::registerLink(
            'stock',
            'Stock',
            fn ($c) => $c->hasModule('fuel_station'),
            fn ($c, $from, $to) => "/{$c->slug}/fuel/reports/stock-statement?start_date={$from}&end_date={$to}",
        );
    }
});

test('a holder with an invoice stays on Customer even without the credit flag', function () {
    $f = statementKindsFixture(fuelStation: true);
    $company = $f['company'];
    $owes = Customer::create([
        'company_id' => $company->id, 'customer_number' => 'AM-3', 'name' => 'Holder Who Owes',
        'customer_type' => 'business', 'base_currency' => 'PKR',
    ]);
    CustomerProfile::create(['company_id' => $company->id, 'customer_id' => $owes->id, 'is_amanat_holder' => true, 'is_credit_customer' => false]);
    // An opening balance owed, as the Opening balances page records it: an invoice, no credit flag.
    \App\Modules\Accounting\Models\Invoice::create([
        'company_id' => $company->id, 'customer_id' => $owes->id, 'invoice_number' => 'INV-OPEN-1',
        'invoice_date' => '2026-08-31', 'due_date' => '2026-08-31', 'status' => 'sent',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 7233, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 7233,
        'paid_amount' => 0, 'balance' => 7233, 'base_amount' => 7233,
    ]);

    expect((new AmanatStatementKind)->hiddenCustomerIds($company))->not->toContain($owes->id);
});
