<?php

namespace App\Modules\Accounting\Actions\Journal;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'transaction_date' => 'required|date',
            'posting_date' => 'nullable|date',
            'description' => 'nullable|string',
            'post' => 'nullable|boolean',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|uuid|exists:acct.accounts,id',
            'entries.*.type' => 'required|in:debit,credit',
            'entries.*.amount' => 'required|numeric|min:0.01',
            'entries.*.description' => 'nullable|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $userId = Auth::id();

        $transactionDate = $params['transaction_date'];
        $postingDate = $params['posting_date'] ?? $transactionDate;

        $period = AccountingPeriod::where('company_id', $company->id)
            ->where('is_closed', false)
            ->where('start_date', '<=', $transactionDate)
            ->where('end_date', '>=', $transactionDate)
            ->first();

        if (!$period) {
            throw new \RuntimeException('No open accounting period for this date');
        }

        $fiscalYearId = $period->fiscal_year_id;

        $debitTotal = 0;
        $creditTotal = 0;
        foreach ($params['entries'] as $entry) {
            if ($entry['type'] === 'debit') {
                $debitTotal += (float) $entry['amount'];
            } else {
                $creditTotal += (float) $entry['amount'];
            }
        }

        if (abs($debitTotal - $creditTotal) > 0.0001) {
            throw new \RuntimeException('Journal must balance (debits = credits)');
        }

        $postNow = (bool) ($params['post'] ?? false);

        $transaction = DB::transaction(function () use (
            $params,
            $company,
            $period,
            $fiscalYearId,
            $postingDate,
            $debitTotal,
            $creditTotal,
            $userId,
            $postNow
        ) {
            $transactionNumber = Transaction::generateJournalNumber($company->id);

            $transaction = Transaction::create([
                'company_id' => $company->id,
                'transaction_number' => $transactionNumber,
                'transaction_type' => 'manual',
                'transaction_date' => $params['transaction_date'],
                'posting_date' => $postingDate,
                'fiscal_year_id' => $fiscalYearId,
                'period_id' => $period->id,
                'description' => $params['description'] ?? null,
                'currency' => $company->base_currency,
                'base_currency' => $company->base_currency,
                'exchange_rate' => null,
                'total_debit' => $debitTotal,
                'total_credit' => $creditTotal,
                'status' => $postNow ? 'posted' : 'draft',
                'posted_at' => $postNow ? now() : null,
                'posted_by_user_id' => $postNow ? $userId : null,
                'created_by_user_id' => $userId,
            ]);

            foreach ($params['entries'] as $idx => $entry) {
                // Validate account belongs to company (RLS would block but be explicit)
                Account::where('company_id', $company->id)->findOrFail($entry['account_id']);

                JournalEntry::create([
                    'company_id' => $company->id,
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry['account_id'],
                    'line_number' => $idx + 1,
                    'description' => $entry['description'] ?? null,
                    'debit_amount' => $entry['type'] === 'debit' ? $entry['amount'] : 0,
                    'credit_amount' => $entry['type'] === 'credit' ? $entry['amount'] : 0,
                    'currency_debit' => null,
                    'currency_credit' => null,
                    'exchange_rate' => null,
                    'reference_type' => null,
                    'reference_id' => null,
                    'dimension_1' => null,
                    'dimension_2' => null,
                    'dimension_3' => null,
                ]);
            }

            return $transaction;
        });

        return [
            'message' => "Journal {$transaction->transaction_number} created" . ($postNow ? ' and posted' : ''),
            'data' => [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'status' => $transaction->status,
            ],
        ];
    }
}
