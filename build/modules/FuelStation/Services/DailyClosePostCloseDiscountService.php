<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Inventory\Models\Item;
use App\Services\AccountingWriteTransaction;
use Illuminate\Validation\ValidationException;

/**
 * A discount given to a credit-sale customer AFTER their close has already posted (Owner's
 * decision: "Allow unlock, and then it'd display the invoice amount and the discount
 * applied"). Only reachable once the close is unlocked again -- see
 * DailyCloseLockService::unlockTransaction -- so the day's own posted snapshot is never
 * touched; this only amends the invoice itself and posts a separate, dated journal, exactly
 * like DailyCloseEntryService::expense() and CloseReadingCorrectionService do for their own
 * kinds of post-close activity.
 *
 * Never touches drawer cash: the customer already owed the gross amount and now owes less
 * of it, so the entry is Dr Sales Discounts / Cr the invoice's own AR account, dated the
 * close's business date.
 */
class DailyClosePostCloseDiscountService
{
    public function apply(Transaction $close, string $invoiceId, string $itemId, ?float $litres, float $discountAmount, User $user): array
    {
        return AccountingWriteTransaction::run(function () use ($close, $invoiceId, $itemId, $litres, $discountAmount, $user) {
            if ($close->is_locked) {
                throw new \RuntimeException('Unlock the day first.');
            }

            $invoice = Invoice::where('company_id', $close->company_id)->with('customer')->find($invoiceId);
            if (!$invoice) {
                throw ValidationException::withMessages(['invoice_id' => 'Invoice not found.']);
            }

            // "One of this close's credit sale invoices": a manual/fuel-sale-channel row
            // carries the close's own transaction_id (see DailyCloseCreditSaleService::
            // attach()); an accounting_invoice-channel row instead only carries
            // included_in_close_id, since it keeps its own transaction. Either counts.
            $belongsToClose = $invoice->transaction_id === $close->id || $invoice->included_in_close_id === $close->id;
            if (!$belongsToClose) {
                throw ValidationException::withMessages(['invoice_id' => 'This invoice is not one of this close\'s credit sale invoices.']);
            }

            $discountAmount = round($discountAmount, 2);
            if ($discountAmount <= 0) {
                throw ValidationException::withMessages(['discount_amount' => 'Enter a positive discount amount.']);
            }
            if ($discountAmount > round((float) $invoice->balance, 2) + 0.005) {
                throw ValidationException::withMessages(['discount_amount' => 'The discount cannot exceed what is still owed on this invoice.']);
            }

            $item = $itemId ? Item::where('company_id', $close->company_id)->find($itemId) : null;

            $customer = $invoice->customer;
            $company = Company::whereKey($close->company_id)->firstOrFail();
            $arId = $customer?->ar_account_id ?: $company->default_ar_account_id;
            $ar = Account::where('company_id', $close->company_id)->where('is_active', true)->where('subtype', 'accounts_receivable')
                ->when($arId, fn ($q) => $q->whereKey($arId), fn ($q) => $q->orderBy('code'))->first();
            if (!$ar || ($ar->currency && $ar->currency !== $company->base_currency)) {
                throw new \RuntimeException('Set up a base-currency receivables account for this buyer before applying a discount.');
            }

            $discountAccountId = app(StationAccountMapper::class)
                ->resolveMappedAccountId($close->company_id, 'sales_discount_account_id', $user->id);
            if (!$discountAccountId) {
                throw new \RuntimeException('Set up a sales discount account before applying a discount.');
            }

            $newDiscount = round((float) $invoice->discount_amount + $discountAmount, 2);
            $newTotal = max(0, round((float) $invoice->total_amount - $discountAmount, 2));
            $newBalance = max(0, round((float) $invoice->balance - $discountAmount, 2));
            $newStatus = $newBalance <= 0.005 ? 'paid' : $invoice->status;

            $invoice->update([
                'discount_amount' => $newDiscount,
                'total_amount' => $newTotal,
                'balance' => $newBalance,
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? ($invoice->paid_at ?? now()) : $invoice->paid_at,
            ]);

            $date = $close->transaction_date->toDateString();
            $description = "Discount on {$invoice->invoice_number}"
                .($item ? " — {$item->name}" : '')
                .($litres ? " ({$litres} L)" : '');

            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $close->company_id,
                'transaction_type' => 'fuel_daily_close_discount',
                'date' => $date,
                'currency' => $invoice->currency ?: ($company->base_currency ?: 'PKR'),
                'description' => $description,
                'reference_type' => 'fuel.daily_close_discount',
                'reference_id' => $invoice->id,
                'metadata' => ['close_id' => $close->id, 'item_id' => $itemId, 'litres' => $litres],
            ], [
                ['account_id' => $discountAccountId, 'type' => 'debit', 'amount' => $discountAmount, 'description' => $description],
                ['account_id' => $ar->id, 'type' => 'credit', 'amount' => $discountAmount, 'description' => $description],
            ]);

            return [
                'invoice_id' => $invoice->id,
                'discount_amount' => $newDiscount,
                'total_amount' => $newTotal,
                'balance' => $newBalance,
                'status' => $newStatus,
                'transaction_id' => $transaction->id,
            ];
        });
    }
}
