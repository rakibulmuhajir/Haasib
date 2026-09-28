<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\AmanatTransaction;
use Illuminate\Support\Facades\DB;

/**
 * An Amanat holder's statement: what they put in, what was paid out or spent on fuel, and what
 * we still hold for them. Built from fuel.amanat_transactions -- the holder's movements never
 * touch their customer ledger (invoices / payments), which is why the Customer statement shows
 * nothing for them past the opening. Same row shape as the other statements.
 */
class AmanatStatementService
{
    public function statement(Customer $customer, string $from, string $to): array
    {
        // A movement's day is its journal's date (a close posts for its own day, keyed in the
        // next morning); a movement without a journal falls back to when it was recorded.
        $dated = DB::table('fuel.amanat_transactions as a')
            ->leftJoin('acct.journal_entries as je', 'je.id', '=', 'a.journal_entry_id')
            ->leftJoin('acct.transactions as t', function ($join) {
                $join->on('t.id', '=', 'je.transaction_id')->on('t.company_id', '=', 'a.company_id');
            })
            ->where('a.company_id', $customer->company_id)
            ->where('a.customer_id', $customer->id)
            // A voided journal takes its movement with it.
            ->where(fn ($q) => $q->whereNull('t.id')->orWhereIn('t.status', ['posted', 'locked']))
            ->selectRaw('a.*, COALESCE(t.transaction_date, a.created_at::date) as movement_date');

        $opening = (float) DB::query()->fromSub($dated, 'm')
            ->where('movement_date', '<', $from)
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance');
        $opening = round($opening, 2);

        $movements = DB::query()->fromSub($dated, 'm')
            ->whereBetween('movement_date', [$from, $to])
            // Within a day, money in before money out, as on the bank statement.
            ->orderBy('movement_date')
            ->orderByRaw("CASE WHEN transaction_type = 'deposit' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get();

        $running = $opening;
        $link = "fuel/amanat/{$customer->id}";
        $rows = [[
            'date' => $from, 'type' => 'opening_balance', 'reference' => null,
            'description' => 'Opening balance', 'money_in' => 0.0, 'money_out' => 0.0,
            'balance' => $opening, 'link' => null,
        ]];

        foreach ($movements as $m) {
            $amount = round((float) $m->amount, 2);
            $in = $m->transaction_type === AmanatTransaction::TYPE_DEPOSIT ? $amount : 0.0;
            $out = $in > 0 ? 0.0 : $amount;
            $running = round($running + $in - $out, 2);

            $rows[] = [
                'date' => \Carbon\Carbon::parse($m->movement_date)->toDateString(),
                'type' => $m->transaction_type,
                'reference' => $m->reference,
                'description' => $this->describe($m),
                'money_in' => $in,
                'money_out' => $out,
                'balance' => $running,
                'link' => $link,
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
            'party' => $customer->name,
        ];
    }

    private function describe(object $m): string
    {
        if (($m->notes ?? null) === 'Opening balance') {
            return 'Opening amanat';
        }

        $label = match ($m->transaction_type) {
            AmanatTransaction::TYPE_DEPOSIT => 'Deposit',
            AmanatTransaction::TYPE_WITHDRAWAL => 'Paid out',
            AmanatTransaction::TYPE_FUEL_PURCHASE => 'Fuel against amanat',
            default => 'Movement',
        };
        $notes = trim((string) ($m->notes ?? ''));

        return $notes !== '' && ! str_starts_with($notes, 'Daily close') ? "{$label} · {$notes}" : $label;
    }
}
