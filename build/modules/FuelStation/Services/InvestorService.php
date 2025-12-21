<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\InvestorLot;
use App\Modules\FuelStation\Models\RateChange;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

class InvestorService
{
    public function __construct(
        private readonly GlPostingService $postingService,
    ) {}

    /**
     * Create a new investor lot with journal entry.
     *
     * The lot locks the entitlement_rate at time of deposit to prevent
     * rate-change disputes. Commission rate is the margin at time of deposit.
     */
    public function createLot(Investor $investor, array $data): InvestorLot
    {
        return DB::transaction(function () use ($investor, $data) {
            $company = app(CurrentCompany::class)->get();

            // Get current purchase rate for the fuel item (default to petrol)
            $itemId = $data['item_id'] ?? $this->getDefaultFuelItemId($company->id);
            $currentRate = RateChange::getCurrentRate($company->id, $itemId);

            if (!$currentRate) {
                throw new \InvalidArgumentException('No current rate found for fuel item. Please set rates first.');
            }

            // Calculate units entitled at current purchase rate
            $unitsEntitled = $data['investment_amount'] / $currentRate->purchase_rate;

            $depositDate = $data['deposit_date'] ?? now()->toDateString();
            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

            // Resolve accounts
            $cashAccount = $this->getCashAccount($company->id);
            $investorLiabilityAccount = $this->getInvestorLiabilityAccount($company->id, $investor);

            // Create GL transaction: Dr Cash/Bank, Cr Investor Payable
            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => 'INV-DEP-' . strtoupper(substr($investor->id, 0, 8)),
                'transaction_type' => 'investor_deposit',
                'date' => $depositDate,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Investment deposit from: {$investor->name}",
                'reference_type' => 'fuel.investors',
                'reference_id' => $investor->id,
            ], [
                [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $data['investment_amount'],
                    'description' => "Investment deposit - {$investor->name}",
                ],
                [
                    'account_id' => $investorLiabilityAccount->id,
                    'type' => 'credit',
                    'amount' => $data['investment_amount'],
                    'description' => "Investment deposit - {$investor->name}",
                ],
            ]);

            // Create the lot with locked rates
            $lot = InvestorLot::create([
                'company_id' => $company->id,
                'investor_id' => $investor->id,
                'deposit_date' => $depositDate,
                'investment_amount' => $data['investment_amount'],
                'entitlement_rate' => $currentRate->purchase_rate,
                'commission_rate' => $currentRate->margin, // Sale - Purchase rate
                'units_entitled' => $unitsEntitled,
                'units_remaining' => $unitsEntitled,
                'commission_earned' => 0,
                'status' => InvestorLot::STATUS_ACTIVE,
                'transaction_id' => $transaction->id,
            ]);

            // Update investor totals
            $investor->recalculateTotals();

            return $lot;
        });
    }

    /**
     * Pay commission to investor.
     *
     * Creates GL transaction: Dr Investor Commission Expense, Cr Cash/Bank
     */
    public function payCommission(Investor $investor, array $data): Transaction
    {
        return DB::transaction(function () use ($investor, $data) {
            $company = app(CurrentCompany::class)->get();
            $amount = $data['amount'];

            // Validate amount against outstanding commission
            if ($amount > $investor->outstanding_commission) {
                throw new \InvalidArgumentException(
                    "Payment amount ({$amount}) exceeds outstanding commission ({$investor->outstanding_commission})."
                );
            }

            $paymentDate = $data['payment_date'] ?? now()->toDateString();
            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

            // Get accounts
            $commissionExpenseAccount = $this->getCommissionExpenseAccount($company->id);
            $cashAccount = $this->getCashAccount($company->id, $data['payment_account_id'] ?? null);

            // Create GL transaction
            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => 'INV-COMM-' . strtoupper(substr($investor->id, 0, 8)) . '-' . now()->format('Ymd'),
                'transaction_type' => 'investor_commission',
                'date' => $paymentDate,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Commission payment to investor: {$investor->name}",
                'reference_type' => 'fuel.investors',
                'reference_id' => $investor->id,
            ], [
                [
                    'account_id' => $commissionExpenseAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Commission payment - {$investor->name}",
                ],
                [
                    'account_id' => $cashAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Commission payment - {$investor->name}",
                ],
            ]);

            // Update investor paid amount
            $investor->total_commission_paid += $amount;
            $investor->save();

            return $transaction;
        });
    }

    /**
     * Get or create investor's liability account.
     */
    private function getInvestorLiabilityAccount(string $companyId, Investor $investor): Account
    {
        // If investor already has an account, use it
        if ($investor->investor_account_id) {
            return Account::find($investor->investor_account_id);
        }

        // Find parent "Investor Deposits" account or create investor's account
        $parentAccount = Account::where('company_id', $companyId)
            ->where('code', 'like', '2%') // Liabilities
            ->where('name', 'like', '%Investor%Deposit%')
            ->first();

        // Create individual investor account
        $account = Account::create([
            'company_id' => $companyId,
            'code' => '2100-' . strtoupper(substr($investor->id, 0, 4)),
            'name' => "Investor Deposit - {$investor->name}",
            'type' => 'liability',
            'subtype' => 'current_liability',
            'parent_id' => $parentAccount?->id,
            'is_active' => true,
            'currency' => 'PKR',
        ]);

        // Update investor with their account
        $investor->update(['investor_account_id' => $account->id]);

        return $account;
    }

    /**
     * Get cash account for transactions.
     */
    private function getCashAccount(string $companyId, ?string $accountId = null): Account
    {
        if ($accountId) {
            return Account::findOrFail($accountId);
        }

        // Default to main cash account
        return Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('name', 'like', '%Cash%')
            ->firstOrFail();
    }

    /**
     * Get commission expense account.
     */
    private function getCommissionExpenseAccount(string $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('type', 'expense')
            ->where('name', 'like', '%Commission%')
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '6100',
            'name' => 'Investor Commission Expense',
            'type' => 'expense',
            'subtype' => 'operating_expense',
            'is_active' => true,
            'currency' => 'PKR',
        ]);
    }

    /**
     * Get default fuel item ID (petrol).
     */
    private function getDefaultFuelItemId(string $companyId): string
    {
        $item = \App\Modules\Inventory\Models\Item::where('company_id', $companyId)
            ->where('name', 'like', '%Petrol%')
            ->first();

        if (!$item) {
            throw new \InvalidArgumentException('No fuel item found. Please create inventory items first.');
        }

        return $item->id;
    }

    /**
     * Get investor summary statistics.
     */
    public function getInvestorSummary(string $companyId): array
    {
        $investors = Investor::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        return [
            'total_investors' => $investors->count(),
            'total_invested' => $investors->sum('total_invested'),
            'total_commission_earned' => $investors->sum('total_commission_earned'),
            'total_commission_paid' => $investors->sum('total_commission_paid'),
            'total_outstanding' => $investors->sum(fn ($i) => $i->outstanding_commission),
        ];
    }
}
