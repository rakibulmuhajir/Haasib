<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * bank-transactions/Index and expenses/Index used to load an unfiltered, unpaginated
 * ->limit(200) list. This exercises the date-range/account/search filters and
 * server-side pagination added on top of that, plus the bank-transactions running
 * balance -- which must read "as of this row across the whole ledger", not "within
 * the filtered page", so page 2 has to pick up exactly where page 1 left off.
 */
function ledgerIndexFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Ledger Index Co '.str()->random(8),
        'slug' => 'ledger-index-'.str()->lower(str()->random(10)),
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

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'January', 'period_number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $bank = Account::create(['company_id' => $company->id, 'code' => '1060', 'name' => 'Main Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $otherBank = Account::create(['company_id' => $company->id, 'code' => '1070', 'name' => 'Second Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $rent = Account::create(['company_id' => $company->id, 'code' => '6100', 'name' => 'Rent Expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit']);
    $utilities = Account::create(['company_id' => $company->id, 'code' => '6200', 'name' => 'Utilities Expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit']);

    return compact('owner', 'company', 'cash', 'bank', 'otherBank', 'rent', 'utilities');
}

function dispatchDeposit(array $f, string $date, float $amount, ?string $reference = null): void
{
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bank_transaction.create', [
        'kind' => 'deposit', 'date' => $date, 'amount' => $amount,
        'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank']->id,
        'reference' => $reference,
    ], $f['owner'], true));
}

test('the bank transactions page paginates and the running balance on page 2 continues from page 1', function () {
    $f = ledgerIndexFixture();

    // 30 deposits of 100 each into the same bank account, one per day in January.
    // Chronologically the running balance after day N is 100*N.
    for ($day = 1; $day <= 30; $day++) {
        dispatchDeposit($f, sprintf('2026-01-%02d', $day), 100);
    }

    $page1 = $this->actingAs($f['owner'])->get("/{$f['company']->slug}/banking/transactions?account_id={$f['bank']->id}");
    $page1->assertOk();
    $page1->assertInertia(fn (Assert $page) => $page
        ->where('transactions.current_page', 1)
        ->where('transactions.per_page', 25)
        ->where('transactions.total', 30)
        ->has('transactions.data', 25));

    $page1Data = $page1->viewData('page')['props']['transactions']['data'];
    // Newest first: the first row is day 30 (balance 3000), the last row on this page
    // is day 6 (balance 600) -- day 6 is the 25th-from-newest of 30 days.
    expect($page1Data[0]['date'])->toBe('2026-01-30')
        ->and((float) $page1Data[0]['running_balance'])->toBe(3000.0)
        ->and($page1Data[24]['date'])->toBe('2026-01-06')
        ->and((float) $page1Data[24]['running_balance'])->toBe(600.0);

    $page2 = $this->actingAs($f['owner'])->get("/{$f['company']->slug}/banking/transactions?account_id={$f['bank']->id}&page=2");
    $page2->assertOk();
    $page2->assertInertia(fn (Assert $page) => $page
        ->where('transactions.current_page', 2)
        ->has('transactions.data', 5));

    $page2Data = $page2->viewData('page')['props']['transactions']['data'];
    // Day 5's balance (500) picks up exactly where day 6's (600) on page 1 left off --
    // it is not recomputed from a running total that restarted at this page's first row.
    expect($page2Data[0]['date'])->toBe('2026-01-05')
        ->and((float) $page2Data[0]['running_balance'])->toBe(500.0)
        ->and($page2Data[4]['date'])->toBe('2026-01-01')
        ->and((float) $page2Data[4]['running_balance'])->toBe(100.0);
});

test('the bank transactions page filters by date range, account and search text', function () {
    $f = ledgerIndexFixture();

    dispatchDeposit($f, '2026-01-05', 500, 'REF-EARLY');
    dispatchDeposit($f, '2026-01-15', 700, 'REF-MID');
    dispatchDeposit($f, '2026-01-25', 900, 'REF-LATE');

    // A deposit into the other bank account should never appear once account_id narrows
    // to the first bank account.
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bank_transaction.create', [
        'kind' => 'deposit', 'date' => '2026-01-15', 'amount' => 999,
        'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['otherBank']->id,
        'reference' => 'REF-OTHER-BANK',
    ], $f['owner'], true));

    $dateFiltered = $this->actingAs($f['owner'])
        ->get("/{$f['company']->slug}/banking/transactions?date_from=2026-01-10&date_to=2026-01-20");
    $dateFiltered->assertInertia(fn (Assert $page) => $page->has('transactions.data', 2));

    $accountFiltered = $this->actingAs($f['owner'])
        ->get("/{$f['company']->slug}/banking/transactions?account_id={$f['bank']->id}");
    $accountFiltered->assertInertia(fn (Assert $page) => $page->has('transactions.data', 3));

    $searchFiltered = $this->actingAs($f['owner'])
        ->get("/{$f['company']->slug}/banking/transactions?search=REF-MID");
    $searchFiltered->assertInertia(fn (Assert $page) => $page->has('transactions.data', 1));
});

function dispatchStandaloneExpense(array $f, string $date, string $account, float $amount, ?string $description = null): void
{
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('expense.create', [
        'date' => $date, 'account_id' => $f[$account]->id, 'amount' => $amount,
        'paid_from_account_id' => $f['cash']->id, 'description' => $description,
    ], $f['owner'], true));
}

test('the expenses page filters by date range, account and search, and paginates', function () {
    $f = ledgerIndexFixture();

    for ($day = 1; $day <= 27; $day++) {
        dispatchStandaloneExpense($f, sprintf('2026-01-%02d', $day), 'rent', 100, "Rent day {$day}");
    }
    dispatchStandaloneExpense($f, '2026-01-10', 'utilities', 250, 'Electricity bill');

    $page1 = $this->actingAs($f['owner'])->get("/{$f['company']->slug}/expenses");
    $page1->assertInertia(fn (Assert $page) => $page
        ->where('expenses.current_page', 1)
        ->where('expenses.total', 28)
        ->has('expenses.data', 25));

    $page2 = $this->actingAs($f['owner'])->get("/{$f['company']->slug}/expenses?page=2");
    $page2->assertInertia(fn (Assert $page) => $page
        ->where('expenses.current_page', 2)
        ->has('expenses.data', 3));

    $byAccount = $this->actingAs($f['owner'])->get("/{$f['company']->slug}/expenses?account_id={$f['utilities']->id}");
    $byAccount->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1));

    $byDate = $this->actingAs($f['owner'])
        ->get("/{$f['company']->slug}/expenses?date_from=2026-01-01&date_to=2026-01-05");
    $byDate->assertInertia(fn (Assert $page) => $page->has('expenses.data', 5));

    $bySearch = $this->actingAs($f['owner'])->get("/{$f['company']->slug}/expenses?search=Electricity");
    $bySearch->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1));
});
