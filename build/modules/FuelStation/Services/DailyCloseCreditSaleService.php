<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Receivable documents for meter sales already posted by the close. */
class DailyCloseCreditSaleService
{
    public function assertMutable(Invoice $invoice): void
    {
        // An Accounting invoice a close counted as credit keeps its own journal but is tied to
        // that close by included_in_close_id; changing it would leave the close's figures wrong.
        // The saved value only: the close's own attach() is what sets it, and must get through.
        if ($invoice->getOriginal('included_in_close_id')) {
            throw new \RuntimeException('This invoice was counted in a posted Daily Close. Record a separate adjustment; its original sale cannot be changed.');
        }
        if (\App\Modules\Accounting\Models\Transaction::where('company_id', $invoice->company_id)
            ->whereKey($invoice->getOriginal('transaction_id') ?? $invoice->transaction_id)
            ->where('transaction_type', 'fuel_daily_close')->whereNotNull('metadata->posting_snapshot')->exists()) {
            throw new \RuntimeException('This invoice belongs to a posted Daily Close. Record a separate adjustment; its original sale cannot be changed.');
        }
    }

    public function prepare(string $companyId, string $date, array $rows, float $nozzleRevenue, float $availableSales, User $user, array $fuelRevenueAccountIds = []): array
    {
        Validator::make(['credit_sales' => $rows], [
            'credit_sales' => 'array',
            'credit_sales.*.customer_id' => 'required|uuid|distinct',
            'credit_sales.*.amount' => 'required|numeric|min:0.01|decimal:0,2',
            'credit_sales.*.reference' => 'nullable|string|max:100',
        ])->validate();

        $company = Company::whereKey($companyId)->firstOrFail();
        $pending = array_merge(
            $this->pendingFuelInvoiceDetails($company, $date),
            $this->pendingAccountingInvoiceDetails($company, $date, $fuelRevenueAccountIds)
        );
        $pendingByNumber = collect($pending)->keyBy('invoice_number');

        // Every litre already went through a nozzle the close reads: a fuel-sale invoice
        // dated this business date is not optional. The client must echo back each pending
        // invoice unchanged (see Create.vue's read-only rows); if one is missing or its
        // amount was tampered with, reject rather than silently reattaching it, so the
        // operator sees exactly why and is pointed at the invoice, not the close.
        foreach ($pending as $invoiceDetail) {
            $match = collect($rows)->first(fn ($row) => ($row['reference'] ?? null) === $invoiceDetail['invoice_number']);
            $matches = $match && round((float) ($match['amount'] ?? 0), 2) === round((float) $invoiceDetail['amount'], 2);
            if (!$matches) {
                throw ValidationException::withMessages([
                    'credit_sales' => "Invoice {$invoiceDetail['invoice_number']} exists for this date and must be included; void or correct the invoice instead.",
                ]);
            }
        }

        // The Create page pre-checks each pending fuel-sale invoice as a credit_sales row so
        // its total is visible in the form (see Create.vue); the FormRequest strips the
        // invoice_id marker, so an unmodified echo is recognised by an exact reference+amount
        // match against the server's own authoritative pending list and silently dropped here
        // (the server re-attaches the real invoice via $pending, not this echoed row). A
        // reference that matches a pending invoice number but disagrees on amount is a
        // genuine conflicting manual entry and is rejected.
        $manualRows = [];
        foreach ($rows as $index => $row) {
            $reference = $row['reference'] ?? null;
            if ($reference && $pendingByNumber->has($reference)) {
                if (round((float) ($row['amount'] ?? 0), 2) === round((float) $pendingByNumber[$reference]['amount'], 2)) {
                    continue;
                }
                throw ValidationException::withMessages([
                    "credit_sales.{$index}.reference" => "Invoice {$reference} is already recorded as a pending fuel-sale invoice for this date; remove the duplicate manual line.",
                ]);
            }
            $manualRows[] = $row;
        }
        $rows = $manualRows;

        $total = round(array_sum(array_column($rows, 'amount')) + array_sum(array_column($pending, 'amount')), 2);
        if ($total > round($nozzleRevenue, 2) || $total > round($availableSales, 2)) {
            throw ValidationException::withMessages(['credit_sales' => 'Credit sales must be part of nozzle sales. Cards and credit together cannot exceed total sales.']);
        }

        $details = $pending;
        if (!$rows) { return $details; }

        foreach ($rows as $index => $row) {
            $customer = Customer::where('company_id', $companyId)->where('is_active', true)->find($row['customer_id']);
            if (!$customer) {
                throw ValidationException::withMessages(["credit_sales.{$index}.customer_id" => 'Choose an active customer of this company.']);
            }
            if ($customer->is_credit_blocked) {
                throw ValidationException::withMessages(["credit_sales.{$index}.customer_id" => "{$customer->name} is blocked from further credit sales."]);
            }
            $arId = $customer->ar_account_id ?: $company->default_ar_account_id;
            $ar = Account::where('company_id', $companyId)->where('is_active', true)->where('subtype', 'accounts_receivable')
                ->when($arId, fn ($q) => $q->whereKey($arId), fn ($q) => $q->orderBy('code'))->first();
            if (!$ar || ($ar->currency && $ar->currency !== $company->base_currency)) {
                throw ValidationException::withMessages(["credit_sales.{$index}.customer_id" => 'Set up a base-currency receivables account for this customer first.']);
            }

            $gross = round((float) $row['amount'], 2);
            $itemId = $row['item_id'] ?? null;
            $litres = isset($row['litres']) ? (float) $row['litres'] : null;
            $discountAmount = 0.0;
            if ($itemId) {
                $stored = app(CustomerFuelDiscountService::class)->for($companyId, $customer->id, $itemId);
                if ($stored) {
                    if ($stored['discount_type'] === CustomerFuelDiscount::TYPE_PER_LITRE && !$litres) {
                        throw ValidationException::withMessages(["credit_sales.{$index}.litres" => "{$customer->name} has a per-litre discount on this fuel; enter the litres for this row."]);
                    }
                    $discountAmount = app(CustomerFuelDiscountService::class)->amount($stored, (float) $litres, $gross);
                }
            }
            $net = round($gross - $discountAmount, 2);

            // Use native numbering and invoice validation; drafts have no independent GL posting.
            $result = app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('invoice.create', [
                'customer' => $customer->id, 'currency' => $company->base_currency, 'date' => $date,
                'draft' => true,
                'notes' => "Credit portion of meter sales for {$date}. ".($row['reference'] ?? ''),
                'line_items' => [['description' => "Meter sales on credit — {$date}", 'quantity' => 1,
                    'unit_price' => $net, 'tax_rate' => 0]],
            ], $user, true));
            $details[] = ['customer_id' => $customer->id, 'customer_name' => $customer->name,
                // 'amount' stays the gross meter sale -- this is what the close removes from
                // expected drawer cash (see DailyCloseService's cashFromSales calc), exactly
                // once, regardless of any discount.
                'amount' => $gross, 'net_amount' => $net, 'discount_amount' => $discountAmount,
                'item_id' => $itemId, 'litres' => $litres, 'reference' => $row['reference'] ?? null,
                'ar_account_id' => $ar->id, 'invoice_id' => $result['data']['id'], 'invoice_number' => $result['data']['number'],
                'source' => 'manual'];
        }
        return $details;
    }

    /**
     * Standalone fuel-sale invoices (FuelSaleService::createSale, sale_type=credit) for this
     * company+date that have not yet been linked to a close. They are a channel of the close,
     * never additional revenue: every litre already went through a nozzle the close reads.
     */
    public function pendingFuelInvoiceDetails(Company $company, string $date): array
    {
        return Invoice::where('company_id', $company->id)
            ->whereDate('invoice_date', $date)
            ->whereNull('transaction_id')
            ->whereHas('saleMetadata', fn ($q) => $q->where('sale_type', SaleMetadata::TYPE_CREDIT))
            ->with('customer')
            ->lockForUpdate()
            ->orderBy('invoice_number')
            ->get()
            ->map(function (Invoice $invoice) use ($company) {
                $customer = $invoice->customer;
                $arId = $customer?->ar_account_id ?: $company->default_ar_account_id;
                $ar = Account::where('company_id', $company->id)->where('is_active', true)->where('subtype', 'accounts_receivable')
                    ->when($arId, fn ($q) => $q->whereKey($arId), fn ($q) => $q->orderBy('code'))->first();
                if (!$ar || ($ar->currency && $ar->currency !== $company->base_currency)) {
                    throw ValidationException::withMessages([
                        'credit_sales' => "Set up a base-currency receivables account for {$invoice->invoice_number}'s buyer before closing this date.",
                    ]);
                }
                return [
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $customer->name ?? 'Buyer',
                    'amount' => round((float) $invoice->total_amount, 2),
                    'reference' => $invoice->invoice_number,
                    'ar_account_id' => $ar->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'source' => 'fuel_sale_invoice',
                ];
            })->all();
    }

    /**
     * Plain Accounting -> Invoices invoices for litres that DID go through a meter this
     * business date: posted already (they book their own AR/revenue journal immediately),
     * not flagged is_direct_delivery, not yet folded into a close, and not a Fuel -> Sales
     * invoice (those are pendingFuelInvoiceDetails' own channel). A candidate is included as
     * a credit row exactly like a pending fuel-sale invoice, except the close never re-debits
     * AR for it -- the invoice already did that -- it instead debits the invoice's own
     * income account(s) to take that revenue back out, so the close's meter revenue is what
     * stands alone in the ledger. Amount is each fuel line's pre-tax total: the close itself
     * tracks no separate tax on meter revenue (see processDailyClose's nozzle-reading loop),
     * so this is the closest like-for-like comparison; tax stays where the invoice booked it.
     *
     * @param  array<int, string>  $fuelRevenueAccountIds
     */
    public function pendingAccountingInvoiceDetails(Company $company, string $date, array $fuelRevenueAccountIds): array
    {
        $fuelRevenueAccountIds = array_values(array_unique(array_filter($fuelRevenueAccountIds)));
        if (!$fuelRevenueAccountIds) {
            return [];
        }

        return Invoice::where('company_id', $company->id)
            ->whereDate('invoice_date', $date)
            ->whereNotIn('status', ['draft', 'void', 'cancelled', 'reversed'])
            ->whereNotNull('transaction_id')
            ->where('is_direct_delivery', false)
            ->whereNull('included_in_close_id')
            ->whereDoesntHave('saleMetadata')
            ->whereHas('lineItems', fn ($q) => $q->whereIn('income_account_id', $fuelRevenueAccountIds))
            ->with(['customer', 'lineItems'])
            ->lockForUpdate()
            ->orderBy('invoice_number')
            ->get()
            ->map(function (Invoice $invoice) use ($fuelRevenueAccountIds) {
                $fuelLines = $invoice->lineItems->filter(fn ($li) => in_array($li->income_account_id, $fuelRevenueAccountIds, true));
                $incomeLines = [];
                foreach ($fuelLines as $line) {
                    $incomeLines[$line->income_account_id] = round(($incomeLines[$line->income_account_id] ?? 0) + (float) $line->line_total, 2);
                }
                $amount = round(array_sum($incomeLines), 2);
                return [
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer->name ?? 'Buyer',
                    'amount' => $amount,
                    'reference' => $invoice->invoice_number,
                    'ar_account_id' => null,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'source' => 'accounting_invoice',
                    'income_lines' => $incomeLines,
                ];
            })
            ->filter(fn ($detail) => $detail['amount'] > 0)
            ->values()
            ->all();
    }

    public function attach(string $companyId, string $transactionId, array $details): void
    {
        foreach ($details as $detail) {
            $invoice = Invoice::where('company_id', $companyId)->findOrFail($detail['invoice_id']);
            if (($detail['source'] ?? null) === 'accounting_invoice') {
                // Already posted its own AR/revenue journal -- the close only records which
                // close's journal absorbed its meter-revenue portion, never touches status or
                // transaction_id.
                $invoice->update(['included_in_close_id' => $transactionId]);
                continue;
            }
            $invoice->update(['transaction_id' => $transactionId, 'status' => 'sent', 'sent_at' => now()]);
        }
    }
}
