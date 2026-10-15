<?php

namespace App\Support\Modules;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * What an industry module tells the app shell about itself. Core never names a
 * module; it asks the registry for the manifests enabled for a company and reads
 * these answers. Every method has a "nothing to add" default, so a manifest
 * overrides only what it uses. Later tasks add methods here; each one keeps a
 * null/empty default.
 */
abstract class ModuleManifest
{
    /** settings.modules key, e.g. 'fuel_station'. */
    abstract public function key(): string;

    /** Display name used in messages, e.g. 'Fuel Station'. */
    public function name(): string
    {
        return ucfirst(str_replace('_', ' ', $this->key()));
    }

    /** Industry codes that imply this module even when settings.modules lacks it. */
    public function industryCodes(): array
    {
        return [];
    }

    /** Core modules that must stay on while this one is on, e.g. ['inventory']. */
    public function requires(): array
    {
        return [];
    }

    /** Module keys switched off when this one is switched on by industry. */
    public function conflicts(): array
    {
        return [];
    }

    /** Behaviours core may ask about without naming the module, e.g. 'daily_close'. */
    public function capabilities(): array
    {
        return [];
    }

    /** Wording overrides, e.g. ['consolidated.item' => 'Fuel']. */
    public function labels(): array
    {
        return [];
    }

    /** Where the company lands after create / on the companies page; null = the core home. */
    public function homeUrl(Company $company): ?string
    {
        return null;
    }

    /** Where a brand-new company of this industry lands; defaults to homeUrl(). */
    public function afterCreateUrl(Company $company): ?string
    {
        return $this->homeUrl($company);
    }

    /** The module's own response for the company home page; null = render the core home. */
    public function homeResponse(Request $request, Company $company): mixed
    {
        return null;
    }

    /** Where onboarding is handed over to the module for this company; null = core onboarding. */
    public function onboardingUrl(Company $company): ?string
    {
        return null;
    }

    /** Flash message shown when core onboarding hands over to onboardingUrl(). */
    public function onboardingHandoffMessage(): string
    {
        return 'Basic setup complete!';
    }

    /** The `flow` request value that marks a core onboarding step as taken inside the module's own flow. */
    public function onboardingFlow(): ?string
    {
        return null;
    }

    /** Installer and defaults, run at company bootstrap. Throws on failure; the caller logs it. */
    public function prepare(Company $company, ?string $userId): void
    {
    }

    /** Before the identity step: make the module ready. Throws on failure; the controller shows the error. */
    public function prepareOnboarding(Company $company): void
    {
    }

    /**
     * After the identity step is saved: seed the module's defaults. A non-null response is the
     * redirect the identity step returns instead of continuing core onboarding.
     */
    public function afterIdentity(Company $company): mixed
    {
        return null;
    }

    /** True when the module completed the 'default-accounts' onboarding step itself. */
    public function afterBankAccounts(Company $company, array $syncIds): bool
    {
        return false;
    }

    /** @return array<string, callable(Company, User): mixed> shared Inertia props under `auth`, keyed by prop name */
    public function sharedProps(): array
    {
        return [];
    }

    /**
     * Settings menu sections. Each entry is ['section' => array|null, 'before' => ?string]: the
     * section is inserted before the core section with that title, or at the end when `before` is null.
     *
     * @return array<int, array{section: ?array, before: ?string}>
     */
    public function settingsSections(Company $company, User $user, callable $section, callable $item, callable $can): array
    {
        return [];
    }

    /** True when a company with this module shows only its own permissions (plus payroll). */
    public function ownsPermissions(): bool
    {
        return false;
    }

    /** Permission prefixes (the part before the first dot) that belong to this module. */
    public function permissionPrefixes(): array
    {
        return [];
    }

    /** "What can this role do" rows for the user list; null = none. */
    public function permissionCapabilities(Company $company, string $role, callable $has): ?array
    {
        return null;
    }

    /** The module's own net profit for the range; null = use the ledger P&L. */
    public function netProfit(Company $company, string $from, string $to): ?float
    {
        return null;
    }

    /** A reason the range is closed to profit sharing (e.g. a locked month), or null. */
    public function periodLockReason(Company $company, string $from, string $to): ?string
    {
        return null;
    }

    public function isEnabledFor(Company $company): bool
    {
        if ($company->isModuleEnabled($this->key())) {
            return true;
        }
        $codes = $this->industryCodes();

        return in_array($company->industry_code, $codes, true) || in_array($company->industry, $codes, true);
    }
}
