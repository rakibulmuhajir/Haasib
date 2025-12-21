<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Services\CurrentCompany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ParcoSettlementService
{
    public function __construct(
        private readonly GlPostingService $postingService,
    ) {}

    /**
     * Get all pending Parco card sales awaiting settlement.
     */
    public function getPendingSettlements(string $companyId): Collection
    {
        return SaleMetadata::where('company_id', $companyId)
            ->where('sale_type', SaleMetadata::TYPE_PARCO_CARD)
            ->whereHas('invoice', function ($query) {
                $query->where('status', '!=', 'paid');
            })
            ->with(['invoice.customer', 'pump'])
            ->get()
            ->map(function ($meta) {
                return [
                    'id' => $meta->id,
                    'invoice_id' => $meta->invoice_id,
                    'invoice_number' => $meta->invoice->invoice_number,
                    'invoice_date' => $meta->invoice->invoice_date,
                    'total_amount' => $meta->invoice->total_amount,
                    'amount_paid' => $meta->invoice->amount_paid,
                    'outstanding' => $meta->invoice->total_amount - $meta->invoice->amount_paid,
                    'pump' => $meta->pump,
                ];
            });
    }

    /**
     * Get summary of pending Parco settlements.
     */
    public function getPendingSummary(string $companyId): array
    {
        $pending = $this->getPendingSettlements($companyId);

        return [
            'count' => $pending->count(),
            'total_outstanding' => $pending->sum('outstanding'),
            'oldest_date' => $pending->min('invoice_date'),
            'items' => $pending,
        ];
    }

    /**
     * Settle Parco card payments.
     *
     * When Parco sends settlement (usually weekly/monthly):
     * - They may deduct fees
     * - Amount received may be less than total outstanding
     *
     * GL Transaction:
     * Dr Bank (amount received)
     * Dr Parco Fees Expense (if any deductions)
     * Cr Parco Receivable (total settled)
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
            $parcoReceivableAccount = $this->getParcoReceivableAccount($companyId);

            // Build GL entries
            $entries = [
                [
                    'account_id' => $bankAccount->id,
                    'type' => 'debit',
                    'amount' => $amountReceived,
                    'description' => 'Parco settlement received',
                ],
            ];

            // Add fees if any
            if ($fees > 0) {
                $feesAccount = $this->getParcoFeesAccount($companyId);
                $entries[] = [
                    'account_id' => $feesAccount->id,
                    'type' => 'debit',
                    'amount' => $fees,
                    'description' => 'Parco settlement fees',
                ];
            }

            // Credit Parco Receivable
            $entries[] = [
                'account_id' => $parcoReceivableAccount->id,
                'type' => 'credit',
                'amount' => $totalOutstanding,
                'description' => 'Parco settlement - ' . count($invoiceIds) . ' invoices',
            ];

            // Create GL transaction
            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_number' => $reference ?? 'PARCO-SETTLE-' . now()->format('Ymd'),
                'transaction_type' => 'parco_settlement',
                'date' => $settlementDate,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => 'Parco card payment settlement',
                'reference_type' => 'fuel.parco_settlement',
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
     * Get or create Parco receivable account.
     */
    private function getParcoReceivableAccount(string $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('name', 'like', '%Parco%Receivable%')
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '1150',
            'name' => 'Parco Card Receivable',
            'type' => 'asset',
            'subtype' => 'current_asset',
            'is_active' => true,
            'currency' => 'PKR',
        ]);
    }

    /**
     * Get or create Parco fees expense account.
     */
    private function getParcoFeesAccount(string $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('type', 'expense')
            ->where('name', 'like', '%Parco%Fee%')
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '6200',
            'name' => 'Parco Card Fees',
            'type' => 'expense',
            'subtype' => 'operating_expense',
            'is_active' => true,
            'currency' => 'PKR',
        ]);
    }
}
