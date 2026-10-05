<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\ProfitLossReportService;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Modules\FuelStation\Services\ProfitStatementService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Shares one month's profit between the active partners, by their profit share %.
 *
 *  profit: Dr Retained Earnings, Cr each partner's Capital account
 *  loss:   Dr each partner's Capital account, Cr Retained Earnings
 *
 * dated the month's last day. Net profit is the books' own: the station's profit statement for a
 * fuel company, the ledger P&L otherwise. Shares that do not total 100% leave the remainder
 * unallocated (reported, never posted). Running it again for the same month reverses the
 * previous live journal (on its own date) and posts the new one; nothing changes when the
 * figures are the same. Once that month's daily closes are locked, an existing share can no
 * longer be replaced.
 */
class PartnerProfitShareService
{
    public const TYPE = 'partner_profit_share';

    /**
     * What sharing the month would do, without writing anything.
     *
     * @return array<string,mixed>
     */
    public function preview(string $companyId, string $month): array
    {
        [$from, $to] = $this->range($month);
        $net = $this->netProfit($companyId, $from, $to);
        $partners = Partner::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        $rows = [];
        $allocated = 0.0;
        foreach ($partners as $partner) {
            $percent = (float) $partner->profit_share_percentage;
            $amount = round($net * $percent / 100, 2);
            $allocated += $amount;
            $rows[] = ['partner_id' => $partner->id, 'name' => $partner->name, 'percent' => $percent, 'amount' => $amount];
        }
        $allocated = round($allocated, 2);

        $existing = $this->live($companyId, $month);
        $unchanged = $existing && $this->sameAllocations($existing, $rows);

        return [
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'net_profit' => $net,
            'partners' => $rows,
            'allocated' => $allocated,
            'unallocated' => round($net - $allocated, 2),
            'shared_before' => (bool) $existing,
            'unchanged' => (bool) $unchanged,
            'locked' => $this->monthLocked($companyId, $to),
            'message' => $unchanged ? 'Already shared; nothing changed' : 'Profit shared',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function share(string $companyId, string $month): array
    {
        $plan = $this->preview($companyId, $month);
        if ($plan['unchanged']) {
            return $plan;
        }
        $existing = $this->live($companyId, $month);
        if ($existing && $plan['locked']) {
            throw ValidationException::withMessages(['month' => "{$month} is locked. Its profit share can no longer change."]);
        }
        $rows = array_values(array_filter($plan['partners'], fn ($r) => abs($r['amount']) >= 0.005));
        $company = Company::findOrFail($companyId);

        return AccountingWriteTransaction::run(function () use ($plan, $existing, $rows, $company, $month) {
            if ($existing) {
                app(PostingService::class)->reverseTransaction($existing, "Profit share {$month} shared again", $plan['to']);
                PartnerTransaction::where('company_id', $company->id)->where('transaction_type', 'profit_share')
                    ->where('reference', $month)->delete();
            }
            if (! $rows) {
                return $plan + ['transaction_id' => null];
            }

            $ledger = app(PartnerLedgerService::class);
            $entries = [];
            $capitalOf = [];
            $total = 0.0;
            foreach ($rows as $row) {
                $partner = Partner::findOrFail($row['partner_id']);
                $capital = $ledger->accountsFor($partner)['capital'];
                $capitalOf[$row['partner_id']] = $capital->id;
                $total += $row['amount'];
                $entries[] = [
                    'account_id' => $capital->id,
                    'type' => $row['amount'] > 0 ? 'credit' : 'debit',
                    'amount' => abs($row['amount']),
                    'description' => "Profit share {$month} - {$partner->name}",
                ];
            }
            // The offset: what the capital lines net to (partners' shares add up to the same figure).
            if (abs(round($total, 2)) >= 0.005) {
                $entries[] = [
                    'account_id' => $this->retainedEarnings($company->id)->id,
                    'type' => $total > 0 ? 'debit' : 'credit',
                    'amount' => abs(round($total, 2)),
                    'description' => "Profit shared to partners {$month}",
                ];
            }

            $currency = $company->base_currency ?: 'PKR';
            $gl = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => self::TYPE,
                'date' => $plan['to'],
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Partner profit share {$month}",
                'reference_type' => 'auth.partners',
                'metadata' => [
                    'month' => $month,
                    'net_profit' => $plan['net_profit'],
                    'allocations' => collect($rows)->mapWithKeys(fn ($r) => [$r['partner_id'] => $r['amount']])->all(),
                ],
            ], $entries);

            foreach ($rows as $row) {
                PartnerTransaction::create([
                    'company_id' => $company->id,
                    'partner_id' => $row['partner_id'],
                    'transaction_date' => $plan['to'],
                    'transaction_type' => 'profit_share',
                    'amount' => $row['amount'],
                    'description' => "Profit share {$month}",
                    'reference' => $month,
                    'gl_transaction_id' => $gl->id,
                    'journal_entry_id' => $gl->journalEntries()->where('account_id', $capitalOf[$row['partner_id']])->value('id'),
                    'recorded_by_user_id' => auth()->id(),
                ]);
            }

            return $plan + ['transaction_id' => $gl->id];
        });
    }

    /** The books' net profit for the range. */
    public function netProfit(string $companyId, string $from, string $to): float
    {
        $company = Company::findOrFail($companyId);
        if ($company->industry === 'fuel_station' || $company->isModuleEnabled('fuel_station')) {
            return round((float) app(ProfitStatementService::class)->run($companyId, $from, $to, (string) $company->slug)['net_profit'], 2);
        }

        return round((float) app(ProfitLossReportService::class)->run($companyId, $from, $to)['totals']['profit'], 2);
    }

    /** Retained earnings: the company's setting, else 3100, else a retained_earnings account, else created. */
    public function retainedEarnings(string $companyId): Account
    {
        $company = Company::findOrFail($companyId);
        $account = $company->retained_earnings_account_id ? Account::where('company_id', $companyId)->find($company->retained_earnings_account_id) : null;
        $account ??= Account::where('company_id', $companyId)->where('code', '3100')->first();
        $account ??= Account::where('company_id', $companyId)->where('subtype', 'retained_earnings')->where('is_active', true)->orderBy('code')->first();
        if ($account) {
            if (! $account->is_active) {
                $account->update(['is_active' => true]);
            }

            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '3100',
            'name' => 'Retained Earnings',
            'type' => 'equity',
            'subtype' => 'retained_earnings',
            'normal_balance' => 'credit',
            'description' => 'Accumulated profit; partner profit shares are taken from it',
            'is_active' => true,
        ]);
    }

    /** @return array{0:string,1:string} first and last day of 'YYYY-MM' */
    private function range(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    private function live(string $companyId, string $month): ?Transaction
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', self::TYPE)
            ->whereRaw("metadata->>'month' = ?", [$month])
            ->whereNull('reversal_of_id')
            ->whereNull('reversed_by_id')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function sameAllocations(Transaction $existing, array $rows): bool
    {
        $old = collect($existing->metadata['allocations'] ?? [])->map(fn ($a) => round((float) $a, 2))->filter(fn ($a) => abs($a) >= 0.005);
        $new = collect($rows)->mapWithKeys(fn ($r) => [$r['partner_id'] => round($r['amount'], 2)])->filter(fn ($a) => abs($a) >= 0.005);

        return $old->sortKeys()->all() === $new->sortKeys()->all();
    }

    private function monthLocked(string $companyId, string $date): bool
    {
        return app(DailyCloseLockService::class)->isDateInLockedMonth($companyId, $date);
    }
}
