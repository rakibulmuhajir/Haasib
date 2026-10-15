<?php

namespace App\Support\Modules;

use App\Models\Company;

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

    public function isEnabledFor(Company $company): bool
    {
        if ($company->isModuleEnabled($this->key())) {
            return true;
        }
        $codes = $this->industryCodes();

        return in_array($company->industry_code, $codes, true) || in_array($company->industry, $codes, true);
    }
}
