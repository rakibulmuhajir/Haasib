<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Umrah\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TravelExpenseService
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
        private readonly PostingService $postingService,
    ) {}

    public function record(Company $company, array $data, ?string $userId): Expense
    {
        return DB::transaction(function () use ($company, $data, $userId) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['umrah-expense-'.$company->id]);

            $currency = strtoupper($data['currency']);
            $exchangeRate = $currency === $company->base_currency ? null : (float) $data['exchange_rate'];
            $amount = round((float) $data['amount'], 6);
            $baseAmount = round($amount * ($exchangeRate ?? 1), 2);

            $expense = Expense::create([
                'company_id' => $company->id,
                'expense_number' => $data['expense_number'] ?: $this->nextNumber($company->id),
                'expense_date' => $data['expense_date'],
                'expense_account_id' => $data['expense_account_id'],
                'payment_account_id' => $data['payment_account_id'],
                'payee' => $data['payee'] ?? null,
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $company->base_currency,
                'base_amount' => $baseAmount,
                'status' => Expense::STATUS_POSTED,
                'created_by_user_id' => $userId,
            ]);

            $transaction = $this->glPostingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => 'expense',
                'date' => $expense->expense_date,
                'currency' => $currency,
                'base_currency' => $company->base_currency,
                'exchange_rate' => $exchangeRate,
                'description' => "Travel expense {$expense->expense_number}: {$expense->description}",
                'reference_type' => 'umrah.expenses',
                'reference_id' => $expense->id,
                'metadata' => ['expense_number' => $expense->expense_number, 'payee' => $expense->payee, 'reference' => $expense->reference],
            ], [
                ['account_id' => $expense->expense_account_id, 'type' => 'debit', 'amount' => $amount, 'description' => $expense->description],
                ['account_id' => $expense->payment_account_id, 'type' => 'credit', 'amount' => $amount, 'description' => $expense->description],
            ]);

            $expense->update(['transaction_id' => $transaction->id]);

            return $expense->fresh(['expenseAccount', 'paymentAccount', 'transaction']);
        });
    }

    public function reverse(Expense $expense, string $reason, ?string $userId): Expense
    {
        return DB::transaction(function () use ($expense, $reason, $userId) {
            $expense = Expense::query()
                ->where('company_id', $expense->company_id)
                ->lockForUpdate()
                ->findOrFail($expense->id);

            if ($expense->status !== Expense::STATUS_POSTED) {
                throw ValidationException::withMessages(['expense' => 'This expense has already been reversed.']);
            }

            $transaction = $expense->transaction_id
                ? Transaction::query()->where('company_id', $expense->company_id)->find($expense->transaction_id)
                : null;

            if (! $transaction) {
                throw ValidationException::withMessages(['expense' => 'The accounting entry for this expense was not found.']);
            }

            $reversal = $this->postingService->reverseTransaction($transaction, $reason, Carbon::today());
            $expense->update([
                'status' => Expense::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by_user_id' => $userId,
                'reversal_reason' => $reason,
                'reversal_transaction_id' => $reversal->id,
            ]);

            return $expense->fresh();
        });
    }

    private function nextNumber(string $companyId): string
    {
        $latest = Expense::query()
            ->where('company_id', $companyId)
            ->where('expense_number', 'like', 'UEX-%')
            ->orderByDesc('expense_number')
            ->value('expense_number');
        $next = is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)
            ? ((int) $matches[1]) + 1
            : 1;

        return sprintf('UEX-%05d', $next);
    }
}
