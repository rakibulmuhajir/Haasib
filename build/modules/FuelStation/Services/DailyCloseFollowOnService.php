<?php

namespace App\Modules\FuelStation\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * After a posted day is edited, the next posted day may still open from the old closing figures.
 *
 * Every day's closing is measured afresh (cash counted, tanks dipped, meters read), so a mistake
 * on one day moves figures between that day and the next one only: a false short on one is a
 * false over on the other. The days after that open from the next day's own count and are not
 * affected -- except fuel cost, which a wrong delivery carries forward until the stock is sold,
 * and which is re-costed, not re-posted.
 *
 * So refreshing is: re-post the next day with its openings taken from this day (cash, meters;
 * tank openings follow the dips by themselves), every other entry as it was, and re-cost the
 * days after it.
 */
class DailyCloseFollowOnService
{
    public function __construct(
        private readonly DailyCloseReopenService $reopen,
        private readonly DailyCloseReconciliationService $drafts,
        private readonly DailyCloseService $closes,
        private readonly DailyCloseCostCorrectionService $costs,
    ) {}

    /**
     * What the next posted day opened from that no longer matches this day's closing.
     *
     * @return array{next_id:string,next_date:string,changes:array<int,array{what:string,was:float,now:float}>}|null
     */
    public function nextDayDrift(Transaction $close): ?array
    {
        $companyId = $close->company_id;
        $date = $close->transaction_date->toDateString();
        $next = $this->nextClose($companyId, $date);
        if (! $next) {
            return null;
        }
        $form = $next->metadata['form_input'] ?? [];
        $changes = [];

        $closingCash = round((float) ($close->metadata['closing_cash'] ?? 0), 2);
        $openingCash = round((float) ($form['opening_cash'] ?? 0), 2);
        if (abs($closingCash - $openingCash) >= 0.01) {
            $changes[] = ['what' => 'Opening cash', 'was' => $openingCash, 'now' => $closingCash];
        }

        $closingMeters = $this->closingMeters($companyId, $date);
        $labels = DB::table('fuel.nozzles as n')->leftJoin('fuel.pumps as p', 'p.id', '=', 'n.pump_id')
            ->where('n.company_id', $companyId)->get(['n.id', 'n.code', 'p.name as pump'])->keyBy('id');
        foreach ($form['nozzle_readings'] ?? [] as $reading) {
            $closing = $closingMeters[$reading['nozzle_id'] ?? ''] ?? null;
            if ($closing === null) {
                continue;
            }
            $opening = round((float) ($reading['opening_electronic'] ?? 0), 2);
            if (abs($closing->closing_electronic - $opening) >= 0.005) {
                $label = $labels[$reading['nozzle_id']] ?? null;
                $changes[] = ['what' => trim(($label->pump ?? 'Pump').' '.($label->code ?? '')).' opening meter',
                    'was' => $opening, 'now' => round((float) $closing->closing_electronic, 2)];
            }
        }

        // Tank openings are the previous day's dips, read when the next day was posted.
        $postedAfter = $next->created_at && $close->created_at && $next->created_at < $close->created_at;
        if ($postedAfter) {
            $before = $this->previousRevisionTanks($companyId, $date);
            foreach ($close->metadata['posting_snapshot']['tanks'] ?? [] as $tank) {
                $was = $before[$tank['tank_id'] ?? ''] ?? null;
                if ($was !== null && abs((float) $tank['physical_liters'] - $was) >= 0.5) {
                    $changes[] = ['what' => ($tank['tank_name'] ?? 'Tank').' opening dip', 'was' => $was, 'now' => (float) $tank['physical_liters']];
                }
            }
        }

        return $changes ? ['next_id' => $next->id, 'next_date' => $next->transaction_date->toDateString(), 'changes' => $changes] : null;
    }

    /**
     * Re-posts the next day with its openings taken from this day, then re-costs the days after.
     * All or nothing: if the next day cannot be re-posted it stays posted as it was.
     *
     * @return array{next_date:string,recosted:int,warnings:array<int,string>}
     */
    public function refreshNextDay(Transaction $close, User $user): array
    {
        $companyId = $close->company_id;
        $date = $close->transaction_date->toDateString();
        $next = $this->nextClose($companyId, $date) ?? throw new \RuntimeException('No posted day follows this one.');
        $nextDate = $next->transaction_date->toDateString();

        return DB::transaction(function () use ($close, $next, $companyId, $date, $nextDate, $user) {
            $reopened = $this->reopen->reopen($next, $user, "Openings refreshed from {$date}");
            $payload = $this->drafts->draft($companyId, $nextDate) ?? throw new \RuntimeException("{$nextDate} could not be reopened.");

            $payload['date'] = $nextDate;
            $payload['opening_cash'] = round((float) ($close->metadata['closing_cash'] ?? 0), 2);
            $closingMeters = $this->closingMeters($companyId, $date);
            foreach ($payload['nozzle_readings'] ?? [] as $i => $reading) {
                $closing = $closingMeters[$reading['nozzle_id'] ?? ''] ?? null;
                if ($closing === null) {
                    continue;
                }
                $payload['nozzle_readings'][$i]['opening_electronic'] = (float) $closing->closing_electronic;
                if ($closing->closing_manual !== null && array_key_exists('opening_manual', $reading)) {
                    $payload['nozzle_readings'][$i]['opening_manual'] = (float) $closing->closing_manual;
                }
            }
            unset($payload['intent']);

            $this->closes->processDailyClose($companyId, $payload, $user);

            // Fuel cost runs on past the next day (a delivery's cost stays in the stock).
            $recosted = 0;
            $later = Transaction::where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')
                ->where('status', 'posted')->whereNull('deleted_at')->whereNull('reversed_by_id')
                ->whereDate('transaction_date', '>', $nextDate)->orderBy('transaction_date')->get();
            foreach ($later as $day) {
                if ($this->costs->correct($day, true)['posted']) {
                    $recosted++;
                }
            }

            return ['next_date' => $nextDate, 'recosted' => $recosted, 'warnings' => $reopened['warnings'] ?? []];
        });
    }

    private function nextClose(string $companyId, string $date): ?Transaction
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereDate('transaction_date', '>', $date)
            ->orderBy('transaction_date')
            ->first();
    }

    private function closingMeters(string $companyId, string $date)
    {
        return DB::table('fuel.nozzle_readings')
            ->where('company_id', $companyId)
            ->whereDate('reading_date', $date)
            ->get(['nozzle_id', 'closing_electronic', 'closing_manual'])
            ->keyBy('nozzle_id');
    }

    /** @return array<string,float> tank id => the dip this day carried before its last edit */
    private function previousRevisionTanks(string $companyId, string $date): array
    {
        $snapshot = DB::table('fuel.daily_close_revisions')
            ->where('company_id', $companyId)
            ->where('business_date', $date)
            ->orderByDesc('created_at')
            ->value('snapshot');
        $tanks = json_decode((string) $snapshot, true)['posting_snapshot']['tanks'] ?? [];

        return collect($tanks)->mapWithKeys(fn ($t) => [$t['tank_id'] ?? '' => (float) ($t['physical_liters'] ?? 0)])->all();
    }
}
