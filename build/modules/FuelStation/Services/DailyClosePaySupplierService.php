<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Validation\ValidationException;

/**
 * "Pay supplier" rows entered directly in the Daily Close's Cash Out section, instead of at
 * /bill-payments. Each row names a supplier, an amount and the account it is paid from
 * (defaulting to the station cash drawer, but any of the company's cash/bank accounts is
 * allowed). Every row becomes an ordinary bill_payment.create — the same canonical path the
 * Bills module uses and the same one the card-channel supplier settlement in
 * DailyCloseService uses — allocated to the vendor's open bills oldest-first
 * (DailyCloseService::allocateOldestFirst), for whatever is actually owed. Anything beyond
 * the open balance is not an error: it is left on account as an advance, applied
 * automatically (VendorAdvanceService::autoApply) the moment the vendor's next bill posts.
 *
 * A cash-account row raises expected drawer cash exactly like the existing "Supplier Bill
 * Payments recorded elsewhere" sweep; a bank-account row never touches the drawer.
 */
class DailyClosePaySupplierService
{
    public function prepare(string $companyId, string $date, array $rows, User $user, DailyCloseService $dailyCloseService): array
    {
        if (!$rows) {
            return [];
        }

        $company = Company::whereKey($companyId)->firstOrFail();
        $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $details = [];

        foreach ($rows as $index => $row) {
            if (empty($row['vendor_id']) && empty($row['amount']) && empty($row['payment_account_id'])) {
                continue; // a wholly empty row, e.g. one added then never filled in
            }
            if (empty($row['vendor_id']) || empty($row['payment_account_id']) || empty($row['amount'])) {
                throw ValidationException::withMessages(["pay_suppliers.{$index}" => 'A supplier payment row must have a supplier, an amount and an account paid from.']);
            }

            $vendor = Vendor::where('company_id', $companyId)->find($row['vendor_id']);
            if (!$vendor) {
                throw ValidationException::withMessages(["pay_suppliers.{$index}.vendor_id" => 'Choose a supplier belonging to this company.']);
            }

            $account = Account::where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')
                ->whereIn('subtype', ['cash', 'bank'])->find($row['payment_account_id']);
            if (!$account) {
                throw ValidationException::withMessages(["pay_suppliers.{$index}.payment_account_id" => 'Choose a cash or bank account belonging to this company.']);
            }

            $amount = round((float) $row['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(["pay_suppliers.{$index}.amount" => 'Amount must be greater than zero.']);
            }

            $allocation = $dailyCloseService->allocateOldestFirst($companyId, $vendor->id, $amount);

            $notes = trim('Daily Close — pay supplier'.(!empty($row['reference']) ? ' — '.$row['reference'] : ''));
            if ($allocation['advance_amount'] > 0.004) {
                $notes .= '. '.number_format($allocation['advance_amount'], 2)." held as an advance with {$vendor->name}.";
            }

            $result = app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
                'vendor_id' => $vendor->id,
                'payment_date' => $date,
                'amount' => $amount,
                'currency' => $currency,
                'base_currency' => $currency,
                'payment_method' => $account->subtype === 'cash' ? 'cash' : 'bank_transfer',
                'payment_account_id' => $account->id,
                'ap_account_id' => $vendor->ap_account_id,
                'allocations' => $allocation['allocations'],
                'reference_number' => $row['reference'] ?? null,
                'notes' => $notes,
            ], $user, true));

            $details[] = [
                'payment_id' => $result['data']['id'] ?? null,
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'amount' => $amount,
                'applied_to_bills' => $allocation['applied_amount'],
                'advance_amount' => $allocation['advance_amount'],
                'payment_account_id' => $account->id,
                'payment_account_name' => trim(($account->code ? $account->code.' — ' : '').$account->name),
                'affects_cash_drawer' => $account->subtype === 'cash',
                'reference' => $row['reference'] ?? null,
            ];
        }

        return $details;
    }
}
