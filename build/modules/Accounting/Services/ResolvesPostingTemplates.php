<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared by PostingService and TicketPostingService. A ticket posting is
 * not a kind of invoice posting -- extending PostingService would suggest
 * it is -- so this is a trait rather than a base class, holding the
 * mechanics both services need to turn a set of debit/credit entries into
 * a real, balanced GL transaction: template resolution, the open-period
 * check, the balance assertion and the transaction/journal-entry write.
 * What differs between the two services is only how the entries are built
 * (buildInvoiceEntries/buildBillEntries vs. the ticket role postings),
 * which stays in each service.
 */
trait ResolvesPostingTemplates
{
    protected function resolveTemplate(string $companyId, string $docType, Carbon|string $date): PostingTemplate
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);

        $template = PostingTemplate::where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->where('is_active', true)
            ->where('is_default', true)
            ->whereDate('effective_from', '<=', $dateObj->toDateString())
            ->where(function ($q) use ($dateObj) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $dateObj->toDateString());
            })
            ->whereNull('deleted_at')
            ->with(['lines'])
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();

        if (! $template) {
            throw new \RuntimeException("No active default posting template for {$docType}. Configure posting templates for this company.");
        }

        return $template;
    }

    /**
     * @return array<string, string>
     */
    protected function roleAccounts(PostingTemplate $template): array
    {
        $map = [];
        foreach ($template->lines as $line) {
            $map[$line->role] = $line->account_id;
        }
        return $map;
    }

    /**
     * @param array<int, array{type:'debit'|'credit',amount:float}> $entries
     */
    protected function assertBalanced(array $entries): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($entries as $entry) {
            if ($entry['type'] === 'debit') $debit += (float) $entry['amount'];
            if ($entry['type'] === 'credit') $credit += (float) $entry['amount'];
        }
        if (abs(round($debit, 2) - round($credit, 2)) >= 0.01) {
            throw new \RuntimeException('Posting preview out of balance; check template mappings and document totals.');
        }
    }

    /**
     * @param array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}> $entries
     */
    protected function createTransaction(array $data, array $entries): Transaction
    {
        $currency = $data['currency'];
        $baseCurrency = $data['base_currency'] ?? $currency;
        $exchangeRate = $data['exchange_rate'] ?? null;

        if ($currency !== $baseCurrency && $exchangeRate === null) {
            throw new \RuntimeException('exchange_rate is required when currency differs from base_currency.');
        }

        $isForeign = $currency !== $baseCurrency;

        $computed = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach (array_values($entries) as $index => $entry) {
            $currencyAmount = (float) $entry['amount'];
            $baseAmount = round($currencyAmount * ($exchangeRate ?? 1), 2);

            $computed[$index] = [
                ...$entry,
                'currency_amount' => $currencyAmount,
                'base_amount' => $baseAmount,
            ];

            if ($entry['type'] === 'debit') {
                $debitTotal += $baseAmount;
            } else {
                $creditTotal += $baseAmount;
            }
        }

        $diff = round($debitTotal - $creditTotal, 2);
        if ($diff !== 0.0) {
            if (abs($diff) > 0.01) {
                throw new \RuntimeException('Transaction not balanced in base currency; debits must equal credits.');
            }

            // FX rounding adjustment: bump the last line on the smaller side to keep the journal balanced in base.
            if ($diff > 0) {
                $this->applyBaseAdjustment($computed, 'credit', $diff);
                $creditTotal += $diff;
            } else {
                $amount = abs($diff);
                $this->applyBaseAdjustment($computed, 'debit', $amount);
                $debitTotal += $amount;
            }
        }

        return DB::transaction(function () use ($data, $entries, $debitTotal, $creditTotal) {
            $transaction = Transaction::create([
                'company_id' => $data['company_id'],
                'transaction_number' => $data['transaction_number'],
                'transaction_type' => $data['transaction_type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'posting_date' => $data['posting_date'] ?? $data['transaction_date'],
                'fiscal_year_id' => $data['fiscal_year_id'],
                'period_id' => $data['period_id'],
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'],
                'base_currency' => $data['base_currency'] ?? $data['currency'],
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'total_debit' => $debitTotal,
                'total_credit' => $creditTotal,
                'status' => 'posted',
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'posted_at' => now(),
                'posted_by_user_id' => Auth::id(),
                'created_by_user_id' => Auth::id(),
            ]);

            $currency = $data['currency'];
            $baseCurrency = $data['base_currency'] ?? $currency;
            $exchangeRate = $data['exchange_rate'] ?? null;
            $isForeign = $currency !== $baseCurrency;

            $computed = [];
            foreach (array_values($entries) as $idx => $entry) {
                $currencyAmount = (float) $entry['amount'];
                $computed[$idx] = [
                    ...$entry,
                    'currency_amount' => $currencyAmount,
                    'base_amount' => round($currencyAmount * ($exchangeRate ?? 1), 2),
                ];
            }
            $diff = round(array_sum(array_map(fn ($e) => $e['type'] === 'debit' ? $e['base_amount'] : 0.0, $computed))
                - array_sum(array_map(fn ($e) => $e['type'] === 'credit' ? $e['base_amount'] : 0.0, $computed)), 2);
            if ($diff !== 0.0 && abs($diff) <= 0.01) {
                if ($diff > 0) {
                    $this->applyBaseAdjustment($computed, 'credit', $diff);
                } else {
                    $this->applyBaseAdjustment($computed, 'debit', abs($diff));
                }
            }

            foreach ($computed as $index => $entry) {
                JournalEntry::create([
                    'company_id' => $data['company_id'],
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry['account_id'],
                    'line_number' => $index + 1,
                    'description' => $entry['description'] ?? null,
                    'debit_amount' => $entry['type'] === 'debit' ? $entry['base_amount'] : 0,
                    'credit_amount' => $entry['type'] === 'credit' ? $entry['base_amount'] : 0,
                    'currency_debit' => ($isForeign && $entry['type'] === 'debit') ? $entry['currency_amount'] : null,
                    'currency_credit' => ($isForeign && $entry['type'] === 'credit') ? $entry['currency_amount'] : null,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'reference_type' => $data['reference_type'] ?? null,
                    'reference_id' => $data['reference_id'] ?? null,
                    'dimension_1' => null,
                    'dimension_2' => null,
                    'dimension_3' => null,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * @param array<int, array{type:'debit'|'credit',base_amount:float}> $entries
     */
    protected function applyBaseAdjustment(array &$entries, string $side, float $amount): void
    {
        for ($i = count($entries) - 1; $i >= 0; $i--) {
            if ($entries[$i]['type'] !== $side) {
                continue;
            }

            $new = round(((float) $entries[$i]['base_amount']) + $amount, 2);
            if ($new <= 0.0) {
                continue;
            }

            $entries[$i]['base_amount'] = $new;
            return;
        }

        throw new \RuntimeException('Unable to apply FX rounding adjustment; no suitable journal line found.');
    }

    protected function resolveOpenPeriod(string $companyId, Carbon|string $date): AccountingPeriod
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);

        $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
            ->where('acct.accounting_periods.company_id', $companyId)
            ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
            ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
            ->where('acct.accounting_periods.is_closed', false)
            ->where('acct.fiscal_years.is_closed', false)
            ->select('acct.accounting_periods.*')
            ->first();

        if (! $period) {
            $fiscalYearService = app(FiscalYearService::class);
            $company = Company::find($companyId);

            if ($company && $company->getAutoCreateFiscalYear()) {
                $fiscalYearService->ensureCurrentFiscalYearExists($companyId, $dateObj);
                $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
                    ->where('acct.accounting_periods.company_id', $companyId)
                    ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
                    ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
                    ->where('acct.accounting_periods.is_closed', false)
                    ->where('acct.fiscal_years.is_closed', false)
                    ->select('acct.accounting_periods.*')
                    ->first();
            }

            if (! $period) {
                $message = "No open accounting period for {$dateObj->toDateString()}. Please ensure a fiscal year and accounting periods are set up.";
                Log::warning($message, [
                    'company_id' => $companyId,
                    'date' => $dateObj->toDateString(),
                    'auto_create_fiscal_year' => $company?->getAutoCreateFiscalYear(),
                ]);
                throw new \RuntimeException($message);
            }
        }

        return $period;
    }
}
