<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Services\StationPerformanceReportService;
use Illuminate\Support\Facades\DB;

/**
 * Profit by day, all products: every money column comes from the books, account by account,
 * and gross - expenses + other always equals the books' profit.
 */
function booksFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Books', 'slug' => 'books-'.str()->lower(str()->random(10)), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    enterCompany($company);

    $acct = function (string $code, string $name, string $type, string $normal) use ($company) {
        $id = (string) str()->uuid();
        DB::table('acct.accounts')->insert([
            'id' => $id, 'company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type,
            'subtype' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    };
    $a = [
        'cash' => $acct('1050', 'Cash on Hand', 'asset', 'debit'),
        'sales' => $acct('4100', 'Fuel Sales - Petrol', 'revenue', 'credit'),
        'gain' => $acct('4900', 'Fuel Variance Gain', 'revenue', 'credit'),
        'cost' => $acct('5100', 'Cost of Fuel - Petrol', 'cogs', 'debit'),
        'power' => $acct('6110', 'Electricity', 'expense', 'debit'),
        'salary' => $acct('6150', 'Salaries & Wages', 'expense', 'debit'),
    ];
    DB::table('inv.items')->insert([
        'id' => (string) str()->uuid(), 'company_id' => $company->id, 'sku' => 'PET-'.str()->random(5), 'name' => 'Petrol',
        'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'fuel_category' => 'petrol',
        'income_account_id' => $a['sales'], 'expense_account_id' => $a['cost'],
    ]);

    return [$company, $a];
}

function booksEntry(Company $company, string $date, string $type, array $lines): void
{
    $id = (string) str()->uuid();
    $total = array_sum(array_map(fn ($l) => $l[1], array_filter($lines, fn ($l) => $l[2] === 'debit')));
    DB::table('acct.transactions')->insert([
        'id' => $id, 'company_id' => $company->id, 'transaction_number' => strtoupper($type).'-'.str()->random(6),
        'transaction_type' => $type, 'transaction_date' => $date, 'posting_date' => $date, 'description' => $type,
        'currency' => 'PKR', 'total_debit' => $total, 'total_credit' => $total, 'status' => 'posted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($lines as $i => [$account, $amount, $side]) {
        DB::table('acct.journal_entries')->insert([
            'id' => (string) str()->uuid(), 'company_id' => $company->id, 'transaction_id' => $id, 'account_id' => $account,
            'line_number' => $i + 1, 'debit_amount' => $side === 'debit' ? $amount : 0, 'credit_amount' => $side === 'credit' ? $amount : 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

test('every money column is from the books and they add up to its profit', function () {
    [$company, $a] = booksFixture();

    // The close: 100,000 of petrol sold at a cost of 95,000, a 500 tank gain.
    booksEntry($company, '2026-09-10', 'fuel_daily_close', [
        [$a['cash'], 100500, 'debit'], [$a['sales'], 100000, 'credit'], [$a['gain'], 500, 'credit'],
        [$a['cost'], 95000, 'debit'], [$a['cash'], 95000, 'credit'],
    ]);
    // An expense entered under Money out > Expenses.
    booksEntry($company, '2026-09-10', 'expense', [[$a['power'], 2000, 'debit'], [$a['cash'], 2000, 'credit']]);
    // Payroll: a cost on an expense account that was not entered as an expense.
    booksEntry($company, '2026-09-10', 'payroll_accrual', [[$a['salary'], 1500, 'debit'], [$a['cash'], 1500, 'credit']]);

    $row = app(StationPerformanceReportService::class)->run($company->id, '2026-09-01', '2026-09-30', 'month', 'all')['rows'][0];

    expect($row['revenue'])->toBe(100000.0)
        ->and($row['cogs'])->toBe(95000.0)
        ->and($row['gross_profit'])->toBe(5000.0)
        ->and($row['expenses'])->toBe(2000.0)
        // Tank gain +500, salaries -1500.
        ->and($row['other'])->toBe(-1000.0)
        ->and($row['net_station_profit'])->toBe(2000.0)
        ->and(collect($row['other_lines'])->pluck('name')->sort()->values()->all())->toBe(['Fuel Variance Gain', 'Salaries & Wages']);
});
