<?php

namespace Modules\Accounting\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\Module;
use Modules\Accounting\Models\User;

class EnableModule
{
    /**
     * Enable a module for a company.
     *
     * @param  Module|string  $module
     *
     * @throws \Exception
     */
    public function execute(Company $company, $module, User $enabledBy, array $settings = []): bool
    {
        // Get module object if string provided
        if (is_string($module)) {
            $module = Module::where('key', $module)->first();
            if (! $module) {
                throw new \InvalidArgumentException("Module '{$module}' not found");
            }
        }

        // Check if module is active
        if (! $module->isActive()) {
            throw new \InvalidArgumentException("Module '{$module->name}' is not active");
        }

        // Check if user has permission to manage modules for this company
        if (! $enabledBy->isAdminOfCompany($company) && ! $enabledBy->isSuperAdmin()) {
            throw new \InvalidArgumentException('User does not have permission to manage modules for this company');
        }

        // Check dependencies
        $missingDependencies = $module->checkDependencies();
        if (! empty($missingDependencies)) {
            throw new \InvalidArgumentException(
                "Module '{$module->name}' has missing dependencies: ".implode(', ', $missingDependencies)
            );
        }

        // Validate settings against module schema
        $this->validateSettings($module, $settings);

        // Begin transaction
        DB::beginTransaction();

        try {
            // Enable the module
            $companyModule = $company->enableModule($module, $enabledBy, $settings);

            // Enable dependencies if not already enabled
            $this->enableDependencies($company, $module, $enabledBy);

            // Run module enable hooks
            $this->runModuleEnableHooks($module, $company, $enabledBy);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Disable a module for a company.
     *
     * @param  Module|string  $module
     *
     * @throws \Exception
     */
    public function disable(Company $company, $module, User $disabledBy): bool
    {
        // Get module object if string provided
        if (is_string($module)) {
            $module = Module::where('key', $module)->first();
            if (! $module) {
                throw new \InvalidArgumentException("Module '{$module}' not found");
            }
        }

        // Check if user has permission
        if (! $disabledBy->isAdminOfCompany($company) && ! $disabledBy->isSuperAdmin()) {
            throw new \InvalidArgumentException('User does not have permission to manage modules for this company');
        }

        // Check if other modules depend on this one
        $dependentModules = $this->getDependentModules($company, $module);
        if (! empty($dependentModules)) {
            throw new \InvalidArgumentException(
                "Cannot disable '{$module->name}'. It is required by: ".implode(', ', $dependentModules)
            );
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Disable the module
            $result = $company->disableModule($module, $disabledBy);

            // Run module disable hooks
            $this->runModuleDisableHooks($module, $company, $disabledBy);

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle module on/off for a company.
     *
     * @param  Module|string  $module
     */
    public function toggle(Company $company, $module, User $user, array $settings = []): bool
    {
        if (is_string($module)) {
            $module = Module::where('key', $module)->first();
            if (! $module) {
                return false;
            }
        }

        if ($company->hasModuleEnabled($module->key)) {
            return $this->disable($company, $module, $user);
        } else {
            return $this->execute($company, $module, $user, $settings);
        }
    }

    /**
     * Enable multiple modules for a company.
     *
     * @return array Results
     */
    public function enableMultiple(Company $company, array $modules, User $enabledBy): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];

        // Sort modules by dependencies
        $sortedModules = $this->sortModulesByDependencies($modules);

        foreach ($sortedModules as $moduleKey) {
            try {
                $module = Module::where('key', $moduleKey)->first();
                if (! $module) {
                    $results['failed'][$moduleKey] = 'Module not found';

                    continue;
                }

                if ($company->hasModuleEnabled($moduleKey)) {
                    $results['skipped'][$moduleKey] = 'Already enabled';

                    continue;
                }

                $this->execute($company, $module, $enabledBy);
                $results['success'][] = $moduleKey;
            } catch (\Exception $e) {
                $results['failed'][$moduleKey] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Validate module settings against schema.
     *
     * @throws ValidationException
     */
    protected function validateSettings(Module $module, array $settings): void
    {
        $schema = $module->getSettingSchema();

        if (empty($schema)) {
            return; // No schema, accept all settings
        }

        $rules = [];
        foreach ($schema as $key => $field) {
            $fieldRules = [];

            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'sometimes';
            }

            if (isset($field['type'])) {
                switch ($field['type']) {
                    case 'string':
                        $fieldRules[] = 'string';
                        if (isset($field['max'])) {
                            $fieldRules[] = 'max:'.$field['max'];
                        }
                        break;
                    case 'integer':
                        $fieldRules[] = 'integer';
                        if (isset($field['min'])) {
                            $fieldRules[] = 'min:'.$field['min'];
                        }
                        if (isset($field['max'])) {
                            $fieldRules[] = 'max:'.$field['max'];
                        }
                        break;
                    case 'boolean':
                        $fieldRules[] = 'boolean';
                        break;
                    case 'array':
                        $fieldRules[] = 'array';
                        break;
                }
            }

            if (isset($field['options']) && is_array($field['options'])) {
                $fieldRules[] = 'in:'.implode(',', $field['options']);
            }

            $rules[$key] = $fieldRules;
        }

        $validator = \Validator::make($settings, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Enable module dependencies.
     */
    protected function enableDependencies(Company $company, Module $module, User $enabledBy): void
    {
        $dependencies = $module->getDependencies();

        foreach ($dependencies as $depKey) {
            $depModule = Module::where('key', $depKey)->active()->first();
            if ($depModule && ! $company->hasModuleEnabled($depKey)) {
                $company->enableModule($depModule, $enabledBy);
            }
        }
    }

    /**
     * Get modules that depend on the given module.
     */
    protected function getDependentModules(Company $company, Module $module): array
    {
        $enabledModules = $company->modules()->where('auth.company_modules.is_active', true)->get();
        $dependents = [];

        foreach ($enabledModules as $enabledModule) {
            if ($enabledModule->hasDependency($module->key)) {
                $dependents[] = $enabledModule->name;
            }
        }

        return $dependents;
    }

    /**
     * Sort modules by dependencies.
     */
    protected function sortModulesByDependencies(array $moduleKeys): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach ($moduleKeys as $moduleKey) {
            $this->visitModule($moduleKey, $visited, $visiting, $sorted);
        }

        return array_reverse($sorted);
    }

    /**
     * Visit module for dependency sorting (DFS).
     */
    protected function visitModule(string $moduleKey, array &$visited, array &$visiting, array &$sorted): void
    {
        if (in_array($moduleKey, $visited)) {
            return;
        }

        if (in_array($moduleKey, $visiting)) {
            throw new \InvalidArgumentException("Circular dependency detected involving module: {$moduleKey}");
        }

        $visiting[] = $moduleKey;

        $module = Module::where('key', $moduleKey)->first();
        if ($module) {
            foreach ($module->getDependencies() as $dep) {
                $this->visitModule($dep, $visited, $visiting, $sorted);
            }
        }

        $visiting = array_diff($visiting, [$moduleKey]);
        $visited[] = $moduleKey;
        $sorted[] = $moduleKey;
    }

    /**
     * Run module enable hooks.
     */
    protected function runModuleEnableHooks(Module $module, Company $company, User $enabledBy): void
    {
        // Emit event for module-specific logic
        event(new \Modules\Accounting\Events\ModuleEnabled($module, $company, $enabledBy));

        // Call module-specific enable method if exists
        $enableMethod = $module->key.'_enable';
        if (method_exists($this, $enableMethod)) {
            $this->$enableMethod($module, $company, $enabledBy);
        }
    }

    /**
     * Run module disable hooks.
     */
    protected function runModuleDisableHooks(Module $module, Company $company, User $disabledBy): void
    {
        // Emit event for module-specific logic
        event(new \Modules\Accounting\Events\ModuleDisabled($module, $company, $disabledBy));

        // Call module-specific disable method if exists
        $disableMethod = $module->key.'_disable';
        if (method_exists($this, $disableMethod)) {
            $this->$disableMethod($module, $company, $disabledBy);
        }
    }

    /**
     * Get module status for a company.
     */
    public function getModuleStatus(Company $company): array
    {
        $allModules = Module::active()->get();
        $enabledModules = $company->modules()->where('auth.company_modules.is_active', true)->get();

        $status = [];

        foreach ($allModules as $module) {
            $companyModule = $enabledModules->firstWhere('id', $module->id);

            $status[$module->key] = [
                'module' => $module,
                'enabled' => $companyModule ? true : false,
                'enabled_at' => $companyModule?->enabled_at,
                'settings' => $companyModule?->settings ?? [],
                'dependencies_met' => empty($module->checkDependencies()),
                'dependencies' => $module->getDependencies(),
                'dependents' => $this->getDependentModules($company, $module),
            ];
        }

        return $status;
    }
}
