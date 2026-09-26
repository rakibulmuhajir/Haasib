<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * A bank or cash account's running balance over a date range — the GL-facing
 * sibling of CustomerStatementService and VendorStatementService.
 *
 * Unlike the party statements, there is no subsidiary ledger to build this from:
 * a bank/cash account's movements ARE the journal lines posted to it, so this
 * reads acct.journal_entries directly. It selects exactly the transactions the
 * Balance Sheet and Trial Balance would (status in POSTED, dated on/before the
 * cut-off), so a balance sheet drawn as of `$to` and this statement's closing
 * balance for the same account always agree.
 *
 * A reversal posts as its own transaction with its own journal lines, so it
 * appears here as its own row rather than hidden or netted against the entry
 * it reverses — the statement shows what happened, not a tidied-up version.
 */
class AccountStatementService
{
    /** Matches BalanceSheetReportService / TrialBalanceReportService exactly. */
    private const POSTED = ['posted', 'locked'];

    private const TYPE_LABELS = [
        'acct.invoices' => 'Invoice',
        'acct.payments' => 'Payment',
        'acct.bills' => 'Bill',
        'acct.bill_payments' => 'Bill payment',
        'fuel.daily_close' => 'Daily close',
        'fuel.daily_close_expense' => 'Daily close expense',
        'fuel.daily_close_discount' => 'Daily close discount',
        'fuel.reading_correction' => 'Reading correction',
        'fuel.payment_channel_settlement' => 'Card settlement',
    ];

    /**
     * @return array{
     *   rows: array<int, array{date:?string,type:string,reference:?string,description:string,money_in:float,money_out:float,balance:float,link:?string}>,
     *   opening_balance: float,
     *   closing_balance: float,
     *   from: string,
     *   to: string,
     *   account: string,
     * }
     */
    public function statement(Account $account, string $from, string $to): array
    {
        $isDebitNormal = $account->normal_balance !== 'credit';

        $openingRows = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $account->company_id)
            ->where('je.account_id', $account->id)
            ->whereIn('t.status', self::POSTED)
            ->whereDate('t.transaction_date', '<', $from)
            ->selectRaw('COALESCE(SUM(je.debit_amount),0) as debit, COALESCE(SUM(je.credit_amount),0) as credit')
            ->first();

        $opening = round(
            $isDebitNormal
                ? (float) $openingRows->debit - (float) $openingRows->credit
                : (float) $openingRows->credit - (float) $openingRows->debit,
            2
        );

        $lines = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $account->company_id)
            ->where('je.account_id', $account->id)
            ->whereIn('t.status', self::POSTED)
            ->whereDate('t.transaction_date', '>=', $from)
            ->whereDate('t.transaction_date', '<=', $to)
            ->orderBy('t.transaction_date')
            ->orderBy('t.created_at')
            ->orderBy('je.line_number')
            ->select([
                't.id as transaction_id', 't.transaction_date', 't.transaction_number',
                't.transaction_type', 't.description', 't.reference_type', 't.reference_id',
                'je.debit_amount', 'je.credit_amount',
            ])
            ->get();

        $running = $opening;
        $rows = [[
            'date' => $from, 'type' => 'opening_balance', 'reference' => null,
            'description' => 'Opening balance', 'money_in' => 0.0, 'money_out' => 0.0,
            'balance' => $opening, 'link' => null,
        ]];

        foreach ($lines as $line) {
            $debit = (float) $line->debit_amount;
            $credit = (float) $line->credit_amount;
            $moneyIn = $isDebitNormal ? $debit : $credit;
            $moneyOut = $isDebitNormal ? $credit : $debit;

            $running = round($running + $moneyIn - $moneyOut, 2);

            $rows[] = [
                'date' => optional($line->transaction_date ? \Carbon\Carbon::parse($line->transaction_date) : null)->toDateString(),
                'type' => $line->transaction_type,
                'reference' => $line->transaction_number,
                'description' => (string) ($line->description ?? self::TYPE_LABELS[$line->reference_type] ?? 'Journal entry'),
                'money_in' => round($moneyIn, 2),
                'money_out' => round($moneyOut, 2),
                'balance' => $running,
                'link' => $this->link($line->reference_type, $line->reference_id, $line->transaction_id),
            ];
        }

        $rows[] = [
            'date' => $to, 'type' => 'closing_balance', 'reference' => null,
            'description' => 'Closing balance', 'money_in' => 0.0, 'money_out' => 0.0,
            'balance' => $running, 'link' => null,
        ];

        return [
            'rows' => $rows,
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'from' => $from,
            'to' => $to,
            'account' => $account->name,
        ];
    }

    private function link(?string $referenceType, ?string $referenceId, string $transactionId): ?string
    {
        return match ($referenceType) {
            'acct.invoices' => $referenceId ? "invoices/{$referenceId}" : null,
            'acct.payments' => $referenceId ? "payments/{$referenceId}" : null,
            'acct.bills' => $referenceId ? "bills/{$referenceId}" : null,
            'acct.bill_payments' => $referenceId ? "bill-payments/{$referenceId}" : null,
            'fuel.daily_close', 'fuel.daily_close_expense', 'fuel.daily_close_discount', 'fuel.reading_correction' => "fuel/daily-close/{$transactionId}",
            default => null,
        };
    }
}
