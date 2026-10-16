<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/BillLineTotalFixtures.php';

/**
 * A bill or invoice that totals nothing used to save. The write path is the action, so the rule
 * lives there and keys the error to the field each form shows.
 */
test('a bill totalling zero is rejected on the amount field and nothing is saved', function () {
    $f = billLineTotalFixture();

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id, 'bill_date' => '2026-09-10', 'status' => 'draft',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'payment_terms' => 0,
        'line_items' => [['description' => 'Nothing', 'quantity' => 1, 'unit_price' => 0, 'expense_account_id' => $f['expense']->id]],
    ])->assertSessionHasErrors(['line_items.0.unit_price' => 'Enter an amount above zero.']);

    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an invoice totalling zero is rejected on the lines', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Zero Co', 'slug' => 'zero-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true]);

    $run = fn () => app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $customer->id, 'currency' => 'PKR', 'date' => '2026-09-10',
        'line_items' => [['description' => 'Nothing', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => 0]],
    ], $user, true));

    try {
        $run();
        $this->fail('expected a validation error');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('line_items');
    }
    expect(Invoice::where('company_id', $company->id)->count())->toBe(0);
});
