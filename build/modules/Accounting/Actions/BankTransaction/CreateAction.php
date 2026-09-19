<?php

namespace App\Modules\Accounting\Actions\BankTransaction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Validation\ValidationException;

/**
 * Manual bank movements (deposit, withdrawal, transfer, bank charge), posted through the
 * same GlPostingService::postBankTransaction/postBankTransfer paths the bank-feed
 * resolution flow already uses — these are ordinary journals seen from a second entry
 * point, never a parallel posting mechanism.
 */
class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'kind' => 'required|in:deposit,withdrawal,transfer,charge',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            // deposit: cash -> bank. withdrawal: bank -> cash.
            'cash_account_id' => 'required_if:kind,deposit,withdrawal|nullable|uuid',
            // deposit/withdrawal/charge target bank account; transfer's own two ends below.
            'bank_account_id' => 'required_if:kind,deposit,withdrawal,charge|nullable|uuid',
            'from_bank_account_id' => 'required_if:kind,transfer|nullable|uuid',
            'to_bank_account_id' => 'required_if:kind,transfer|nullable|uuid',
            'expense_account_id' => 'required_if:kind,charge|nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BANK_ACCOUNT_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $companyId = $company->id;
        $amount = round((float) $params['amount'], 2);
        $date = $params['date'];
        $reference = $params['reference'] ?? null;
        $description = $reference ?: ($params['notes'] ?? null);

        $account = function (string $id, array $subtypes) use ($companyId) {
            $acc = Account::where('company_id', $companyId)->where('is_active', true)
                ->whereNull('deleted_at')->whereIn('subtype', $subtypes)->find($id);
            if (! $acc && in_array('bank', $subtypes, true)) {
                $acc = Account::where('company_id', $companyId)->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereKey(BankAccount::where('company_id', $companyId)
                        ->where('is_active', true)->whereNull('deleted_at')
                        ->where('account_type', '!=', 'cash')->pluck('gl_account_id'))
                    ->find($id);
            }
            if (! $acc) {
                throw ValidationException::withMessages(['account' => 'Choose an account belonging to this company.']);
            }

            return $acc;
        };

        $posting = app(GlPostingService::class);

        switch ($params['kind']) {
            case 'deposit':
                // Cash -> Bank: Dr Bank, Cr Cash on Hand.
                $bank = $account($params['bank_account_id'], ['bank']);
                $cash = $account($params['cash_account_id'], ['cash']);
                $transaction = $posting->postBankTransaction([
                    'company_id' => $companyId, 'date' => $date, 'currency' => $bank->currency ?: $company->base_currency,
                    'amount' => $amount, 'description' => $description ?? 'Cash deposited to bank', 'bank_account_id' => $bank->id,
                ], [['account_id' => $cash->id, 'amount' => $amount, 'description' => $description]]);
                break;

            case 'withdrawal':
                // Bank -> Cash: Dr Cash on Hand, Cr Bank.
                $bank = $account($params['bank_account_id'], ['bank']);
                $cash = $account($params['cash_account_id'], ['cash']);
                $transaction = $posting->postBankTransaction([
                    'company_id' => $companyId, 'date' => $date, 'currency' => $bank->currency ?: $company->base_currency,
                    'amount' => -$amount, 'description' => $description ?? 'Cash withdrawn from bank', 'bank_account_id' => $bank->id,
                ], [['account_id' => $cash->id, 'amount' => $amount, 'description' => $description]]);
                break;

            case 'transfer':
                // Bank -> Bank: Dr destination, Cr source.
                $from = $account($params['from_bank_account_id'], ['bank']);
                $to = $account($params['to_bank_account_id'], ['bank']);
                if ($from->id === $to->id) {
                    throw ValidationException::withMessages(['to_bank_account_id' => 'Choose a different destination account.']);
                }
                $transaction = $posting->postBankTransfer([
                    'company_id' => $companyId, 'date' => $date, 'currency' => $from->currency ?: $company->base_currency,
                    'amount' => $amount, 'from_account_id' => $from->id, 'to_account_id' => $to->id,
                    'description' => $description,
                ]);
                break;

            case 'charge':
                // Bank charge/fee: Dr Bank Charges expense, Cr Bank.
                $bank = $account($params['bank_account_id'], ['bank']);
                $expense = Account::where('company_id', $companyId)->where('is_active', true)
                    ->whereNull('deleted_at')->where('type', 'expense')->find($params['expense_account_id']);
                if (! $expense) {
                    throw ValidationException::withMessages(['expense_account_id' => 'Choose an expense account belonging to this company.']);
                }
                $transaction = $posting->postBankTransaction([
                    'company_id' => $companyId, 'date' => $date, 'currency' => $bank->currency ?: $company->base_currency,
                    'amount' => -$amount, 'description' => $description ?? 'Bank charge', 'bank_account_id' => $bank->id,
                ], [['account_id' => $expense->id, 'amount' => $amount, 'description' => $description]]);
                break;

            default:
                throw ValidationException::withMessages(['kind' => 'Unknown bank transaction kind.']);
        }

        return [
            'message' => 'Bank transaction recorded: '.$transaction->transaction_number,
            'data' => ['id' => $transaction->id, 'transaction_number' => $transaction->transaction_number],
        ];
    }
}
