<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

class AmanatService
{
    public function __construct(
        private readonly GlPostingService $postingService,
    ) {}

    /**
     * Process an amanat deposit.
     *
     * GL Transaction:
     * Dr Cash (asset increases)
     * Cr Customer Amanat Liability (liability increases - we owe them fuel)
     */
    public function deposit(Customer $customer, array $data): AmanatTransaction
    {
        return DB::transaction(function () use ($customer, $data) {
            $company = app(CurrentCompany::class)->get();
            $amount = $data['amount'];

            // Ensure customer has fuel profile with amanat flag
            $profile = CustomerProfile::getOrCreateForCustomer($company->id, $customer->id);
            if (!$profile->is_amanat_holder) {
                $profile->update(['is_amanat_holder' => true]);
            }

            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));
            $cashAccount = $this->getCashAccount($company->id);
            $amanatLiabilityAccount = $this->getAmanatLiabilityAccount($company->id);

            // Create GL transaction
            $glTransaction = $this->postingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => 'AMANAT-DEP-' . strtoupper(substr($customer->id, 0, 8)) . '-' . now()->format('Ymd'),
                'transaction_type' => 'amanat_deposit',
                'date' => now()->toDateString(),
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Amanat deposit from: {$customer->name}",
                'reference_type' => 'fuel.amanat_transactions',
                'reference_id' => null, // Will update after creating transaction
            ], [
                [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Amanat deposit - {$customer->name}",
                ],
                [
                    'account_id' => $amanatLiabilityAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Amanat deposit - {$customer->name}",
                ],
            ]);

            // Create transaction record
            $transaction = AmanatTransaction::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'transaction_type' => AmanatTransaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by_user_id' => auth()->id(),
                'transaction_id' => $glTransaction->id,
            ]);

            // Update profile balance
            $profile->adjustAmanatBalance($amount);

            return $transaction;
        });
    }

    /**
     * Process an amanat withdrawal.
     *
     * GL Transaction:
     * Dr Customer Amanat Liability (liability decreases)
     * Cr Cash (asset decreases)
     */
    public function withdraw(Customer $customer, array $data): AmanatTransaction
    {
        return DB::transaction(function () use ($customer, $data) {
            $company = app(CurrentCompany::class)->get();
            $amount = $data['amount'];

            // Get profile and validate balance
            $profile = CustomerProfile::getOrCreateForCustomer($company->id, $customer->id);

            if ($amount > $profile->amanat_balance) {
                throw new \InvalidArgumentException(
                    "Withdrawal amount ({$amount}) exceeds available balance ({$profile->amanat_balance})."
                );
            }

            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));
            $cashAccount = $this->getCashAccount($company->id);
            $amanatLiabilityAccount = $this->getAmanatLiabilityAccount($company->id);

            // Create GL transaction (reversed from deposit)
            $glTransaction = $this->postingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => 'AMANAT-WDR-' . strtoupper(substr($customer->id, 0, 8)) . '-' . now()->format('Ymd'),
                'transaction_type' => 'amanat_withdrawal',
                'date' => now()->toDateString(),
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Amanat withdrawal to: {$customer->name}",
                'reference_type' => 'fuel.amanat_transactions',
                'reference_id' => null,
            ], [
                [
                    'account_id' => $amanatLiabilityAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Amanat withdrawal - {$customer->name}",
                ],
                [
                    'account_id' => $cashAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Amanat withdrawal - {$customer->name}",
                ],
            ]);

            // Create transaction record
            $transaction = AmanatTransaction::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'transaction_type' => AmanatTransaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by_user_id' => auth()->id(),
                'transaction_id' => $glTransaction->id,
            ]);

            // Update profile balance (negative for withdrawal)
            $profile->adjustAmanatBalance(-$amount);

            return $transaction;
        });
    }

    /**
     * Apply amanat balance to a fuel purchase.
     * Called from FuelSaleService when customer pays with amanat.
     *
     * GL Transaction:
     * Dr Amanat Liability (liability decreases - they used their deposit)
     * Cr Fuel Sales (revenue recognized)
     */
    public function applyToFuelPurchase(
        Customer $customer,
        float $amount,
        string $itemId,
        float $quantity,
        string $reference
    ): AmanatTransaction {
        return DB::transaction(function () use ($customer, $amount, $itemId, $quantity, $reference) {
            $company = app(CurrentCompany::class)->get();

            // Get profile and validate balance
            $profile = CustomerProfile::getOrCreateForCustomer($company->id, $customer->id);

            if ($amount > $profile->amanat_balance) {
                throw new \InvalidArgumentException(
                    "Purchase amount ({$amount}) exceeds available amanat balance ({$profile->amanat_balance})."
                );
            }

            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));
            $amanatLiabilityAccount = $this->getAmanatLiabilityAccount($company->id);
            $fuelSalesAccount = $this->getFuelSalesAccount($company->id);

            // Create GL transaction: Dr Amanat Liability, Cr Fuel Sales
            $glTransaction = $this->postingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => 'AMANAT-FUEL-' . strtoupper(substr($customer->id, 0, 8)) . '-' . now()->format('YmdHis'),
                'transaction_type' => 'amanat_fuel_purchase',
                'date' => now()->toDateString(),
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Amanat fuel purchase by: {$customer->name} ({$quantity}L)",
                'reference_type' => 'fuel.amanat_transactions',
                'reference_id' => null,
            ], [
                [
                    'account_id' => $amanatLiabilityAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Amanat fuel purchase - {$customer->name}",
                ],
                [
                    'account_id' => $fuelSalesAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Fuel sale from amanat - {$customer->name}",
                ],
            ]);

            // Create transaction record
            $transaction = AmanatTransaction::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'transaction_type' => AmanatTransaction::TYPE_FUEL_PURCHASE,
                'amount' => $amount,
                'fuel_item_id' => $itemId,
                'fuel_quantity' => $quantity,
                'reference' => $reference,
                'recorded_by_user_id' => auth()->id(),
                'transaction_id' => $glTransaction->id,
            ]);

            // Update profile balance (negative for purchase)
            $profile->adjustAmanatBalance(-$amount);

            return $transaction;
        });
    }

    /**
     * Get cash account.
     */
    private function getCashAccount(string $companyId): Account
    {
        return Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('name', 'like', '%Cash%')
            ->firstOrFail();
    }

    /**
     * Get or create amanat liability account.
     */
    private function getAmanatLiabilityAccount(string $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('type', 'liability')
            ->where('name', 'like', '%Amanat%')
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => '2200',
            'name' => 'Customer Amanat Deposits',
            'type' => 'liability',
            'subtype' => 'current_liability',
            'is_active' => true,
            'currency' => 'PKR',
        ]);
    }

    /**
     * Get fuel sales account.
     */
    private function getFuelSalesAccount(string $companyId): Account
    {
        return Account::where('company_id', $companyId)
            ->where('type', 'revenue')
            ->where(function ($query) {
                $query->where('name', 'like', '%Fuel%Sales%')
                    ->orWhere('code', '4100');
            })
            ->firstOrFail();
    }

    /**
     * Get amanat summary for dashboard.
     */
    public function getAmanatSummary(string $companyId): array
    {
        $profiles = CustomerProfile::where('company_id', $companyId)
            ->where('is_amanat_holder', true)
            ->get();

        return [
            'total_holders' => $profiles->count(),
            'total_balance' => $profiles->sum('amanat_balance'),
        ];
    }
}
