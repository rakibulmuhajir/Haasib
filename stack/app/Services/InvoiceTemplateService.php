<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InvoiceTemplateService extends BaseService
{
    use AuditLogging;

    public function __construct(ServiceContext $context)
    {
        parent::__construct($context);
    }

    /**
     * Create a new invoice template.
     */
    public function createTemplate(Company $company, array $data, User $user): InvoiceTemplate
    {
        // Validate company access
        $this->validateCompanyAccess($company->id);
        
        // Set RLS context
        $this->setRlsContext($company->id);

        // Validate template data
        $this->validateTemplateData($data);

        return $this->executeInTransaction(function () use ($company, $data, $user) {
            $template = InvoiceTemplate::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'currency' => $data['currency'],
                'template_data' => $this->prepareTemplateData($data),
                'settings' => $this->prepareTemplateSettings($data),
                'is_active' => $data['is_active'] ?? true,
                'created_by_user_id' => $user->id,
            ]);

            // Create audit log entry
            $this->audit('template.created', [
                'company_id' => $company->id,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'currency' => $template->currency,
                'customer_id' => $template->customer_id,
                'is_active' => $template->is_active,
                'created_by_user_id' => $user->id,
                'created_by_context_user_id' => $this->getUserId(),
                'request_id' => $this->getRequestId(),
            ]);

            return $template;
        });
    }

    /**
     * Update an existing invoice template.
     */
    public function updateTemplate(InvoiceTemplate $template, array $data, User $user): InvoiceTemplate
    {
        // Validate company access
        $this->validateCompanyAccess($template->company_id);
        
        // Set RLS context
        $this->setRlsContext($template->company_id);

        // Validate template data
        $this->validateTemplateData($data, $template);

        return $this->executeInTransaction(function () use ($template, $data, $user) {
            // Store before state for audit
            $beforeState = $template->toArray();

            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description,
                'customer_id' => $data['customer_id'] ?? $template->customer_id,
                'currency' => $data['currency'] ?? $template->currency,
                'template_data' => isset($data['template_data'])
                    ? $this->prepareTemplateData($data)
                    : $template->template_data,
                'settings' => isset($data['settings'])
                    ? $this->prepareTemplateSettings($data)
                    : $template->settings,
                'is_active' => $data['is_active'] ?? $template->is_active,
            ]);

            // Create audit log entry with before/after states
            $this->audit('template.updated', [
                'company_id' => $template->company_id,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'updated_by_user_id' => $user->id,
                'updated_by_context_user_id' => $this->getUserId(),
                'changes' => array_keys($data),
                'before_state' => $beforeState,
                'after_state' => $template->fresh()->toArray(),
                'request_id' => $this->getRequestId(),
            ]);

            return $template->fresh();
        });
    }

    /**
     * Delete an invoice template.
     */
    public function deleteTemplate(InvoiceTemplate $template, User $user): void
    {
        // Validate company access
        $this->validateCompanyAccess($template->company_id);
        
        // Set RLS context
        $this->setRlsContext($template->company_id);

        $this->executeInTransaction(function () use ($template, $user) {
            // Store template data for audit before deletion
            $templateData = $template->toArray();
            
            $template->delete();

            // Create audit log entry
            $this->audit('template.deleted', [
                'company_id' => $template->company_id,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'deleted_by_user_id' => $user->id,
                'deleted_by_context_user_id' => $this->getUserId(),
                'deleted_template_data' => $templateData,
                'request_id' => $this->getRequestId(),
            ]);
        });
    }

    /**
     * Apply template to create a new invoice.
     */
    public function applyTemplate(InvoiceTemplate $template, ?Customer $customer = null, array $overrides = [], ?User $user = null): array
    {
        if ($user) {
            $this->authService->canAccessCompany($user, $template->company);
        }

        if (! $template->is_active) {
            throw new \InvalidArgumentException('Cannot apply inactive template');
        }

        // Validate template structure
        $validationErrors = $template->validateTemplate();
        if (! empty($validationErrors)) {
            throw ValidationException::withMessages($validationErrors);
        }

        // Apply template to create invoice data
        $invoiceData = $template->applyToInvoice($customer, $overrides);

        // Ensure currency compatibility
        if ($customer && $customer->currency && $customer->currency !== $template->currency) {
            throw new \InvalidArgumentException('Template currency does not match customer currency');
        }

        Log::info('Invoice template applied', [
            'template_id' => $template->id,
            'company_id' => $template->company_id,
            'customer_id' => $customer?->id,
            'user_id' => $user?->id,
            'overrides_count' => count($overrides),
        ]);

        return $invoiceData;
    }

    /**
     * Create template from existing invoice.
     */
    public function createTemplateFromInvoice(Invoice $invoice, string $name, ?string $description = null, ?User $user = null): InvoiceTemplate
    {
        if ($user) {
            $this->authService->canAccessCompany($user, $invoice->company);
        }

        return DB::transaction(function () use ($invoice, $name, $description, $user) {
            $templateData = [
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'payment_terms' => $invoice->issue_date->diffInDays($invoice->due_date),
                'line_items' => $this->convertLineItemsToTemplate($invoice->lineItems),
            ];

            $template = InvoiceTemplate::create([
                'company_id' => $invoice->company_id,
                'name' => $name,
                'description' => $description ?? "Template created from invoice {$invoice->invoice_number}",
                'customer_id' => $invoice->customer_id,
                'currency' => $invoice->currency,
                'template_data' => $templateData,
                'settings' => [
                    'auto_number' => true,
                    'number_prefix' => 'TPL-',
                    'send_email' => false,
                    'generate_pdf' => false,
                    'source_invoice_id' => $invoice->id,
                    'source_invoice_number' => $invoice->invoice_number,
                ],
                'is_active' => true,
                'created_by_user_id' => $user?->id ?? auth()->id(),
            ]);

            Log::info('Invoice template created from invoice', [
                'template_id' => $template->id,
                'company_id' => $template->company_id,
                'source_invoice_id' => $invoice->id,
                'user_id' => $user?->id,
                'name' => $template->name,
            ]);

            return $template;
        });
    }

    /**
     * Get templates for a company with filtering options.
     */
    public function getTemplatesForCompany(Company $company, User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        // Validate company access
        $this->validateCompanyAccess($company->id);
        
        // Set RLS context
        $this->setRlsContext($company->id);

        $query = InvoiceTemplate::query()
            ->forCompany($company->id)
            ->with(['customer', 'creator']);

        // Apply filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['customer_id'])) {
            if ($filters['customer_id'] === 'general') {
                $query->whereNull('customer_id');
            } else {
                $query->where('customer_id', $filters['customer_id']);
            }
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Duplicate a template with optional modifications.
     */
    public function duplicateTemplate(InvoiceTemplate $template, string $newName, array $modifications = [], ?User $user = null): InvoiceTemplate
    {
        if ($user) {
            $this->authService->canAccessCompany($user, $template->company);
        }

        return DB::transaction(function () use ($template, $newName, $modifications, $user) {
            $duplicate = $template->duplicate($newName, $modifications);

            Log::info('Invoice template duplicated', [
                'original_template_id' => $template->id,
                'duplicate_template_id' => $duplicate->id,
                'company_id' => $template->company_id,
                'user_id' => $user?->id,
                'new_name' => $newName,
            ]);

            return $duplicate;
        });
    }

    /**
     * Validate template data structure.
     */
    protected function validateTemplateData(array $data, ?InvoiceTemplate $existingTemplate = null): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'nullable|uuid|exists:pgsql.acct.customers,id',
            'currency' => 'required|string|size:3',
            'template_data.line_items' => 'required|array|min:1',
            'template_data.line_items.*.description' => 'required|string',
            'template_data.line_items.*.quantity' => 'required|numeric|min:0',
            'template_data.line_items.*.unit_price' => 'required|numeric|min:0',
            'template_data.line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'template_data.line_items.*.discount_amount' => 'nullable|numeric|min:0',
        ];

        // If updating, exclude the current template from unique checks
        if ($existingTemplate) {
            // Add any update-specific validation rules here
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Prepare template data for storage.
     */
    protected function prepareTemplateData(array $data): array
    {
        $templateData = $data['template_data'] ?? [];

        return [
            'notes' => $templateData['notes'] ?? null,
            'terms' => $templateData['terms'] ?? null,
            'payment_terms' => (int) ($templateData['payment_terms'] ?? 30),
            'line_items' => $this->normalizeLineItems($templateData['line_items'] ?? []),
        ];
    }

    /**
     * Prepare template settings for storage.
     */
    protected function prepareTemplateSettings(array $data): array
    {
        $defaultSettings = [
            'auto_number' => true,
            'number_prefix' => 'TPL-',
            'send_email' => false,
            'generate_pdf' => false,
            'reminder_settings' => [
                'enabled' => false,
                'days_before_due' => 7,
                'days_overdue' => 14,
            ],
        ];

        return array_merge($defaultSettings, $data['settings'] ?? []);
    }

    /**
     * Normalize line items data.
     */
    protected function normalizeLineItems(array $lineItems): array
    {
        return collect($lineItems)->map(function ($item, $index) {
            return [
                'id' => $item['id'] ?? 'item-'.uniqid(),
                'description' => $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'discount_amount' => (float) ($item['discount_amount'] ?? 0),
            ];
        })->toArray();
    }

    /**
     * Convert invoice line items to template format.
     */
    protected function convertLineItemsToTemplate($lineItems): array
    {
        return $lineItems->map(function ($item) {
            return [
                'id' => 'item-'.uniqid(),
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate ?? 0,
                'discount_amount' => $item->discount_amount ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get template statistics for a company.
     */
    public function getTemplateStatistics(Company $company, User $user): array
    {
        // Validate company access
        $this->validateCompanyAccess($company->id);
        
        // Set RLS context
        $this->setRlsContext($company->id);

        $statistics = [
            'total_templates' => InvoiceTemplate::forCompany($company->id)->count(),
            'active_templates' => InvoiceTemplate::forCompany($company->id)->active()->count(),
            'inactive_templates' => InvoiceTemplate::forCompany($company->id)->where('is_active', false)->count(),
            'general_templates' => InvoiceTemplate::forCompany($company->id)->whereNull('customer_id')->count(),
            'customer_specific_templates' => InvoiceTemplate::forCompany($company->id)->whereNotNull('customer_id')->count(),
            'currency_breakdown' => InvoiceTemplate::forCompany($company->id)
                ->selectRaw('currency, count(*) as count')
                ->groupBy('currency')
                ->pluck('count', 'currency')
                ->toArray(),
        ];

        // Create audit log entry for statistics access
        $this->audit('template.statistics_accessed', [
            'company_id' => $company->id,
            'accessed_by_user_id' => $user->id,
            'accessed_by_context_user_id' => $this->getUserId(),
            'statistics' => $statistics,
            'request_id' => $this->getRequestId(),
        ]);

        return $statistics;
    }
}
