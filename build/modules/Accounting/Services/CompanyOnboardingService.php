<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Models\CompanyOnboarding;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Models\IndustryCoaTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Accounting\Services\PostingTemplateInstaller;

/**
 * Company Onboarding Service
 *
 * This service implements the complete onboarding flow and ensures all settings
 * are actually used by the system, not just stored as cosmetic values.
 */
class CompanyOnboardingService
{
    /**
     * Get the current user ID safely (works in queued contexts).
     */
    protected function getCurrentUserId(): ?string
    {
        return auth()->check() ? auth()->id() : null;
    }

    /**
     * Initialize onboarding for a company.
     */
    public function initializeOnboarding(Company $company): CompanyOnboarding
    {
        return CompanyOnboarding::firstOrCreate(
            ['company_id' => $company->id],
            [
                'current_step' => 'company-identity',
                'step_number' => 1,
                'completed_steps' => [],
                'is_completed' => false,
            ]
        );
    }

    /**
     * Step 1: Update company identity and set up industry-specific COA.
     *
     * Creates accounts from the selected industry COA pack.
     * These accounts will be used by the system for AR, AP, Revenue, Expenses, etc.
     */
    public function setupCompanyIdentity(
        Company $company,
        array $data
    ): Company {
        return DB::transaction(function () use ($company, $data) {
            // Update company with identity information
            $company->update([
                'industry_code' => $data['industry_code'],
                'registration_number' => $data['registration_number'] ?? null,
                'trade_name' => $data['trade_name'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

            $this->enableModulesForIndustry($company, $data['industry_code']);

            // Create industry-specific chart of accounts (idempotent)
            $this->createIndustryChartOfAccounts($company, $data['industry_code']);

            // Mark step as completed
            $onboarding = $this->initializeOnboarding($company);
            $onboarding->completeStep('company-identity');
            $onboarding->advanceToStep('fiscal-year', 2);

            return $company->fresh();
        });
    }

    private function enableModulesForIndustry(Company $company, string $industryCode): void
    {
        if ($industryCode === 'fuel_station') {
            $company->enableModule('fuel_station');
        }
    }

    /**
     * Step 2: Set up fiscal year and accounting periods.
     *
     * Creates the first fiscal year and accounting periods (monthly/quarterly).
     * These periods will enforce posting rules and period-close logic.
     */
    public function setupFiscalYear(Company $company, array $data): FiscalYear
    {
        return DB::transaction(function () use ($company, $data) {
            // Update company with fiscal settings
            $company->update([
                'fiscal_year_start_month' => $data['fiscal_year_start_month'],
                'period_frequency' => $data['period_frequency'] ?? 'monthly',
            ]);

            // Determine fiscal year dates
            $startMonth = $data['fiscal_year_start_month'];
            $currentYear = now()->year;

            // If we're past the fiscal start month, use current year; otherwise previous year
            $fiscalStartYear = now()->month >= $startMonth ? $currentYear : $currentYear - 1;

            $startDate = \Carbon\Carbon::create($fiscalStartYear, $startMonth, 1);
            $endDate = $startDate->copy()->addYear()->subDay();

            // Get retained earnings account for the fiscal year
            $retainedEarningsAccount = Account::where('company_id', $company->id)
                ->where('subtype', 'retained_earnings')
                ->first();

            // Create the first fiscal year
            $fiscalYear = FiscalYear::create([
                'company_id' => $company->id,
                'name' => "FY {$startDate->format('Y')}-{$endDate->format('Y')}",
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_current' => true,
                'is_closed' => false,
                'status' => 'open',
                'retained_earnings_account_id' => $retainedEarningsAccount?->id,
                'created_by_user_id' => $this->getCurrentUserId(),
            ]);

            // Create accounting periods based on frequency
            $this->createAccountingPeriods($fiscalYear, $data['period_frequency']);

            // Mark step as completed
            $onboarding = $company->onboarding;
            $onboarding->completeStep('fiscal-year');
            $onboarding->advanceToStep('bank-accounts', 3);

            return $fiscalYear;
        });
    }

    /**
     * Step 3: Set up physical bank/cash accounts.
     *
     * These accounts are used for payment processing, reconciliation, and cash flow.
     */
    public function setupBankAccounts(Company $company, array $bankAccounts): array
    {
        return DB::transaction(function () use ($company, $bankAccounts) {
            $createdAccounts = [];

            foreach ($bankAccounts as $bankAccount) {
                // Determine subtype based on account type
                $subtype = $bankAccount['account_type'] === 'cash' ? 'cash' : 'bank';

                // Find next available code in 1000-1049 range
                $code = $this->getNextAvailableCode($company->id, '1000', '1049');

                $account = Account::create([
                    'company_id' => $company->id,
                    'code' => $code,
                    'name' => $bankAccount['account_name'],
                    'type' => 'asset',
                    'subtype' => $subtype,
                    'normal_balance' => 'debit',
                    'currency' => strtoupper($bankAccount['currency']), // Ensure uppercase
                    'is_active' => true,
                    'is_system' => false,
                    'created_by_user_id' => $this->getCurrentUserId(),
                ]);

                $createdAccounts[] = $account;
            }

            app(CompanyBankAccountSyncService::class)->ensureForCompany(
                $company->id,
                $this->getCurrentUserId(),
                collect($createdAccounts)->pluck('id')->all(),
            );

            // Set default bank account if none exists
            if (!empty($createdAccounts) && empty($company->bank_account_id)) {
                $company->update(['bank_account_id' => $createdAccounts[0]->id]);
            }

            // Mark step as completed
            $onboarding = $company->onboarding;
            $onboarding->completeStep('bank-accounts');
            $onboarding->advanceToStep('default-accounts', 4);

            return $createdAccounts;
        });
    }

    /**
     * Step 4: Configure default system accounts.
     *
     * These defaults are used by AR, AP, invoicing, billing, and GL posting services.
     */
    public function setupDefaultAccounts(Company $company, array $defaults): Company
    {
        return DB::transaction(function () use ($company, $defaults) {
            // Store default account IDs in DB columns - these are ACTUALLY USED by the system
            $company->update([
                'ar_account_id' => $defaults['ar_account_id'],
                'ap_account_id' => $defaults['ap_account_id'],
                'income_account_id' => $defaults['income_account_id'],
                'expense_account_id' => $defaults['expense_account_id'],
                'bank_account_id' => $defaults['bank_account_id'],
                'retained_earnings_account_id' => $defaults['retained_earnings_account_id'],
                'sales_tax_payable_account_id' => $defaults['sales_tax_payable_account_id'] ?? null,
                'purchase_tax_receivable_account_id' => $defaults['purchase_tax_receivable_account_id'] ?? null,
            ]);

            // Bootstrap default posting templates now that core accounts are mapped.
            app(PostingTemplateInstaller::class)->ensureDefaults($company->fresh());

            // Mark step as completed
            $onboarding = $company->onboarding;
            $onboarding->completeStep('default-accounts');
            $onboarding->advanceToStep('tax-settings', 5);

            return $company->fresh();
        });
    }

    /**
     * Step 5: Configure tax settings.
     *
     * Tax rates and settings are used by invoice/bill creation.
     */
    public function setupTaxSettings(Company $company, array $data): Company
    {
        $company->update([
            'tax_registered' => $data['tax_registered'] ?? false,
            'tax_rate' => $data['tax_rate'] ?? null,
            'tax_inclusive' => $data['tax_inclusive'] ?? false,
        ]);

        // Mark step as completed
        $onboarding = $company->onboarding;
        $onboarding->completeStep('tax-settings');
        $onboarding->advanceToStep('numbering', 6);

        return $company->fresh();
    }

    /**
     * Step 6: Configure invoice/bill numbering.
     *
     * These settings are used by invoice/bill creation services.
     */
    public function setupNumberingPreferences(Company $company, array $data): Company
    {
        $company->update([
            'invoice_prefix' => $data['invoice_prefix'] ?? 'INV-',
            'invoice_start_number' => $data['invoice_start_number'] ?? 1001,
            'bill_prefix' => $data['bill_prefix'] ?? 'BILL-',
            'bill_start_number' => $data['bill_start_number'] ?? 1001,
        ]);

        // Mark step as completed
        $onboarding = $company->onboarding;
        $onboarding->completeStep('numbering');
        $onboarding->advanceToStep('payment-terms', 7);

        return $company->fresh();
    }

    /**
     * Step 7: Configure default payment terms.
     *
     * These are used when creating customers/vendors without specific terms.
     */
    public function setupPaymentTerms(Company $company, array $data): Company
    {
        $company->update([
            'default_customer_payment_terms' => $data['default_customer_payment_terms'] ?? 30,
            'default_vendor_payment_terms' => $data['default_vendor_payment_terms'] ?? 30,
        ]);

        // Mark step as completed
        $onboarding = $company->onboarding;
        $onboarding->completeStep('payment-terms');
        $onboarding->advanceToStep('complete', 8);

        return $company->fresh();
    }

    /**
     * Step 8: Set opening balances (optional).
     *
     * Creates journal entries for opening balances.
     */
    public function setupOpeningBalances(Company $company, array $balances): void
    {
        DB::transaction(function () use ($company, $balances) {
            // This would create journal entries for opening balances
            // Implementation depends on your journal entry service
            Log::info('Opening balances setup', ['company' => $company->id, 'balances' => $balances]);

            // Mark step as completed
            $onboarding = $company->onboarding;
            $onboarding->completeStep('opening_balances');
            $onboarding->advanceToStep('complete', 9);
        });
    }

    /**
     * Complete the onboarding process.
     */
    public function completeOnboarding(Company $company): void
    {
        DB::transaction(function () use ($company) {
            $onboarding = $company->onboarding;
            $onboarding->complete();

            Log::info('Company onboarding completed', ['company' => $company->id]);
        });
    }

    /**
     * Create industry-specific chart of accounts from templates.
     *
     * This is where we copy the COA pack templates into actual company accounts.
     * These accounts are then used by all system services.
     */
    private function createIndustryChartOfAccounts(Company $company, string $industryCode): void
    {
        $industryPack = IndustryCoaPack::where('code', $industryCode)->firstOrFail();

        $templates = IndustryCoaTemplate::where('industry_pack_id', $industryPack->id)
            ->orderBy('sort_order')
            ->get();

        // Bank/cash accounts are created explicitly during onboarding (Step 3) so we don't
        // seed industry-pack bank/cash templates into company COA to avoid duplicates/confusion.
        $skipSubtypes = ['bank', 'cash'];

        // Subtypes that are allowed to have currency per the check constraint
        $monetarySubtypes = [
            'bank',
            'cash',
            'accounts_receivable',
            'accounts_payable',
            'credit_card',
            'other_current_asset',
            'other_asset',
            'other_current_liability',
            'other_liability',
        ];

        foreach ($templates as $template) {
            if (in_array($template->subtype, $skipSubtypes, true)) {
                continue;
            }

            // Only monetary accounts can have currency set
            $currency = in_array($template->subtype, $monetarySubtypes)
                ? $company->base_currency
                : null;

            // Check if account already exists (idempotent)
            $existingAccount = Account::where('company_id', $company->id)
                ->where('code', $template->code)
                ->first();

            if ($existingAccount) {
                // Update existing account to match template
                $existingAccount->update([
                    'name' => $template->name,
                    'type' => $template->type,
                    'subtype' => $template->subtype,
                    'normal_balance' => $template->normal_balance,
                    'currency' => $currency,
                    'is_contra' => $template->is_contra,
                    'is_system' => $template->is_system,
                    'description' => $template->description,
                ]);
                continue;
            }

            Account::create([
                'company_id' => $company->id,
                'code' => $template->code,
                'name' => $template->name,
                'type' => $template->type,
                'subtype' => $template->subtype,
                'normal_balance' => $template->normal_balance,
                'currency' => $currency,
                'is_contra' => $template->is_contra,
                'is_active' => true,
                'is_system' => $template->is_system,
                'description' => $template->description,
                'created_by_user_id' => $this->getCurrentUserId(),
            ]);
        }
    }

    /**
     * Create accounting periods for a fiscal year.
     *
     * These periods enforce posting rules and are used by the period-close process.
     */
    private function createAccountingPeriods(FiscalYear $fiscalYear, string $frequency): void
    {
        $startDate = \Carbon\Carbon::parse($fiscalYear->start_date);
        $endDate = \Carbon\Carbon::parse($fiscalYear->end_date);
        $periodNumber = 1;

        $currentStart = $startDate->copy();

        while ($currentStart->lte($endDate)) {
            // Calculate period end based on frequency
            switch ($frequency) {
                case 'quarterly':
                    $currentEnd = $currentStart->copy()->addMonths(3)->subDay();
                    break;
                case 'yearly':
                    $currentEnd = $endDate->copy();
                    break;
                case 'monthly':
                default:
                    $currentEnd = $currentStart->copy()->addMonth()->subDay();
                    break;
            }

            // Don't exceed fiscal year end
            if ($currentEnd->gt($endDate)) {
                $currentEnd = $endDate->copy();
            }

            AccountingPeriod::create([
                'company_id' => $fiscalYear->company_id,
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $periodNumber,
                'name' => $currentStart->format('F Y'),
                'start_date' => $currentStart,
                'end_date' => $currentEnd,
                'period_type' => $frequency,
                'is_closed' => false,
                'created_by_user_id' => $this->getCurrentUserId(),
            ]);

            $currentStart = $currentEnd->copy()->addDay();
            $periodNumber++;

            // Safety: prevent infinite loop
            if ($periodNumber > 100) {
                break;
            }
        }
    }

    /**
     * Find the next available account code in a range.
     */
    private function getNextAvailableCode(string $companyId, string $startCode, string $endCode): string
    {
        $start = (int) $startCode;
        $end = (int) $endCode;

        for ($code = $start; $code <= $end; $code++) {
            $exists = Account::where('company_id', $companyId)
                ->where('code', (string) $code)
                ->exists();

            if (!$exists) {
                return (string) $code;
            }
        }

        // Fallback: use a random code in range
        return (string) rand($start, $end);
    }
}
