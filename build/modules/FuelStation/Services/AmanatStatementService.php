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
    public function statement(Customer $customer, string $from, string $to, bool $includeTrail = false): array
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
            ->selectRaw('a.*, t.id as transaction_id, COALESCE(t.transaction_date, a.created_at::date) as movement_date');

        $openingRow = DB::query()->fromSub($dated, 'm')
            ->where('movement_date', '<', $from)
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) as balance")
            ->when($includeTrail, fn ($q) => $q->selectRaw('json_agg(m) as evidence'))
            ->first();
        $opening = (float) $openingRow->balance;
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

        $result = [
            'rows' => $rows,
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'from' => $from,
            'to' => $to,
            'party' => $customer->name,
        ];
        if ($includeTrail) {
            $builder = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
            $graph = ['nodes' => [], 'roots' => [], 'context' => ['start_date' => $from, 'end_date' => $to]];
            $leaf = function (object $m) use ($builder, &$graph) {
                return $builder->node($graph, 'amanat:'.$m->id, $this->describe($m).' · '.$m->movement_date,
                    round($m->transaction_type === AmanatTransaction::TYPE_DEPOSIT ? (float) $m->amount : -(float) $m->amount, 2),
                    formula: 'Deposits add; withdrawals and fuel purchases subtract', source: $m->transaction_id ? [
                        'transaction_id' => $m->transaction_id, 'date' => $m->movement_date,
                    ] : null);
            };
            $prior = array_map($leaf, json_decode($openingRow->evidence ?? '[]') ?: []);
            $current = $movements->map($leaf)->all();
            $builder->node($graph, 'statement:opening', 'Opening balance', $opening, $prior, 'Sum of prior signed amanat movements');
            $builder->node($graph, 'statement:closing', 'Closing balance', $running, ['statement:opening', ...$current], 'Opening + signed amanat movements');
            $result['valueTrail'] = $graph;
        }

        return $result;
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
