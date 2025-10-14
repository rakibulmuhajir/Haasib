<?php

namespace Modules\Accounting\Services;

use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ModuleService
{
    /**
     * Return all modules in the catalog.
     */
    public function getAllModules(bool $activeOnly = true)
    {
        $query = Module::query()->orderBy('menu_order')->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function getEnabledModules(Company $company)
    {
        return $company->modules()
            ->wherePivot('is_active', true)
            ->orderBy('modules.name')
            ->get();
    }

    public function enableModule(Company $company, string $moduleKey, User $performedBy, array $settings = []): void
    {
        $module = $this->resolveModule($moduleKey);

        $this->validateDependencies($company, $module);
        $this->validateSettings($module, $settings);

        $company->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active' => true,
                'enabled_at' => now(),
                'enabled_by_user_id' => $performedBy->id,
                'settings' => json_encode($settings),
                'disabled_at' => null,
                'disabled_by_user_id' => null,
                'updated_at' => now(),
            ],
        ]);
    }

    public function disableModule(Company $company, string $moduleKey, User $performedBy): void
    {
        $module = $this->resolveModule($moduleKey);

        if (! $company->hasModuleEnabled($module->key)) {
            return;
        }

        $this->assertNoDependentsEnabled($company, $module);

        $company->modules()->updateExistingPivot($module->id, [
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_by_user_id' => $performedBy->id,
            'updated_at' => now(),
        ]);
    }

    public function toggleModule(Company $company, string $moduleKey, User $performedBy, array $settings = []): void
    {
        $module = $this->resolveModule($moduleKey);

        if ($company->hasModuleEnabled($module->key)) {
            $this->disableModule($company, $module->key, $performedBy);
        } else {
            $this->enableModule($company, $module->key, $performedBy, $settings);
        }
    }

    public function updateModuleSettings(Company $company, string $moduleKey, array $settings): void
    {
        $module = $this->resolveModule($moduleKey);
        $this->validateSettings($module, $settings);

        $company->modules()->updateExistingPivot($module->id, [
            'settings' => json_encode($settings),
            'updated_at' => now(),
        ]);
    }

    protected function resolveModule(string $key): Module
    {
        $module = Module::where('key', $key)->orWhere('name', $key)->first();

        if (! $module) {
            throw new \InvalidArgumentException("Module '{$key}' not found.");
        }

        if (! $module->isActive()) {
            throw new \InvalidArgumentException("Module '{$module->name}' is not active.");
        }

        return $module;
    }

    protected function validateDependencies(Company $company, Module $module): void
    {
        $dependencies = $module->dependencies ?? [];

        foreach ($dependencies as $dependencyKey) {
            if (! $company->hasModuleEnabled($dependencyKey)) {
                throw new \InvalidArgumentException(
                    "Module '{$module->name}' requires '{$dependencyKey}' to be enabled first."
                );
            }
        }
    }

    protected function assertNoDependentsEnabled(Company $company, Module $module): void
    {
        $dependent = $company->modules()
            ->wherePivot('is_active', true)
            ->whereJsonContains('modules.dependencies', $module->key)
            ->first();

        if ($dependent) {
            throw new \InvalidArgumentException(
                "Module '{$module->name}' is required by '{$dependent->name}' and cannot be disabled."
            );
        }
    }

    protected function validateSettings(Module $module, array $settings): void
    {
        $schema = $module->getSettingSchema();

        if (empty($schema)) {
            return;
        }

        $rules = [];

        foreach ($schema as $group => $definition) {
            if (($definition['type'] ?? null) === 'object' && isset($definition['properties'])) {
                foreach ($definition['properties'] as $key => $property) {
                    $ruleKey = "{$group}.{$key}";
                    $rules[$ruleKey] = $this->translatePropertyToRules($property);
                }
            }
        }

        if (! empty($rules)) {
            $validator = Validator::make($settings, $rules);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }
    }

    protected function translatePropertyToRules(array $property): array
    {
        $rules = [];

        $rules[] = ($property['required'] ?? false) ? 'required' : 'sometimes';

        return match ($property['type'] ?? 'string') {
            'string' => array_filter(array_merge($rules, ['string', isset($property['max']) ? 'max:'.$property['max'] : null])),
            'integer' => array_filter(array_merge($rules, ['integer', isset($property['min']) ? 'min:'.$property['min'] : null, isset($property['max']) ? 'max:'.$property['max'] : null])),
            'boolean' => array_merge($rules, ['boolean']),
            'array' => array_merge($rules, ['array']),
            default => $rules,
        };
    }
}
