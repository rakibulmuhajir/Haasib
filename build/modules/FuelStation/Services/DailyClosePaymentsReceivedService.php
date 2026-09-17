<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Validation\ValidationException;

/**
 * Buyer payments settling a credit invoice, entered inline inside the Daily Close instead
 * of at /payments. Every row becomes a canonical payment through the existing
 * Payment\CreateAction (never a parallel posting path): a payment into a cash account
 * raises expected drawer cash exactly like a standalone one would; a payment into a bank
 * account never touches the drawer.
 */
class DailyClosePaymentsReceivedService
{
    public function prepare(string $companyId, string $date, array $rows, User $user): array
    {
        if (!$rows) {
            return [];
        }

        $company = Company::whereKey($companyId)->firstOrFail();
        $details = [];

        foreach ($rows as $index => $row) {
            if (empty($row['invoice_id']) || empty($row['payment_account_id']) || empty($row['amount'])) {
                throw ValidationException::withMessages(["payments_received.{$index}" => 'A payment row must have an invoice, an amount and an account received into.']);
            }

            $invoice = Invoice::where('company_id', $companyId)->find($row['invoice_id']);
            if (!$invoice) {
                throw ValidationException::withMessages(["payments_received.{$index}.invoice_id" => 'Choose an invoice belonging to this company.']);
            }

            $account = Account::where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')
                ->whereIn('subtype', ['cash', 'bank'])->find($row['payment_account_id']);
            if (!$account) {
                throw ValidationException::withMessages(["payments_received.{$index}.payment_account_id" => 'Choose a cash or bank account belonging to this company.']);
            }

            $amount = round((float) $row['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(["payments_received.{$index}.amount" => 'Amount must be greater than zero.']);
            }
            if ($amount > round((float) $invoice->balance, 2)) {
                throw ValidationException::withMessages([
                    "payments_received.{$index}.amount" => "Amount cannot exceed invoice {$invoice->invoice_number}'s outstanding balance.",
                ]);
            }

            $arAccountId = $invoice->customer?->ar_account_id ?: $company->default_ar_account_id;

            $result = app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('payment.create', [
                'invoice' => $invoice->id,
                'amount' => $amount,
                'method' => $account->subtype === 'cash' ? 'cash' : 'bank_transfer',
                'date' => $date,
                'reference' => $row['reference'] ?? null,
                'deposit_account_id' => $account->id,
                'ar_account_id' => $arAccountId,
            ], $user, true));

            $details[] = [
                'payment_id' => $result['data']['id'],
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $row['customer_name'] ?? $invoice->customer?->name,
                'amount' => $amount,
                'payment_account_id' => $account->id,
                'payment_account_name' => trim(($account->code ? $account->code.' — ' : '').$account->name),
                'affects_cash_drawer' => $account->subtype === 'cash',
                'reference' => $row['reference'] ?? null,
            ];
        }

        return $details;
    }
}
