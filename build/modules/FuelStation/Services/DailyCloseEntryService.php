<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\DB;

/** Entry convenience for the reconciliation hub; uses the existing Accounting journal. */
class DailyCloseEntryService
{
    public function expense(string $companyId, string $date, array $expense): Transaction
    {
        return DB::transaction(function () use ($companyId, $date, $expense) {
            $account = Account::where('company_id', $companyId)->where('is_active', true)
                ->where('type', 'expense')->findOrFail($expense['account_id']);
            $cashId = app(DailyCloseService::class)->cashAccountId($companyId);
            $amount = round((float) $expense['amount'], 2);
            if ($amount <= 0 || !$cashId) {
                throw new \InvalidArgumentException('A positive amount and a configured cash account are required.');
            }
            $currency = \App\Models\Company::whereKey($companyId)->value('base_currency') ?: 'PKR';
            return app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $companyId, 'transaction_type' => 'expense', 'date' => $date,
                'currency' => $currency, 'description' => $expense['description'],
                'reference_type' => 'fuel.daily_close_expense',
            ], [
                ['account_id' => $account->id, 'type' => 'debit', 'amount' => $amount, 'description' => $expense['description']],
                ['account_id' => $cashId, 'type' => 'credit', 'amount' => $amount, 'description' => $expense['description']],
            ]);
        });
    }
}
