<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
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
        if (\App\Modules\Accounting\Models\Transaction::where('company_id', $invoice->company_id)
            ->whereKey($invoice->getOriginal('transaction_id') ?? $invoice->transaction_id)
            ->where('transaction_type', 'fuel_daily_close')->whereNotNull('metadata->posting_snapshot')->exists()) {
            throw new \RuntimeException('This invoice belongs to a posted Daily Close. Record a separate adjustment; its original sale cannot be changed.');
        }
    }

    public function prepare(string $companyId, string $date, array $rows, float $nozzleRevenue, float $availableSales, User $user): array
    {
        Validator::make(['credit_sales' => $rows], [
            'credit_sales' => 'array',
            'credit_sales.*.customer_id' => 'required|uuid|distinct',
            'credit_sales.*.amount' => 'required|numeric|min:0.01|decimal:0,2',
            'credit_sales.*.reference' => 'nullable|string|max:100',
        ])->validate();

        $company = Company::whereKey($companyId)->firstOrFail();
        $pending = $this->pendingFuelInvoiceDetails($company, $date);
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
            // Use native numbering and invoice validation; drafts have no independent GL posting.
            $result = app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('invoice.create', [
                'customer' => $customer->id, 'currency' => $company->base_currency, 'date' => $date,
                'draft' => true,
                'notes' => "Credit portion of meter sales for {$date}. ".($row['reference'] ?? ''),
                'line_items' => [['description' => "Meter sales on credit — {$date}", 'quantity' => 1,
                    'unit_price' => $row['amount'], 'tax_rate' => 0]],
            ], $user, true));
            $details[] = ['customer_id' => $customer->id, 'customer_name' => $customer->name,
                'amount' => round((float) $row['amount'], 2), 'reference' => $row['reference'] ?? null,
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

    public function attach(string $companyId, string $transactionId, array $details): void
    {
        foreach ($details as $detail) {
            Invoice::where('company_id', $companyId)->findOrFail($detail['invoice_id'])
                ->update(['transaction_id' => $transactionId, 'status' => 'sent', 'sent_at' => now()]);
        }
    }
}
