<?php

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportTemplateService
{
    /**
     * Create a new report template
     */
    public function createTemplate(array $data): array
    {
        $validator = Validator::make($data, $this->getCreationRules());

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $validated = $validator->validated();

        // Set defaults
        $validated['template_id'] = Str::uuid()->toString();
        $validated['created_at'] = now();
        $validated['updated_at'] = now();
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Sanitize and validate configuration
        $this->validateAndSanitizeConfiguration($validated);

        try {
            DB::table('rpt.report_templates')->insert($validated);

            return $this->getTemplate($validated['template_id'], $validated['company_id']);
        } catch (\Exception $e) {
            Log::error('Failed to create report template', [
                'company_id' => $validated['company_id'] ?? null,
                'name' => $validated['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to create report template: '.$e->getMessage());
        }
    }

    /**
     * Update an existing report template
     */
    public function updateTemplate(string $templateId, string $companyId, array $data): array
    {
        $template = $this->getTemplate($templateId, $companyId);

        $validator = Validator::make($data, $this->getUpdateRules($template));

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $validated = $validator->validated();
        $validated['updated_at'] = now();

        // Sanitize and validate configuration if provided
        if (isset($validated['configuration'])) {
            $this->validateAndSanitizeConfiguration($validated + [
                'report_type' => $template['report_type'],
            ]);
        }

        try {
            DB::table('rpt.report_templates')
                ->where('template_id', $templateId)
                ->where('company_id', $companyId)
                ->update($validated);

            return $this->getTemplate($templateId, $companyId);
        } catch (\Exception $e) {
            Log::error('Failed to update report template', [
                'template_id' => $templateId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to update report template: '.$e->getMessage());
        }
    }

    /**
     * Delete a report template
     */
    public function deleteTemplate(string $templateId, string $companyId): void
    {
        $template = $this->getTemplate($templateId, $companyId);

        // Check if template is in use by active schedules
        $schedulesCount = DB::table('rpt.report_schedules')
            ->where('template_id', $templateId)
            ->where('company_id', $companyId)
            ->where('status', '!=', 'archived')
            ->count();

        if ($schedulesCount > 0) {
            throw new \InvalidArgumentException('Cannot delete template that is in use by active schedules');
        }

        // Check if template is a system template
        if ($template['is_system_template']) {
            throw new \InvalidArgumentException('Cannot delete system templates');
        }

        try {
            DB::table('rpt.report_templates')
                ->where('template_id', $templateId)
                ->where('company_id', $companyId)
                ->delete();

            Log::info('Report template deleted', [
                'template_id' => $templateId,
                'company_id' => $companyId,
                'name' => $template['name'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete report template', [
                'template_id' => $templateId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to delete report template: '.$e->getMessage());
        }
    }

    /**
     * Get a specific template
     */
    public function getTemplate(string $templateId, string $companyId): array
    {
        $template = DB::table('rpt.report_templates')
            ->where('template_id', $templateId)
            ->where('company_id', $companyId)
            ->first();

        if (! $template) {
            throw new \InvalidArgumentException('Template not found');
        }

        return (array) $template;
    }

    /**
     * List templates for a company
     */
    public function listTemplates(string $companyId, array $filters = []): array
    {
        $query = DB::table('rpt.report_templates')
            ->where('company_id', $companyId);

        // Apply filters
        if (isset($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        if (isset($filters['is_system_template'])) {
            $query->where('is_system_template', $filters['is_system_template']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Default ordering
        $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        return $query->get()->toArray();
    }

    /**
     * Get templates available for a user based on permissions
     */
    public function getAvailableTemplates(string $companyId, array $userRoles = []): array
    {
        return DB::table('rpt.report_templates')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($userRoles) {
                $query->where('is_public', true)
                    ->orWhereIn('applies_to_roles', $userRoles);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Duplicate a template
     */
    public function duplicateTemplate(string $templateId, string $companyId, array $overrides = []): array
    {
        $originalTemplate = $this->getTemplate($templateId, $companyId);

        $templateData = [
            'company_id' => $companyId,
            'name' => ($overrides['name'] ?? $originalTemplate['name']).' (Copy)',
            'description' => $overrides['description'] ?? $originalTemplate['description'],
            'report_type' => $originalTemplate['report_type'],
            'category' => $originalTemplate['category'],
            'configuration' => $originalTemplate['configuration'],
            'filters' => $originalTemplate['filters'],
            'parameters' => $originalTemplate['parameters'],
            'is_system_template' => false, // Copies are never system templates
            'is_public' => $overrides['is_public'] ?? false,
            'sort_order' => $overrides['sort_order'] ?? $this->getNextSortOrder($companyId),
            'created_by' => $overrides['created_by'] ?? null,
            'updated_by' => $overrides['updated_by'] ?? $overrides['created_by'] ?? null,
        ];

        return $this->createTemplate($templateData);
    }

    /**
     * Reorder templates
     */
    public function reorderTemplates(string $companyId, array $templateOrders): void
    {
        DB::transaction(function () use ($companyId, $templateOrders) {
            foreach ($templateOrders as $order) {
                if (! isset($order['template_id']) || ! isset($order['sort_order'])) {
                    continue;
                }

                DB::table('rpt.report_templates')
                    ->where('template_id', $order['template_id'])
                    ->where('company_id', $companyId)
                    ->update([
                        'sort_order' => $order['sort_order'],
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * Validate template configuration
     */
    public function validateTemplateConfiguration(array $data): array
    {
        $errors = [];

        // Basic validation
        if (empty($data['configuration'])) {
            return ['configuration' => ['Configuration is required']];
        }

        $config = $data['configuration'];
        $reportType = $data['report_type'] ?? null;

        switch ($reportType) {
            case 'income_statement':
                $errors = array_merge($errors, $this->validateIncomeStatementConfig($config));
                break;

            case 'balance_sheet':
                $errors = array_merge($errors, $this->validateBalanceSheetConfig($config));
                break;

            case 'cash_flow':
                $errors = array_merge($errors, $this->validateCashFlowConfig($config));
                break;

            case 'trial_balance':
                $errors = array_merge($errors, $this->validateTrialBalanceConfig($config));
                break;

            case 'kpi_dashboard':
                $errors = array_merge($errors, $this->validateKpiDashboardConfig($config));
                break;
        }

        return $errors;
    }

    /**
     * Get default configuration for a report type
     */
    public function getDefaultConfiguration(string $reportType): array
    {
        switch ($reportType) {
            case 'income_statement':
                return [
                    'sections' => [
                        ['id' => 'revenue', 'name' => 'Revenue', 'type' => 'revenue'],
                        ['id' => 'expenses', 'name' => 'Expenses', 'type' => 'expenses'],
                        ['id' => 'other_income', 'name' => 'Other Income', 'type' => 'other_income'],
                        ['id' => 'other_expenses', 'name' => 'Other Expenses', 'type' => 'other_expenses'],
                    ],
                    'show_comparisons' => true,
                    'show_variance_analysis' => true,
                    'currency_format' => 'symbol',
                    'decimal_places' => 2,
                ];

            case 'balance_sheet':
                return [
                    'sections' => [
                        ['id' => 'current_assets', 'name' => 'Current Assets', 'type' => 'assets', 'subcategory' => 'current'],
                        ['id' => 'non_current_assets', 'name' => 'Non-Current Assets', 'type' => 'assets', 'subcategory' => 'non_current'],
                        ['id' => 'current_liabilities', 'name' => 'Current Liabilities', 'type' => 'liabilities', 'subcategory' => 'current'],
                        ['id' => 'non_current_liabilities', 'name' => 'Non-Current Liabilities', 'type' => 'liabilities', 'subcategory' => 'non_current'],
                        ['id' => 'equity', 'name' => 'Equity', 'type' => 'equity'],
                    ],
                    'show_comparisons' => true,
                    'show_ratios' => true,
                    'currency_format' => 'symbol',
                    'decimal_places' => 2,
                ];

            case 'cash_flow':
                return [
                    'sections' => [
                        ['id' => 'operating', 'name' => 'Operating Activities', 'type' => 'operating'],
                        ['id' => 'investing', 'name' => 'Investing Activities', 'type' => 'investing'],
                        ['id' => 'financing', 'name' => 'Financing Activities', 'type' => 'financing'],
                    ],
                    'show_reconciliation' => true,
                    'show_comparisons' => true,
                    'currency_format' => 'symbol',
                    'decimal_places' => 2,
                ];

            case 'trial_balance':
                return [
                    'group_by' => 'account_type',
                    'show_zero_balances' => false,
                    'show_comparisons' => true,
                    'show_variance_analysis' => true,
                    'currency_format' => 'symbol',
                    'decimal_places' => 2,
                ];

            case 'kpi_dashboard':
                return [
                    'layout' => 'grid',
                    'refresh_interval' => 300, // 5 minutes
                    'auto_refresh' => true,
                    'cards' => [],
                ];

            default:
                return [];
        }
    }

    /**
     * Get template usage statistics
     */
    public function getTemplateUsage(string $templateId, string $companyId): array
    {
        $reportCount = DB::table('rpt.reports')
            ->where('template_id', $templateId)
            ->where('company_id', $companyId)
            ->count();

        $scheduleCount = DB::table('rpt.report_schedules')
            ->where('template_id', $templateId)
            ->where('company_id', $companyId)
            ->count();

        $lastUsed = DB::table('rpt.reports')
            ->where('template_id', $templateId)
            ->where('company_id', $companyId)
            ->max('created_at');

        return [
            'report_count' => $reportCount,
            'schedule_count' => $scheduleCount,
            'last_used_at' => $lastUsed,
        ];
    }

    /**
     * Get validation rules for creation
     */
    private function getCreationRules(): array
    {
        return [
            'company_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'report_type' => ['required', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom'])],
            'category' => ['required', Rule::in(['financial', 'operational', 'analytical'])],
            'configuration' => ['required', 'array'],
            'filters' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'is_system_template' => ['boolean'],
            'is_public' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'created_by' => ['nullable', 'uuid'],
            'updated_by' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Get validation rules for update
     */
    private function getUpdateRules(array $template): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', Rule::in(['financial', 'operational', 'analytical'])],
            'configuration' => ['sometimes', 'array'],
            'filters' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'updated_by' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Validate and sanitize configuration
     */
    private function validateAndSanitizeConfiguration(array &$data): void
    {
        $config = $data['configuration'];

        // Ensure configuration has required schema version
        if (! isset($config['schema_version'])) {
            $config['schema_version'] = '1.0';
        }

        // Sanitize JSON fields
        $data['configuration'] = json_decode(json_encode($config), true);
        $data['filters'] = isset($data['filters']) ? json_decode(json_encode($data['filters']), true) : null;
        $data['parameters'] = isset($data['parameters']) ? json_decode(json_encode($data['parameters']), true) : null;
    }

    /**
     * Validate income statement configuration
     */
    private function validateIncomeStatementConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['sections']) || ! is_array($config['sections'])) {
            $errors['sections'] = ['Income statement must have sections defined'];
        }

        // Validate each section
        if (isset($config['sections'])) {
            $validTypes = ['revenue', 'expenses', 'other_income', 'other_expenses'];

            foreach ($config['sections'] as $index => $section) {
                if (! isset($section['type']) || ! in_array($section['type'], $validTypes)) {
                    $errors["sections.{$index}.type"] = ['Invalid section type'];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate balance sheet configuration
     */
    private function validateBalanceSheetConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['sections']) || ! is_array($config['sections'])) {
            $errors['sections'] = ['Balance sheet must have sections defined'];
        }

        if (isset($config['sections'])) {
            $validTypes = ['assets', 'liabilities', 'equity'];
            $validSubcategories = ['current', 'non_current'];

            foreach ($config['sections'] as $index => $section) {
                if (! isset($section['type']) || ! in_array($section['type'], $validTypes)) {
                    $errors["sections.{$index}.type"] = ['Invalid section type'];
                }

                if (isset($section['subcategory']) && ! in_array($section['subcategory'], $validSubcategories)) {
                    $errors["sections.{$index}.subcategory"] = ['Invalid subcategory'];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate cash flow configuration
     */
    private function validateCashFlowConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['sections']) || ! is_array($config['sections'])) {
            $errors['sections'] = ['Cash flow statement must have sections defined'];
        }

        if (isset($config['sections'])) {
            $validTypes = ['operating', 'investing', 'financing'];

            foreach ($config['sections'] as $index => $section) {
                if (! isset($section['type']) || ! in_array($section['type'], $validTypes)) {
                    $errors["sections.{$index}.type"] = ['Invalid section type'];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate trial balance configuration
     */
    private function validateTrialBalanceConfig(array $config): array
    {
        $errors = [];

        $validGroups = ['account_type', 'account_category', 'none'];

        if (isset($config['group_by']) && ! in_array($config['group_by'], $validGroups)) {
            $errors['group_by'] = ['Invalid group by option'];
        }

        return $errors;
    }

    /**
     * Validate KPI dashboard configuration
     */
    private function validateKpiDashboardConfig(array $config): array
    {
        $errors = [];

        $validLayouts = ['grid', 'list', 'custom'];

        if (isset($config['layout']) && ! in_array($config['layout'], $validLayouts)) {
            $errors['layout'] = ['Invalid layout option'];
        }

        if (isset($config['cards']) && ! is_array($config['cards'])) {
            $errors['cards'] = ['Cards must be an array'];
        }

        return $errors;
    }

    /**
     * Get next sort order for company
     */
    private function getNextSortOrder(string $companyId): int
    {
        $maxOrder = DB::table('rpt.report_templates')
            ->where('company_id', $companyId)
            ->max('sort_order');

        return ($maxOrder ?? 0) + 10;
    }
}
