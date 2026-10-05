<?php

namespace App\Modules\Accounting\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;

/**
 * A partner's statement, straight from the ledger: what they put in and their profit shares
 * (Capital - name, credit side) against what they took out (Drawings - name, debit side),
 * with one running balance. Closing balance = Capital account balance - Drawings account
 * balance, the same figure as the partner's page.
 *
 * Reads the same posted journals as the balance sheet. A profit share that was shared again
 * (reversed and re-posted) hides the pair within the range unless asked for; the balance is
 * the same either way.
 */
class PartnerStatementService
{
    private const POSTED = ['posted', 'locked'];

    /**
     * @return array{rows: array<int,array<string,mixed>>, opening_balance: float, closing_balance: float, from: string, to: string, party: string, reversed_count: int}
     */
    public function statement(Partner $partner, string $from, string $to, bool $showReversed = false): array
    {
        $accountIds = array_values(array_filter([$partner->capital_account_id, $partner->drawing_account_id]));
        // Capital: credit adds to what the partner is owed, debit takes from it. Drawings: a debit
        // (money taken) takes from it, a credit gives back. One formula for both: credit - debit.
        $sign = fn ($line) => (float) $line->credit_amount - (float) $line->debit_amount;

        $base = fn () => DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $partner->company_id)
            ->whereIn('je.account_id', $accountIds)
            ->whereIn('t.status', self::POSTED);

        $opening = 0.0;
        if ($accountIds) {
            $row = $base()->whereDate('t.transaction_date', '<', $from)
                ->selectRaw('COALESCE(SUM(je.credit_amount - je.debit_amount),0) as net')->first();
            $opening = round((float) $row->net, 2);
        }

        $lines = $accountIds
            ? $base()
                ->whereDate('t.transaction_date', '>=', $from)->whereDate('t.transaction_date', '<=', $to)
                ->orderBy('t.transaction_date')
                ->orderByRaw('CASE WHEN je.credit_amount > 0 THEN 0 ELSE 1 END')
                ->orderBy('t.created_at')->orderBy('je.line_number')
                ->select(['t.id as transaction_id', 't.transaction_date', 't.transaction_number', 't.transaction_type', 't.description as txn_description',
                    't.reversal_of_id', 't.reversed_by_id', 'je.account_id', 'je.description', 'je.debit_amount', 'je.credit_amount'])
                ->get()
            : collect();

        $inRange = $lines->pluck('transaction_id')->flip();
        $reversed = $lines->filter(fn ($l) => ($l->reversal_of_id && $inRange->has($l->reversal_of_id))
            || ($l->reversed_by_id && $inRange->has($l->reversed_by_id)))->pluck('transaction_id')->unique();
        if (! $showReversed) {
            $lines = $lines->reject(fn ($l) => $reversed->contains($l->transaction_id))->values();
        }

        $running = $opening;
        $rows = [['date' => $from, 'type' => 'opening_balance', 'reference' => null, 'description' => 'Opening balance',
            'money_in' => 0.0, 'money_out' => 0.0, 'balance' => $opening, 'link' => null]];
        foreach ($lines as $line) {
            $net = round($sign($line), 2);
            $running = round($running + $net, 2);
            $rows[] = [
                'date' => \Carbon\Carbon::parse($line->transaction_date)->toDateString(),
                'type' => $line->transaction_type,
                'reference' => $line->transaction_number,
                'description' => (string) ($line->description ?: $line->txn_description ?: 'Journal entry'),
                'money_in' => $net > 0 ? $net : 0.0,
                'money_out' => $net < 0 ? -$net : 0.0,
                'balance' => $running,
                'link' => null,
            ];
        }
        $rows[] = ['date' => $to, 'type' => 'closing_balance', 'reference' => null, 'description' => 'Closing balance',
            'money_in' => 0.0, 'money_out' => 0.0, 'balance' => $running, 'link' => null];

        return [
            'rows' => $rows,
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'from' => $from,
            'to' => $to,
            'party' => $partner->name,
            'reversed_count' => $reversed->count(),
        ];
    }
}
