<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
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
        $total = round(array_sum(array_column($rows, 'amount')), 2);
        if ($total > round($nozzleRevenue, 2) || $total > round($availableSales, 2)) {
            throw ValidationException::withMessages(['credit_sales' => 'Credit sales must be part of nozzle sales. Cards and credit together cannot exceed total sales.']);
        }
        if (!$rows) { return []; }

        $company = Company::whereKey($companyId)->firstOrFail();
        $details = [];
        foreach ($rows as $index => $row) {
            $customer = Customer::where('company_id', $companyId)->where('is_active', true)->find($row['customer_id']);
            if (!$customer) {
                throw ValidationException::withMessages(["credit_sales.{$index}.customer_id" => 'Choose an active customer of this company.']);
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
                'ar_account_id' => $ar->id, 'invoice_id' => $result['data']['id'], 'invoice_number' => $result['data']['number']];
        }
        return $details;
    }

    public function attach(string $companyId, string $transactionId, array $details): void
    {
        foreach ($details as $detail) {
            Invoice::where('company_id', $companyId)->findOrFail($detail['invoice_id'])
                ->update(['transaction_id' => $transactionId, 'status' => 'sent', 'sent_at' => now()]);
        }
    }
}
