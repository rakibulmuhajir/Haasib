<?php

namespace App\Modules\Accounting\Actions\Expense;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Validation\ValidationException;

/**
 * A standalone expense: Dr the chosen expense account, Cr the cash or bank account it was
 * paid from. Posted as an ordinary journal (transaction_type 'expense') through the same
 * GlPostingService::postBalancedTransaction every other canonical transaction uses, so the
 * Daily Close picks it up automatically for its date — no parallel mechanism, no new table.
 */
class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'account_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
            'paid_from_account_id' => 'required|uuid',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::EXPENSE_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $companyId = $company->id;

        $expenseAccount = Account::where('company_id', $companyId)->where('is_active', true)
            ->whereNull('deleted_at')->where('type', 'expense')->find($params['account_id']);
        if (!$expenseAccount) {
            throw ValidationException::withMessages(['account_id' => 'Choose an expense account belonging to this company.']);
        }

        $paidFrom = Account::where('company_id', $companyId)->where('is_active', true)
            ->whereNull('deleted_at')->whereIn('subtype', ['cash', 'bank'])->find($params['paid_from_account_id']);
        if (!$paidFrom) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Choose a cash or bank account belonging to this company.']);
        }

        $amount = round((float) $params['amount'], 2);
        $currency = $company instanceof Company ? ($company->base_currency ?: 'PKR') : 'PKR';

        $transaction = app(GlPostingService::class)->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => 'expense',
            'date' => $params['date'],
            'currency' => $currency,
            'description' => $params['description'],
            'reference_type' => 'acct.expenses',
        ], [
            ['account_id' => $expenseAccount->id, 'type' => 'debit', 'amount' => $amount, 'description' => $params['description']],
            ['account_id' => $paidFrom->id, 'type' => 'credit', 'amount' => $amount, 'description' => $params['reference'] ?? $params['description']],
        ]);

        return [
            'message' => 'Expense recorded: '.$transaction->transaction_number,
            'data' => ['id' => $transaction->id, 'transaction_number' => $transaction->transaction_number],
        ];
    }
}
