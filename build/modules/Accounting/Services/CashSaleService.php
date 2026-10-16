<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Services\AccountingWriteTransaction;
use App\Services\CommandBus;
use Illuminate\Support\Facades\DB;

/**
 * The one write path for a cash sale: an invoice to the "Cash Sales" customer, paid in full
 * on the spot into a bank or cash account. The sales page and the `sale.create` command both
 * call this, so they post the same journals.
 */
class CashSaleService
{
    /**
     * @param  array<int, array{description: string, amount: float|int|string, income_account_id?: ?string}>  $lines
     * @return array{invoice_id: ?string, invoice_result: array, payment_result: ?array}
     */
    public function record(Company $company, $user, string $date, string $depositAccountId, array $lines): array
    {
        $commandBus = app(CommandBus::class);

        return AccountingWriteTransaction::run(function () use ($company, $user, $date, $depositAccountId, $lines, $commandBus) {
            $customer = $this->firstOrCreateCashSalesCustomer(
                companyId: $company->id,
                baseCurrency: $company->base_currency,
                companyArAccountId: $company->ar_account_id
            );

            $lineItems = array_map(fn (array $item) => [
                'description' => $item['description'],
                'quantity' => 1,
                'unit_price' => (float) $item['amount'],
                'tax_rate' => 0,
                'discount_rate' => 0,
                'income_account_id' => $item['income_account_id'] ?? null,
            ], $lines);

            $total = array_reduce($lines, fn (float $carry, array $item) => $carry + (float) ($item['amount'] ?? 0), 0.0);

            $invoiceResult = $commandBus->dispatch('invoice.create', [
                'customer' => $customer->id,
                'currency' => $company->base_currency,
                'date' => $date,
                'due' => $date,
                'draft' => false,
                'send_immediately' => true,
                'description' => 'Sale',
                'line_items' => $lineItems,
            ], $user);

            $invoiceId = $invoiceResult['data']['id'] ?? null;
            if (! $invoiceId) {
                return ['invoice_id' => null, 'invoice_result' => $invoiceResult, 'payment_result' => null];
            }

            $paymentResult = $commandBus->dispatch('payment.create', [
                'invoice' => $invoiceId,
                'amount' => $total,
                'method' => 'cash',
                'currency' => $company->base_currency,
                'date' => $date,
                'deposit_account_id' => $depositAccountId,
                'ar_account_id' => $company->ar_account_id,
                'notes' => 'Sale payment',
            ], $user);

            return ['invoice_id' => $invoiceId, 'invoice_result' => $invoiceResult, 'payment_result' => $paymentResult];
        });
    }

    private function firstOrCreateCashSalesCustomer(string $companyId, string $baseCurrency, ?string $companyArAccountId): Customer
    {
        $existing = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['cash sales'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($companyId, $baseCurrency, $companyArAccountId) {
            $lastNumber = Customer::where('company_id', $companyId)
                ->whereNotNull('customer_number')
                ->lockForUpdate()
                ->orderByDesc('customer_number')
                ->value('customer_number');

            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            } else {
                $sequence = 1;
            }

            $customerNumber = 'CUST-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

            return Customer::create([
                'company_id' => $companyId,
                'customer_number' => $customerNumber,
                'name' => 'Cash Sales',
                'base_currency' => strtoupper($baseCurrency),
                'payment_terms' => 0,
                'ar_account_id' => $companyArAccountId,
                'is_active' => true,
            ]);
        });
    }
}
