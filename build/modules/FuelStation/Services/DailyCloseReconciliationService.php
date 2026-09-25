<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Reconciliation evidence only. All financial amounts come from canonical journals. */
class DailyCloseReconciliationService
{
    public function park(string $companyId, array $payload, string $userId): void
    {
        DB::transaction(function () use ($companyId, $payload, $userId) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$companyId, $payload['date']]);
            if (Transaction::where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')
                ->whereDate('transaction_date', $payload['date'])->exists()) {
                throw new \RuntimeException('This business date is already posted. Record a dated adjustment instead.');
            }
            $existing = DB::table('fuel.daily_close_drafts')->where('company_id', $companyId)
                ->where('business_date', $payload['date'])->first();
            $values = ['payload' => json_encode($payload), 'updated_by_user_id' => $userId, 'updated_at' => now()];
            if ($existing) {
                DB::table('fuel.daily_close_drafts')->where('id', $existing->id)->where('company_id', $companyId)->update($values);
            } else {
                DB::table('fuel.daily_close_drafts')->insert($values + [
                    'id' => (string) Str::uuid(), 'company_id' => $companyId,
                    'business_date' => $payload['date'], 'created_by_user_id' => $userId, 'created_at' => now(),
                ]);
            }
        });
    }

    public function draft(string $companyId, string $date): ?array
    {
        $row = DB::table('fuel.daily_close_drafts')->where('company_id', $companyId)->where('business_date', $date)->first();
        return $row ? json_decode($row->payload, true) : null;
    }

    /**
     * Openings for the day after $date come from $date's parked draft when $date has one and
     * has NOT been posted (a posted, non-reversed fuel_daily_close transaction always wins —
     * the draft is stale once that happens). Returns null when neither condition holds, so the
     * caller falls back to its normal posted-close lookups.
     *
     * Used both by the Create page (to seed the next day's openings — Owner's rule A: "even if
     * the close is not posted, its values should be taken in the next day") and by
     * DailyCloseService::processDailyClose (to refuse posting a day whose previous day is
     * still only parked).
     */
    public function parkedClosingFigures(string $companyId, string $date): ?array
    {
        $posted = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereDate('transaction_date', $date)
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->exists();

        if ($posted) {
            return null;
        }

        $payload = $this->draft($companyId, $date);

        if (! $payload) {
            return null;
        }

        $nozzles = [];
        foreach ($payload['nozzle_readings'] ?? [] as $reading) {
            if (empty($reading['nozzle_id'])) {
                continue;
            }
            $nozzles[$reading['nozzle_id']] = [
                'closing_electronic' => isset($reading['closing_electronic']) ? (float) $reading['closing_electronic'] : null,
                'closing_manual' => isset($reading['closing_manual']) ? (float) $reading['closing_manual'] : null,
            ];
        }

        $tanks = [];
        foreach ($payload['tank_readings'] ?? [] as $reading) {
            if (empty($reading['tank_id'])) {
                continue;
            }
            $tanks[$reading['tank_id']] = [
                'liters' => isset($reading['liters']) ? (float) $reading['liters'] : null,
                'stick_reading' => isset($reading['stick_reading']) ? (float) $reading['stick_reading'] : null,
            ];
        }

        return [
            'date' => $date,
            'nozzles' => $nozzles,
            'tanks' => $tanks,
            'closing_cash' => isset($payload['closing_cash']) ? (float) $payload['closing_cash'] : null,
        ];
    }

    public function sources(string $companyId, string $date, ?string $excludeId = null, ?string $snapshotCashAccountId = null): array
    {
        $cashAccount = $snapshotCashAccountId ?? app(DailyCloseService::class)->cashAccountId($companyId);
        $transactions = Transaction::where('company_id', $companyId)
            ->where(function ($query) use ($date, $companyId) {
                $query->where(function ($own) use ($date) {
                    $own->whereDate('transaction_date', $date)->whereNull('reversal_of_id');
                })->orWhereHas('reversalOf', fn ($original) => $original->where('company_id', $companyId)->whereDate('transaction_date', $date));
            })->whereIn('status', ['posted', 'locked'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('transaction_type', '!=', 'fuel_daily_close')
            ->with('journalEntries')->orderBy('id')->get();
        $accountTypes = DB::table('acct.accounts')->where('company_id', $companyId)->pluck('type', 'id');
        $sources = [];
        foreach ($transactions as $transaction) {
            $cashIn = 0; $cashOut = 0; $sales = 0; $channels = [];
            $lines = [];
            foreach ($transaction->journalEntries as $line) {
                $lines[$line->id] = ['account_id' => $line->account_id,
                    'debit' => (float) $line->debit_amount, 'credit' => (float) $line->credit_amount];
                if (($accountTypes[$line->account_id] ?? null) === 'revenue') { $sales += (float) $line->credit_amount - (float) $line->debit_amount; }
                $channels[$line->account_id] = ($channels[$line->account_id] ?? 0) + (float) $line->debit_amount - (float) $line->credit_amount;
                if ($line->account_id === $cashAccount) {
                    $cashIn += (float) $line->debit_amount;
                    $cashOut += (float) $line->credit_amount;
                }
            }
            $sources['journal:'.$transaction->id] = [
                'id' => $transaction->id, 'type' => $transaction->transaction_type,
                'reference' => $transaction->transaction_number, 'source_type' => $transaction->reference_type,
                'source_id' => $transaction->reference_id, 'business_date' => $transaction->transaction_date->toDateString(), 'reconciles_business_date' => $date,
                'entered_at' => $transaction->created_at?->toISOString(),
                'entered_by' => $transaction->created_by_user_id,
                'updated_at' => $transaction->updated_at?->toISOString(),
                'updated_by' => $transaction->updated_by_user_id,
                'amount' => (float) $transaction->total_debit, 'sales' => $transaction->reference_type === 'fuel.reading_correction' ? (float) ($transaction->metadata['revenue_effect'] ?? 0) : round($sales, 2), 'account_effects' => $channels,
                'money_in' => round($cashIn, 2), 'money_out' => round($cashOut, 2),
                'cash_effect' => round($cashIn - $cashOut, 2), 'lines' => $lines,
            ];
        }
        $movements = DB::table('inv.stock_movements')->where('company_id', $companyId)
            ->whereDate('movement_date', $date)
            ->where(function ($q) { $q->whereNull('reference_type')->orWhere('reference_type', '!=', 'fuel.daily_close'); })
            ->orderBy('id')->get();
        foreach ($movements as $movement) {
            $sources['stock:'.$movement->id] = [
                'id' => $movement->id, 'type' => 'stock:'.$movement->movement_type,
                'reference' => $movement->reference_id, 'source_type' => $movement->reference_type,
                'business_date' => $date, 'entered_at' => $movement->created_at,
                'entered_by' => $movement->created_by_user_id, 'updated_at' => null,
                'warehouse_id' => $movement->warehouse_id, 'item_id' => $movement->item_id,
                'quantity' => (float) $movement->quantity, 'amount' => (float) $movement->total_cost,
                'cash_effect' => 0, 'money_in' => 0, 'money_out' => 0,
            ];
        }
        return $sources;
    }

    public function view(Transaction $close): array
    {
        $metadata = $close->metadata ?? [];
        // Every Daily Close is a snapshot close; the legacy branch for a close posted
        // without one (pre-snapshot code) has been removed — see docs/contracts/fuel-schema.md.
        $snapshot = $metadata['posting_snapshot'] ?? null;
        if (!$snapshot) {
            throw new \RuntimeException('This Daily Close has no posting snapshot and cannot be reconciled.');
        }
        // Old snapshots stored cash-only flows. Normalize the read model, never the frozen record.
        if (($snapshot['version'] ?? 1) < 2) {
            $nonCash = array_sum(array_column($snapshot['channels'] ?? [], 'amount'));
            $snapshot['totals']['money_in'] += $nonCash;
            $snapshot['totals']['money_out'] += $nonCash;
        }
        $snapshot['posted_by_name'] = DB::table('auth.users')->where('id', $snapshot['posted_by'])->value('name');
        $currentSources = $this->sources($close->company_id, $close->transaction_date->toDateString(), $close->id, $snapshot['cash_account_id'] ?? null);
        $before = $snapshot['sources'];
        $activity = [];
        $current = $snapshot['totals'];
        $current['account_effects'] = $snapshot['account_effects'] ?? [];
        $current['tanks'] = $snapshot['tanks'] ?? [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($currentSources))) as $key) {
            $old = $before[$key] ?? null;
            $new = $currentSources[$key] ?? null;
            if ($old == $new) { continue; }
            $row = $new ?? $old;
            $effect = round(($new['cash_effect'] ?? 0) - ($old['cash_effect'] ?? 0), 2);
            if (!$new && str_starts_with($key, 'journal:')) {
                $removed = Transaction::withTrashed()->where('company_id', $close->company_id)->find($row['id']);
                $row['updated_at'] = $removed?->updated_at?->toISOString();
                $row['updated_by'] = $removed?->updated_by_user_id;
            }
            $row['activity'] = !$old ? (strtotime($row['entered_at'] ?? '') > strtotime($snapshot['posted_at']) ? 'Entered after posting' : 'Added to this business date after posting') : (!$new ? 'Removed or moved to another business date' : 'Amended after posting');
            $row['reconciliation_effect'] = $effect;
            $row['quantity_effect'] = ($new['quantity'] ?? 0) - ($old['quantity'] ?? 0);
            $row['before'] = $old;
            $row['after'] = $new;
            $activity[] = $row;
            if (isset($row['warehouse_id']) && ($row['source_type'] ?? null) !== 'fuel.reading_correction') {
                foreach ($current['tanks'] as &$tank) {
                    $oldQuantity = ($tank['tank_id'] === ($old['warehouse_id'] ?? null) && $tank['item_id'] === ($old['item_id'] ?? null)) ? ($old['quantity'] ?? 0) : 0;
                    $newQuantity = ($tank['tank_id'] === ($new['warehouse_id'] ?? null) && $tank['item_id'] === ($new['item_id'] ?? null)) ? ($new['quantity'] ?? 0) : 0;
                    if ($oldQuantity || $newQuantity) {
                        $tank['expected_liters'] += $newQuantity - $oldQuantity;
                        $tank['variance_liters'] = round($tank['physical_liters'] - $tank['expected_liters'], 3);
                    }
                }
                unset($tank);
            }
            foreach (array_unique(array_merge(array_keys($old['account_effects'] ?? []), array_keys($new['account_effects'] ?? []))) as $accountId) {
                $current['account_effects'][$accountId] = ($current['account_effects'][$accountId] ?? 0)
                    + ($new['account_effects'][$accountId] ?? 0) - ($old['account_effects'][$accountId] ?? 0);
            }
            $current['total_revenue'] += ($new['sales'] ?? 0) - ($old['sales'] ?? 0);
            $current['money_in'] += ($new['money_in'] ?? 0) - ($old['money_in'] ?? 0);
            $current['money_out'] += ($new['money_out'] ?? 0) - ($old['money_out'] ?? 0);
            $current['expected_closing'] += $effect;
        }
        $current['variance'] = round($current['closing_cash'] - $current['expected_closing'], 2);
        $corrections = $this->applyReadingCorrections($close, $current);
        $current = $corrections['current'];
        $userIds = collect($activity)->flatMap(fn ($row) => [$row['entered_by'] ?? null, $row['updated_by'] ?? null])->filter()->unique();
        $users = DB::table('auth.users')->whereIn('id', $userIds)->pluck('name', 'id');
        foreach ($activity as &$row) {
            $row['entered_by_name'] = $users[$row['entered_by'] ?? ''] ?? 'Unknown';
            $row['updated_by_name'] = $users[$row['updated_by'] ?? ''] ?? null;
        }
        $audit = DB::table('fuel.daily_close_activity as activity')
            ->leftJoin('auth.users as actor', 'actor.id', '=', 'activity.actor_id')
            ->where('activity.company_id', $close->company_id)->where('close_transaction_id', $close->id)
            ->orderBy('occurred_at')->orderBy('activity.id')
            ->get(['activity.*', 'actor.name as actor_name'])->map(function ($event) {
                $event->before_data = $event->before_data ? json_decode($event->before_data, true) : null;
                $event->after_data = $event->after_data ? json_decode($event->after_data, true) : null;
                return $event;
            })->all();
        return [
            'audit_events' => $audit,
            'has_post_close_activity' => !empty($activity) || !empty($audit) || !empty($corrections['list']),
            'snapshot' => $snapshot,
            'current' => $current,
            'activity' => $activity,
            'corrections' => $corrections['list'],
        ];
    }

    /** Apply frozen physical and cash-expectation deltas; revenue comes from canonical GL above. */
    private function applyReadingCorrections(Transaction $close, array $current): array
    {
        $rows = DB::table('fuel.daily_close_reading_corrections as c')
            ->leftJoin('auth.users as actor', 'actor.id', '=', 'c.created_by_user_id')
            ->where('c.company_id', $close->company_id)->where('c.close_transaction_id', $close->id)
            ->orderBy('c.created_at')->orderBy('c.revision')->orderBy('c.id')
            ->get(['c.*', 'actor.name as actor_name']);
        $list = [];
        foreach ($rows as $row) {
            $effect = json_decode($row->effects, true);
            $current['money_in'] += $effect['revenue_effect'];
            $current['expected_closing'] += $effect['revenue_effect'];
            foreach ($current['tanks'] as &$tank) {
                if ($tank['tank_id'] === $effect['tank_id'] && $tank['item_id'] === $effect['item_id']) {
                    $tank['physical_liters'] = round($tank['physical_liters'] + $effect['physical_liters_effect'], 3);
                    $tank['expected_liters'] = round($tank['expected_liters'] + $effect['expected_liters_effect'], 3);
                    $tank['variance_liters'] = round($tank['physical_liters'] - $tank['expected_liters'], 3);
                }
            }
            unset($tank);
            $list[] = ['id' => $row->id, 'reading_type' => $row->reading_type, 'reading_id' => $row->reading_id,
                'original_value' => (float) $row->original_value, 'corrected_value' => (float) $row->corrected_value,
                'revision' => $row->revision, 'transaction_id' => $row->transaction_id,
                'reason' => $row->reason, 'created_by_name' => $row->actor_name ?? 'Unknown',
                'created_at' => $row->created_at] + $effect;
        }
        $current['variance'] = round($current['closing_cash'] - $current['expected_closing'], 2);
        return ['current' => $current, 'list' => $list];
    }
}
