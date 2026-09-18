<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\Accounting\Exceptions\IndustryCoaPackNotSeededException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Models\IndustryCoaTemplate;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Services\CompanyContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Repairs a company whose industry chart of accounts is incomplete -- typically
 * because it was onboarded while acct.industry_coa_templates was empty for its
 * industry pack (see IndustryCoaPackNotSeededException).
 *
 * This is CREATE-ONLY: unlike onboarding, it never updates an existing account.
 * A company may have legitimately renamed a template-seeded account (e.g.
 * "Operating Bank Account" -> "HBL Main Branch"); overwriting that would be
 * destructive. Existing accounts whose code matches a template but whose
 * subtype differs are reported as a warning for a human to resolve.
 */
class RepairChartOfAccounts extends Command
{
    protected $signature = 'accounting:repair-coa
        {--company= : Company slug or UUID to repair}
        {--all : Repair every company that has an industry_code}
        {--dry-run : Print the plan without creating anything}';

    protected $description = 'Create missing industry chart-of-accounts template accounts for a company (create-only; never modifies existing accounts)';

    /**
     * Posting-critical system identifiers and the subtype/type used to detect
     * their presence on a company's accounts (acct.accounts has no
     * system_identifier column populated in practice -- see
     * DefaultAccountProvisioner, which falls back to the same subtype checks).
     */
    private const SYSTEM_IDENTIFIERS = [
        'ar_control' => ['field' => 'subtype', 'value' => 'accounts_receivable'],
        'ap_control' => ['field' => 'subtype', 'value' => 'accounts_payable'],
        'retained_earnings' => ['field' => 'subtype', 'value' => 'retained_earnings'],
        'primary_revenue' => ['field' => 'type', 'value' => 'revenue'],
    ];

    public function handle(CompanyOnboardingService $onboardingService, CompanyContextService $context): int
    {
        $companyOption = $this->option('company');
        $all = (bool) $this->option('all');

        if (! $companyOption && ! $all) {
            $this->error('Pass --company=<slug|uuid> to repair one company, or --all to repair every company.');

            return self::FAILURE;
        }

        if ($companyOption && $all) {
            $this->error('Pass either --company or --all, not both.');

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($all) {
            // auth.companies is tenant-scoped under row level security, and a
            // repair pass by definition has no single tenant to be scoped to.
            $companies = $context->crossCompany(
                fn () => Company::whereNotNull('industry_code')->orderBy('name')->get()
            );
        } else {
            $company = $context->crossCompany(fn () => $this->resolveCompany($companyOption));

            if (! $company) {
                $this->error("No company found matching \"{$companyOption}\".");

                return self::FAILURE;
            }

            $companies = collect([$company]);
        }

        if ($companies->isEmpty()) {
            $this->warn('No companies to repair.');

            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn('DRY RUN -- no changes will be made.');
        }

        $hadError = false;

        foreach ($companies as $company) {
            $this->newLine();
            $this->line("=== {$company->name} ({$company->slug}) ===");

            if (! $company->industry_code) {
                $this->warn('No industry_code set -- skipping.');

                continue;
            }

            try {
                $context->withContext($company, function () use ($company, $onboardingService, $isDryRun) {
                    $this->repairCompany($company, $onboardingService, $isDryRun);
                });
            } catch (IndustryCoaPackNotSeededException $e) {
                $hadError = true;
                $this->error($e->getMessage());
            } catch (\Throwable $e) {
                $hadError = true;
                $this->error("Failed to repair {$company->name}: {$e->getMessage()}");
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }

    private function resolveCompany(string $identifier): ?Company
    {
        if (Str::isUuid($identifier)) {
            return Company::find($identifier);
        }

        return Company::where('slug', $identifier)->first();
    }

    private function repairCompany(Company $company, CompanyOnboardingService $onboardingService, bool $isDryRun): void
    {
        $industryCode = $company->industry_code;

        $industryPack = IndustryCoaPack::where('code', $industryCode)->first();

        if (! $industryPack) {
            $this->error("Industry code \"{$industryCode}\" has no acct.industry_coa_packs row.");

            return;
        }

        $templates = IndustryCoaTemplate::where('industry_pack_id', $industryPack->id)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            throw IndustryCoaPackNotSeededException::forIndustry($industryCode);
        }

        $existingAccounts = Account::where('company_id', $company->id)->get()->keyBy('code');

        $this->info("Industry: {$industryCode}");
        $this->info("Current accounts: {$existingAccounts->count()}");

        $missing = [];
        $conflicts = [];

        foreach ($templates as $template) {
            if (in_array($template->subtype, CompanyOnboardingService::SKIP_SUBTYPES, true)) {
                continue;
            }

            $existing = $existingAccounts->get($template->code);

            if (! $existing) {
                $missing[] = $template;

                continue;
            }

            if ($existing->subtype !== $template->subtype) {
                $conflicts[] = ['template' => $template, 'existing' => $existing];
            }
        }

        if (empty($missing)) {
            $this->info('No missing template accounts.');
        } else {
            $this->warn('Missing template accounts:');
            foreach ($missing as $template) {
                $this->line("  - {$template->code}  {$template->name}  (subtype: {$template->subtype})");
            }
        }

        if (! empty($conflicts)) {
            $this->warn('Conflicts (code matches a template but subtype differs -- NOT changed, needs human review):');
            foreach ($conflicts as $conflict) {
                $template = $conflict['template'];
                $existing = $conflict['existing'];
                $this->line("  - {$template->code}  existing subtype \"{$existing->subtype}\" vs template subtype \"{$template->subtype}\" (existing name: \"{$existing->name}\")");
            }
        }

        $missingIdentifiers = [];
        foreach (self::SYSTEM_IDENTIFIERS as $identifier => $check) {
            $present = $existingAccounts->contains(fn ($account) => $account->{$check['field']} === $check['value']);

            if (! $present) {
                $missingIdentifiers[] = $identifier;
            }
        }

        if (empty($missingIdentifiers)) {
            $this->info('All posting-critical identifiers present (ar_control, ap_control, retained_earnings, primary_revenue).');
        } else {
            $this->error('Missing posting-critical identifiers: '.implode(', ', $missingIdentifiers));
        }

        if ($isDryRun) {
            $this->line(empty($missing)
                ? 'Dry run: nothing to create.'
                : 'Dry run: would create '.count($missing).' account(s) listed above.');

            return;
        }

        if (empty($missing)) {
            return;
        }

        $result = $onboardingService->applyIndustryCoaTemplates($company, $industryCode, allowUpdateExisting: false);

        $this->info('Created '.count($result['created']).' account(s).');
    }
}
