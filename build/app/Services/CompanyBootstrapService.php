<?php

namespace App\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CompanyBankAccountSyncService;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Modules\Accounting\Services\FiscalYearService;
use App\Modules\Accounting\Services\PostingTemplateInstaller;
use App\Modules\FuelStation\Services\FuelStationModuleInstaller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CompanyBootstrapService
{
    public function bootstrap(Company $company, ?string $industryCode, ?string $userId = null): void
    {
        if (! $industryCode) {
            return;
        }

        $this->ensureIndustryDefaults($company, $industryCode);
        $this->ensureBankAccount($company, $userId);

        $company = $company->fresh();

        app(CompanyBankAccountSyncService::class)->ensureForCompany($company->id, $userId);
        app(DefaultAccountProvisioner::class)->ensureCoreDefaults($company);
        app(DefaultAccountProvisioner::class)->ensureTransitAccounts($company->fresh());
        app(PostingTemplateInstaller::class)->ensureDefaults($company->fresh());
        app(FiscalYearService::class)->ensureCurrentFiscalYearExists($company->id);
    }

    private function ensureIndustryDefaults(Company $company, string $industryCode): void
    {
        try {
            app(CompanyOnboardingService::class)->setupCompanyIdentity($company, [
                'industry_code' => $industryCode,
                'timezone' => $company->timezone ?? 'UTC',
            ]);
        } catch (\Throwable $e) {
            Log::error('Company bootstrap failed to apply industry defaults', [
                'company_id' => $company->id,
                'industry_code' => $industryCode,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        if ($industryCode === 'fuel_station') {
            try {
                app(FuelStationModuleInstaller::class)->ensureMigrationsApplied();
            } catch (\Throwable $e) {
                Log::error('Company bootstrap failed to prepare Fuel Station module', [
                    'company_id' => $company->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function ensureBankAccount(Company $company, ?string $userId): void
    {
        if (! Schema::connection('pgsql')->hasTable('acct.accounts')) {
            return;
        }

        $hasBankAccount = Account::where('company_id', $company->id)
            ->where('subtype', 'bank')
            ->whereNull('deleted_at')
            ->exists();

        $hasCashAccount = Account::where('company_id', $company->id)
            ->where('subtype', 'cash')
            ->whereNull('deleted_at')
            ->exists();

        $currency = strtoupper((string) ($company->base_currency ?: 'USD'));

        if (! $hasBankAccount) {
            $bankCode = $this->nextAvailableCode($company->id, 1000, 1049);
            Account::create([
                'company_id' => $company->id,
                'code' => $bankCode,
                'name' => 'Operating Bank Account',
                'type' => 'asset',
                'subtype' => 'bank',
                'normal_balance' => 'debit',
                'currency' => $currency,
                'is_contra' => false,
                'is_active' => true,
                'is_system' => true,
                'description' => 'Auto-created default bank account.',
                'created_by_user_id' => $userId,
            ]);
        }

        if (! $hasCashAccount) {
            $cashCode = $this->nextAvailableCode($company->id, 1050, 1099);
            Account::create([
                'company_id' => $company->id,
                'code' => $cashCode,
                'name' => 'Cash on Hand',
                'type' => 'asset',
                'subtype' => 'cash',
                'normal_balance' => 'debit',
                'currency' => $currency,
                'is_contra' => false,
                'is_active' => true,
                'is_system' => true,
                'description' => 'Auto-created cash account for walk-in receipts.',
                'created_by_user_id' => $userId,
            ]);
        }
    }

    private function nextAvailableCode(string $companyId, int $startCode, int $endCode): string
    {
        for ($code = $startCode; $code <= $endCode; $code++) {
            $exists = Account::where('company_id', $companyId)
                ->where('code', (string) $code)
                ->exists();

            if (! $exists) {
                return (string) $code;
            }
        }

        return (string) $startCode;
    }
}
