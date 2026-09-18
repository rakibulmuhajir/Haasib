<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
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
 *
 * A row names a buyer and an amount; which invoice(s) it settles is optional:
 *  - `invoice_id` alone: settle that one invoice (any amount beyond its balance now goes
 *    on account rather than being rejected - see Payment\CreateAction).
 *  - `invoice_ids`: settle several hand-picked invoices, auto-split oldest-first.
 *  - neither: auto-allocate across every open invoice for that buyer, oldest first: a
 *    row naming a buyer with nothing selected is a valid on-account/advance payment.
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
            if (empty($row['customer_id']) || empty($row['payment_account_id']) || empty($row['amount'])) {
                throw ValidationException::withMessages(["payments_received.{$index}" => 'A payment row must have a buyer, an amount and an account received into.']);
            }

            $customer = Customer::where('company_id', $companyId)->find($row['customer_id']);
            if (!$customer) {
                throw ValidationException::withMessages(["payments_received.{$index}.customer_id" => 'Choose a buyer belonging to this company.']);
            }

            $invoiceIds = [];
            if (!empty($row['invoice_id'])) {
                $invoiceIds = [$row['invoice_id']];
            } elseif (!empty($row['invoice_ids'])) {
                $invoiceIds = $row['invoice_ids'];
            }
            $invoiceNumbers = [];
            foreach ($invoiceIds as $invoiceId) {
                $invoice = Invoice::where('company_id', $companyId)->where('customer_id', $customer->id)->find($invoiceId);
                if (!$invoice) {
                    throw ValidationException::withMessages(["payments_received.{$index}.invoice_id" => 'Choose an invoice belonging to this buyer and company.']);
                }
                $invoiceNumbers[] = $invoice->invoice_number;
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

            $arAccountId = $customer->ar_account_id ?: $company->default_ar_account_id;

            $result = app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('payment.create', [
                'customer_id' => $customer->id,
                'invoice' => count($invoiceIds) === 1 ? $invoiceIds[0] : null,
                'invoice_ids' => count($invoiceIds) > 1 ? $invoiceIds : null,
                'amount' => $amount,
                'method' => $account->subtype === 'cash' ? 'cash' : 'bank_transfer',
                'date' => $date,
                'reference' => $row['reference'] ?? null,
                'deposit_account_id' => $account->id,
                'ar_account_id' => $arAccountId,
            ], $user, true));

            $details[] = [
                'payment_id' => $result['data']['id'],
                'invoice_id' => $invoiceIds[0] ?? null,
                'invoice_number' => $invoiceNumbers[0] ?? null,
                'invoice_numbers' => $invoiceNumbers,
                'customer_id' => $customer->id,
                'customer_name' => $row['customer_name'] ?? $customer->name,
                'amount' => $amount,
                'on_account' => $result['data']['on_account'] ?? 0,
                'payment_account_id' => $account->id,
                'payment_account_name' => trim(($account->code ? $account->code.' — ' : '').$account->name),
                'affects_cash_drawer' => $account->subtype === 'cash',
                'reference' => $row['reference'] ?? null,
            ];
        }

        return $details;
    }
}
