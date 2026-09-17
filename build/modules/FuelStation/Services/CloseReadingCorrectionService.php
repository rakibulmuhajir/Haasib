<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\StockMovement;
use App\Services\AccountingWriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloseReadingCorrectionService
{
    public function correct(string $companyId, array $params, string $actorId): array
    {
        return AccountingWriteTransaction::run(function () use ($companyId, $params, $actorId) {
            $close = Transaction::where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')->find($params['close_id']);
            if (!$close || empty($close->metadata['posting_snapshot'])) {
                throw ValidationException::withMessages(['close_id' => 'A posted snapshot close for this company is required.']);
            }
            $date = $close->transaction_date->toDateString();
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$companyId, $date]);
            $snapshot = $close->metadata['posting_snapshot'];
            $accounts = $snapshot['correction_accounts'] ?? null;
            $type = $params['reading_type'];
            $reading = ($type === 'tank' ? TankReading::query() : NozzleReading::query())
                ->where('company_id', $companyId)->whereDate('reading_date', $date)->find($params['reading_id']);
            if (!$reading || ($type === 'nozzle' && $reading->daily_close_transaction_id !== $close->id)) {
                throw ValidationException::withMessages(['reading_id' => 'Reading does not belong to this close.']);
            }
            $basis = $type === 'tank'
                ? collect($snapshot['tanks'] ?? [])->firstWhere('tank_id', $reading->tank_id)
                : collect($snapshot['nozzles'] ?? [])->firstWhere('nozzle_id', $reading->nozzle_id);
            if (!$accounts || !$basis || !array_key_exists('unit_cost', $basis)) {
                throw ValidationException::withMessages(['reading_id' => 'This older close lacks frozen correction pricing and accounts. Record a reviewed canonical adjustment instead.']);
            }
            $history = DB::table('fuel.daily_close_reading_corrections')->where('company_id', $companyId)
                ->where('close_transaction_id', $close->id)->get();
            $previous = $history->where('reading_id', $reading->id)->sortByDesc('revision')->first();
            $revision = ($previous->revision ?? 0) + 1;
            if (isset($params['expected_revision']) && (int) $params['expected_revision'] !== $revision - 1) {
                throw ValidationException::withMessages(['corrected_value' => 'Another correction was saved. Reload this close before correcting again.']);
            }
            $original = (float) ($previous->corrected_value ?? ($type === 'tank' ? $reading->dip_measurement_liters : $reading->liters_dispensed));
            $value = round((float) $params['corrected_value'], 3);
            $delta = round($value - $original, 3);
            if (abs($delta) < 0.001) {
                throw ValidationException::withMessages(['corrected_value' => 'Enter a value different from the current corrected reading.']);
            }
            $tankId = $basis['tank_id'];
            $tank = collect($snapshot['tanks'] ?? [])->firstWhere('tank_id', $tankId);
            $effect = ['tank_id' => $tankId, 'item_id' => $basis['item_id'], 'quantity_effect' => $delta,
                'expected_liters_effect' => $type === 'nozzle' ? -$delta : 0,
                'physical_liters_effect' => $type === 'tank' ? $delta : 0,
                'revenue_effect' => 0.0, 'cogs_effect' => 0.0, 'unit_cost' => (float) $basis['unit_cost']];
            $debits = [];
            $add = function (?string $account, float $amount) use (&$debits) {
                if (abs($amount) < 0.005) { return; }
                if (!$account) { throw ValidationException::withMessages(['reading_id' => 'The posted close lacks an accounting account needed for this correction.']); }
                $debits[$account] = ($debits[$account] ?? 0) + $amount;
            };
            if ($type === 'nozzle') {
                $effect['revenue_effect'] = round($this->revenue($basis, $value) - $this->revenue($basis, $original), 2);
                $effect['cogs_effect'] = round($delta * $basis['unit_cost'], 2);
                // Counted cash is historical. The changed sales expectation reclassifies over/short.
                $add($basis['income_account_id'], -$effect['revenue_effect']);
                $add($accounts['cash_over_short'], $effect['revenue_effect']);
                $add($basis['cogs_account_id'], $effect['cogs_effect']);
                $add($basis['inventory_account_id'], -$effect['cogs_effect']);
            }
            if ($tank) {
                // Reclassify only correction effects, not unrelated late purchases/adjustments.
                $oldVariance = (float) $tank['variance_liters'];
                foreach ($history as $row) {
                    $prior = json_decode($row->effects, true);
                    if (($prior['tank_id'] ?? null) === $tankId) {
                        $oldVariance += ($prior['physical_liters_effect'] ?? 0) - ($prior['expected_liters_effect'] ?? 0);
                    }
                }
                $newVariance = $oldVariance + $delta;
                foreach ([[$oldVariance, -1], [$newVariance, 1]] as [$variance, $sign]) {
                    // Match the original close's half-litre variance tolerance.
                    if (abs($variance) <= 0.5) { continue; }
                    $amount = round(abs($variance) * $tank['unit_cost'], 2) * $sign;
                    $add($tank['inventory_account_id'], $variance > 0 ? $amount : -$amount);
                    $add($variance > 0 ? $accounts['fuel_variance_gain'] : $accounts['fuel_shrinkage'], $variance > 0 ? -$amount : $amount);
                }
            }
            $id = (string) Str::uuid();
            $entries = [];
            foreach ($debits as $account => $amount) {
                $amount = round($amount, 2);
                if (abs($amount) >= 0.01) {
                    $entries[] = ['account_id' => $account, 'type' => $amount > 0 ? 'debit' : 'credit', 'amount' => abs($amount)];
                }
            }
            $transaction = $entries ? app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $companyId, 'transaction_type' => 'fuel_reading_correction', 'date' => $date,
                'currency' => $close->currency, 'base_currency' => $close->base_currency, 'exchange_rate' => $close->exchange_rate,
                'reference_type' => 'fuel.reading_correction', 'reference_id' => $id,
                'description' => 'Reading correction: '.$params['reason'],
                'metadata' => ['revenue_effect' => $effect['revenue_effect']],
            ], $entries) : null;
            // A declared physical dip already fixes stock on hand; changing meter sales
            // only reallocates COGS/variance. Without a dip, adjust actual stock too.
            $stockDelta = $type === 'tank' ? $delta : ($tank ? 0 : -$delta);
            if (abs($stockDelta) >= 0.001) {
                if (!$tankId) { throw ValidationException::withMessages(['reading_id' => 'The posted reading has no supplying tank.']); }
                StockMovement::create([
                    'company_id' => $companyId, 'warehouse_id' => $tankId, 'item_id' => $basis['item_id'],
                    'movement_date' => $date, 'movement_type' => $stockDelta > 0 ? 'adjustment_in' : 'adjustment_out',
                    'quantity' => $stockDelta, 'unit_cost' => $basis['unit_cost'], 'total_cost' => round($stockDelta * $basis['unit_cost'], 2),
                    'gl_transaction_id' => $transaction?->id, 'reference_type' => 'fuel.reading_correction', 'reference_id' => $id,
                    'reason' => 'Correction of posted reading', 'notes' => $params['reason'], 'created_by_user_id' => $actorId,
                ]);
            }
            DB::table('fuel.daily_close_reading_corrections')->insert([
                'id' => $id, 'company_id' => $companyId, 'close_transaction_id' => $close->id,
                'reading_type' => $type, 'reading_id' => $reading->id, 'original_value' => $original,
                'corrected_value' => $value, 'revision' => $revision, 'effects' => json_encode($effect),
                'transaction_id' => $transaction?->id, 'reason' => $params['reason'],
                'created_by_user_id' => $actorId, 'created_at' => now(),
            ]);
            if ($transaction) {
                $transaction->update(['metadata' => ['revenue_effect' => $effect['revenue_effect'], 'reading_correction' => $id]]);
            }
            return ['id' => $id, 'close_id' => $close->id, 'reading_type' => $type, 'reading_id' => $reading->id,
                'original_value' => $original, 'corrected_value' => $value, 'revision' => $revision];
        });
    }

    private function revenue(array $basis, float $liters): float
    {
        $pricing = $basis['pricing'];
        if (!($pricing['used_snapshot'] ?? false)) { return round($liters * $basis['sale_rate'], 2); }
        $oldLiters = min($liters, max(0, $pricing['snapshot_electronic_reading'] - $basis['opening_electronic']));
        return round($oldLiters * $pricing['old_rate'] + ($liters - $oldLiters) * $pricing['new_rate'], 2);
    }
}
