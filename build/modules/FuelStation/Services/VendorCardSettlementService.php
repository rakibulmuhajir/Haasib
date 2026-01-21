<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\SaleMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorCardSettlementService
{
    public function __construct(
        private readonly GlPostingService $postingService,
    ) {}

    /**
     * Get all pending vendor card sales awaiting settlement.
     */
    public function getPendingSettlements(string $companyId): Collection
    {
        return SaleMetadata::where('company_id', $companyId)
            ->where('sale_type', SaleMetadata::TYPE_VENDOR_CARD)
            ->whereHas('invoice', function ($query) {
                $query->where('status', '!=', 'paid');
            })
            ->with(['invoice.customer', 'pump'])
            ->get()
            ->map(function ($meta) {
                $invoice = $meta->invoice;
                $customerName = $invoice?->customer?->name ?? 'Customer';
                $totalAmount = (float) ($invoice?->total_amount ?? 0);
                $amountPaid = (float) ($invoice?->amount_paid ?? 0);

                return [
                    'id' => $meta->id,
                    'invoice_id' => $meta->invoice_id,
                    'customer_name' => $customerName,
                    'invoice_number' => $invoice?->invoice_number,
                    'invoice_date' => $invoice?->invoice_date,
                    'amount' => $totalAmount,
                    'settled_amount' => $amountPaid,
                    'outstanding' => $totalAmount - $amountPaid,
                    'status' => 'pending',
                    'pump' => $meta->pump,
                ];
            });
    }

    /**
     * Get summary of pending vendor card settlements.
     */
    public function getPendingSummary(string $companyId): array
    {
        $pending = $this->getPendingSettlements($companyId);

        return [
            'count_pending' => $pending->count(),
            'total_pending' => $pending->sum('outstanding'),
            'total_outstanding' => $pending->sum('outstanding'),
            'total_settled_today' => 0,
            'oldest_date' => $pending->min('invoice_date'),
            'items' => $pending,
        ];
    }

    /**
     * Settle vendor card payments.
     *
     * When a vendor sends settlement (usually weekly/monthly):
     * - They may deduct fees
     * - Amount received may be less than total outstanding
     *
     * GL Transaction:
     * Dr Bank (amount received)
     * Dr Vendor Card Fees Expense (if any deductions)
     * Cr Vendor Card Receivable (total settled)
     */
    public function settle(string $companyId, array $data): Transaction
    {
        return DB::transaction(function () use ($companyId, $data) {
            $company = \App\Models\Company::findOrFail($companyId);
            $invoiceIds = $data['invoice_ids'];
            $amountReceived = $data['amount_received'];
            $settlementDate = $data['settlement_date'] ?? now()->toDateString();
            $reference = $data['reference'] ?? null;

            // Get invoices to settle
            $invoices = Invoice::whereIn('id', $invoiceIds)
                ->where('company_id', $companyId)
                ->get();

            $totalOutstanding = $invoices->sum(fn ($inv) => $inv->total_amount - $inv->amount_paid);

            // Calculate fees (difference between outstanding and received)
            $fees = $totalOutstanding - $amountReceived;
            if ($fees < 0) {
                throw new \InvalidArgumentException(
                    "Amount received ({$amountReceived}) exceeds outstanding ({$totalOutstanding})."
                );
            }

            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));
            $bankAccount = $this->getBankAccount($companyId, $data['bank_account_id'] ?? null);
            $vendorReceivableAccount = $this->getVendorCardReceivableAccount($companyId);

            // Build GL entries
            $entries = [
                [
                    'account_id' => $bankAccount->id,
                    'type' => 'debit',
                    'amount' => $amountReceived,
                    'description' => 'Vendor card settlement received',
                ],
            ];

            // Add fees if any
            if ($fees > 0) {
                $feesAccount = $this->getVendorCardFeesAccount($companyId);
                $entries[] = [
                    'account_id' => $feesAccount->id,
                    'type' => 'debit',
                    'amount' => $fees,
                    'description' => 'Vendor card settlement fees',
                ];
            }

            // Credit Vendor Card Receivable
            $entries[] = [
                'account_id' => $vendorReceivableAccount->id,
                'type' => 'credit',
                'amount' => $totalOutstanding,
                'description' => 'Vendor card settlement - ' . count($invoiceIds) . ' invoices',
            ];

            // Create GL transaction
            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_number' => $reference ?? 'VENDOR-CARD-SETTLE-' . now()->format('Ymd'),
                'transaction_type' => 'vendor_card_settlement',
                'date' => $settlementDate,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => 'Vendor card payment settlement',
                'reference_type' => 'fuel.vendor_card_settlement',
                'reference_id' => null,
            ], $entries);

            // Mark invoices as paid
            foreach ($invoices as $invoice) {
                $invoice->update([
                    'amount_paid' => $invoice->total_amount,
                    'status' => 'paid',
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Get bank account for settlement.
     */
    private function getBankAccount(string $companyId, ?string $accountId = null): Account
    {
        if ($accountId) {
            return Account::findOrFail($accountId);
        }

        return Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('subtype', 'bank')
            ->firstOrFail();
    }

    /**
     * Get or create vendor card receivable account.
     */
    private function getVendorCardReceivableAccount(string $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where(function ($query) {
                $query->where('name', 'like', '%Vendor%Card%Receivable%')
                    ->orWhere('name', 'like', '%Fuel%Card%Receivable%')
                    ->orWhere('name', 'like', '%Parco%Receivable%');
            })
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '1150',
            'name' => 'Vendor Card Receivable',
            'type' => 'asset',
            'subtype' => 'current_asset',
            'is_active' => true,
            'currency' => 'PKR',
        ]);
    }

    /**
     * Get or create vendor card fees expense account.
     */
    private function getVendorCardFeesAccount(string $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('type', 'expense')
            ->where(function ($query) {
                $query->where('name', 'like', '%Vendor%Card%Fee%')
                    ->orWhere('name', 'like', '%Fuel%Card%Fee%')
                    ->orWhere('name', 'like', '%Parco%Fee%');
            })
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '6200',
            'name' => 'Vendor Card Fees',
            'type' => 'expense',
            'subtype' => 'operating_expense',
            'is_active' => true,
            'currency' => 'PKR',
        ]);
    }
}
