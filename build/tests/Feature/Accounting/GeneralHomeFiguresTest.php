<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Services\GeneralHomeFigures;
use App\Services\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/BillPaymentEditFixtures.php';

/**
 * The general company home's money figures, taken from the ledger and the bills table.
 */
function homeEntry(array $f, string $date, string $type, array $lines): void
{
    $id = (string) str()->uuid();
    $total = array_sum(array_map(fn ($l) => $l[1], array_filter($lines, fn ($l) => $l[2] === 'debit')));
    DB::table('acct.transactions')->insert([
        'id' => $id, 'company_id' => $f['company']->id, 'transaction_number' => strtoupper($type).'-'.str()->random(6),
        'fiscal_year_id' => $f['sepPeriod']->fiscal_year_id, 'period_id' => $f['sepPeriod']->id,
        'transaction_type' => $type, 'transaction_date' => $date, 'posting_date' => $date, 'description' => $type,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => $total, 'total_credit' => $total, 'status' => 'posted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($lines as $i => [$account, $amount, $side]) {
        DB::table('acct.journal_entries')->insert([
            'id' => (string) str()->uuid(), 'company_id' => $f['company']->id, 'transaction_id' => $id, 'account_id' => $account->id,
            'line_number' => $i + 1, 'debit_amount' => $side === 'debit' ? $amount : 0, 'credit_amount' => $side === 'credit' ? $amount : 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function homeFigures(array $f, string $today): array
{
    return app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(GeneralHomeFigures::class)->for($f['company'], Carbon::parse($today))
    );
}

test('a general home reports cash, payables and the month from the books', function () {
    $f = billPaymentEditFixture();
    $mk = fn (string $code, string $name, string $type) => Account::create([
        'company_id' => $f['company']->id, 'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $type === 'revenue' ? 'sales' : 'operating_expense',
        'normal_balance' => $type === 'revenue' ? 'credit' : 'debit', 'is_active' => true,
    ]);
    $sales = $mk('4000', 'Sales', 'revenue');
    $rent = $mk('6000', 'Rent', 'expense');

    // An invoice of 10,000 sent, a bill of 4,000 for rent, and 3,000 received in cash.
    homeEntry($f, '2026-09-05', 'invoice', [[$f['bank'], 10000, 'debit'], [$sales, 10000, 'credit']]);
    homeEntry($f, '2026-09-06', 'bill', [[$rent, 4000, 'debit'], [$f['ap'], 4000, 'credit']]);
    homeEntry($f, '2026-09-07', 'payment', [[$f['cash'], 3000, 'debit'], [$f['bank'], 3000, 'credit']]);

    // Only the 4,000 bill is open: the two fixture bills are settled, a draft is not owed.
    Bill::query()->where('company_id', $f['company']->id)->update(['balance' => 0, 'status' => 'paid']);
    Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'bill_number' => 'BILL-RENT', 'bill_date' => '2026-09-06',
        'due_date' => '2026-09-14', 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 4000, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 4000, 'paid_amount' => 0,
        'balance' => 4000, 'base_amount' => 4000, 'created_by_user_id' => $f['user']->id,
    ]);
    Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'bill_number' => 'BILL-DRAFT', 'bill_date' => '2026-09-06',
        'due_date' => '2026-09-14', 'status' => 'draft', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 900, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 900, 'paid_amount' => 0,
        'balance' => 900, 'base_amount' => 900, 'created_by_user_id' => $f['user']->id,
    ]);

    $figures = homeFigures($f, '2026-09-15');

    expect($figures['revenue_mtd'])->toBe(10000.0)
        ->and($figures['expenses_mtd'])->toBe(4000.0)
        ->and($figures['profit_mtd'])->toBe(6000.0)
        ->and($figures['ap_outstanding'])->toBe(4000.0)
        ->and($figures['ap_overdue'])->toBe(4000.0)
        // Bank 10,000 - 3,000 plus cash 3,000: a transfer between the two moves nothing.
        ->and($figures['cash_and_bank'])->toBe(10000.0);
});

test('last month does not count towards this month and a bill not yet due is not overdue', function () {
    $f = billPaymentEditFixture();
    $sales = Account::create([
        'company_id' => $f['company']->id, 'code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'subtype' => 'sales',
        'normal_balance' => 'credit', 'is_active' => true,
    ]);
    homeEntry($f, '2026-08-20', 'invoice', [[$f['bank'], 7000, 'debit'], [$sales, 7000, 'credit']]);

    $figures = homeFigures($f, '2026-09-01');

    // The fixture's two bills (20,000 and 5,000) fall due 5 and 6 September.
    expect($figures['revenue_mtd'])->toBe(0.0)
        ->and($figures['cash_and_bank'])->toBe(7000.0)
        ->and($figures['ap_outstanding'])->toBe(25000.0)
        ->and($figures['ap_overdue'])->toBe(0.0);
});
