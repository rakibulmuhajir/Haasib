<?php

namespace App\Support\Modules;

use App\Models\Company;

/**
 * Keyed registry of industry modules. Modules register from their service
 * provider's boot(); registering the same key again replaces the entry, so a
 * re-booted app (Octane, tests) never holds duplicates. Bound as a singleton.
 */
class ModuleRegistry
{
    /** @var array<string, ModuleManifest> */
    private array $modules = [];

    public function register(ModuleManifest $manifest): void
    {
        $this->modules[$manifest->key()] = $manifest;
    }

    public function get(string $key): ?ModuleManifest
    {
        return $this->modules[$key] ?? null;
    }

    /** @return array<string, ModuleManifest> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<string, ModuleManifest> */
    public function enabledFor(Company $company): array
    {
        return array_filter($this->modules, fn (ModuleManifest $m) => $m->isEnabledFor($company));
    }

    /** The first enabled module's wording for $key, else $default. */
    public function label(Company $company, string $key, string $default): string
    {
        foreach ($this->enabledFor($company) as $manifest) {
            if (isset($manifest->labels()[$key])) {
                return $manifest->labels()[$key];
            }
        }

        return $default;
    }
}
