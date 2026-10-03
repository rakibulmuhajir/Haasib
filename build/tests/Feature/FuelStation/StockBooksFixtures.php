<?php

use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * A posted transaction straight into the books: $lines is [[account_id, amount, 'debit'|'credit'], ...].
 * Needs a fiscal year and period for the company (creditCloseFixture() makes them).
 */
function stockBooksEntry(string $companyId, string $date, string $type, array $lines): string
{
    $id = (string) str()->uuid();
    $total = array_sum(array_map(fn ($l) => $l[1], array_filter($lines, fn ($l) => $l[2] === 'debit')));
    DB::table('acct.transactions')->insert([
        'id' => $id, 'company_id' => $companyId, 'transaction_number' => strtoupper($type).'-'.str()->random(6),
        ...fixtureYearAndPeriod($companyId), 'transaction_type' => $type, 'transaction_date' => $date, 'posting_date' => $date,
        'description' => $type, 'currency' => 'PKR', 'total_debit' => $total, 'total_credit' => $total, 'status' => 'posted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($lines as $i => [$account, $amount, $side]) {
        DB::table('acct.journal_entries')->insert([
            'id' => (string) str()->uuid(), 'company_id' => $companyId, 'transaction_id' => $id, 'account_id' => $account,
            'line_number' => $i + 1, 'debit_amount' => $side === 'debit' ? $amount : 0, 'credit_amount' => $side === 'credit' ? $amount : 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}
