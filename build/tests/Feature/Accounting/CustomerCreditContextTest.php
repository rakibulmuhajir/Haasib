<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/*
 * CreditSalesEntry.vue (the Daily Close credit-sales row) warns inline when a sale
 * would push a buyer over their credit_limit, without blocking it -- only a blocked
 * buyer is refused (DailyCloseCreditSaleService::prepare, FuelSaleService::createSale;
 * both already covered by CustomerStatementTest at the posting layer). The warning
 * needs credit_limit/current_balance/is_credit_blocked at selection time, and the only
 * place the frontend picker (EntitySearch) gets a customer's data from is
 * CustomerController::search()/recent() -- this proves those two endpoints actually
 * carry that data now, since nothing did before this change.
 */
function creditContextFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Credit Context Co '.str()->random(8),
        'slug' => 'credit-context-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'),
    );
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);

    return compact('owner', 'company', 'ar');
}

test('customer search returns credit_limit, current_balance and is_credit_blocked for a matching buyer', function () {
    $f = creditContextFixture();
    $customer = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-1', 'name' => 'Truck Owner Zafar',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'credit_limit' => 5000, 'is_active' => true,
    ]);
    Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-CC-1',
        'invoice_date' => '2026-09-01', 'due_date' => '2026-09-01', 'status' => 'sent',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 3000, 'total_amount' => 3000, 'paid_amount' => 0, 'balance' => 3000,
    ]);

    $response = $this->actingAs($f['owner'])->getJson("/{$f['company']->slug}/customers/search?q=Zafar");
    $response->assertOk();
    $results = $response->json('results');
    expect($results)->toHaveCount(1);
    expect((float) $results[0]['credit_limit'])->toBe(5000.0)
        ->and((float) $results[0]['current_balance'])->toBe(3000.0)
        ->and($results[0]['is_credit_blocked'])->toBeFalse();
});

test('a blocked buyer is reported as blocked by customer search', function () {
    $f = creditContextFixture();
    Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Blocked Buyer Habib',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'credit_limit' => 1000,
        'is_credit_blocked' => true, 'is_active' => true,
    ]);

    $response = $this->actingAs($f['owner'])->getJson("/{$f['company']->slug}/customers/search?q=Habib");
    $results = $response->json('results');
    expect($results)->toHaveCount(1)
        ->and($results[0]['is_credit_blocked'])->toBeTrue();
});

test('a voided invoice does not count toward a buyer current_balance in search results', function () {
    $f = creditContextFixture();
    $customer = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-3', 'name' => 'Clean Slate Traders',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'credit_limit' => 2000, 'is_active' => true,
    ]);
    Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-CC-2',
        'invoice_date' => '2026-09-01', 'due_date' => '2026-09-01', 'status' => 'void',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 9000, 'total_amount' => 9000, 'paid_amount' => 0, 'balance' => 9000,
    ]);

    $response = $this->actingAs($f['owner'])->getJson("/{$f['company']->slug}/customers/search?q=Clean");
    $results = $response->json('results');
    expect((float) $results[0]['current_balance'])->toBe(0.0);
});
