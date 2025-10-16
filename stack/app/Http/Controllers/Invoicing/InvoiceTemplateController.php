<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceTemplateController extends Controller
{
    public function __construct(
        private readonly InvoiceTemplateService $templateService
    ) {
        $this->middleware('auth');
        $this->middleware('permission:templates.view')->only(['index', 'show']);
        $this->middleware('permission:templates.create')->only(['store', 'createFromInvoice']);
        $this->middleware('permission:templates.update')->only(['update', 'activate', 'deactivate']);
        $this->middleware('permission:templates.delete')->only(['destroy']);
        $this->middleware('permission:templates.apply')->only(['apply']);
    }

    /**
     * Display a listing of invoice templates.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $filters = $request->only([
            'is_active',
            'customer_id',
            'currency',
            'search',
        ]);

        $templates = $this->templateService->getTemplatesForCompany($company, $request->user(), $filters);

        return response()->json([
            'templates' => $templates->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'customer' => $template->customer?->only(['id', 'name']),
                'currency' => $template->currency,
                'is_active' => $template->is_active,
                'line_items_count' => count($template->template_data['line_items'] ?? []),
                'total_amount' => $this->calculateTemplateTotal($template),
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ]),
            'statistics' => $this->templateService->getTemplateStatistics($company, $request->user()),
        ]);
    }

    /**
     * Store a newly created invoice template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'nullable|uuid|exists:acct.customers,id',
            'currency' => 'required|string|size:3',
            'template_data' => 'required|array',
            'template_data.notes' => 'nullable|string',
            'template_data.terms' => 'nullable|string',
            'template_data.payment_terms' => 'nullable|integer|min:1|max:365',
            'template_data.line_items' => 'required|array|min:1',
            'template_data.line_items.*.description' => 'required|string',
            'template_data.line_items.*.quantity' => 'required|numeric|min:0',
            'template_data.line_items.*.unit_price' => 'required|numeric|min:0',
            'template_data.line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'template_data.line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $company = $request->user()->currentCompany();
        $template = $this->templateService->createTemplate($company, $validated, $request->user());

        return response()->json([
            'message' => 'Template created successfully',
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'customer' => $template->customer?->only(['id', 'name']),
                'currency' => $template->currency,
                'is_active' => $template->is_active,
                'line_items_count' => count($template->template_data['line_items'] ?? []),
                'created_at' => $template->created_at,
            ],
        ], 201);
    }

    /**
     * Display the specified invoice template.
     */
    public function show(Request $request, InvoiceTemplate $template): JsonResponse
    {
        // Check authorization
        $this->authorize('view', $template);

        return response()->json([
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'customer' => $template->customer?->only(['id', 'name', 'email', 'phone']),
                'currency' => $template->currency,
                'is_active' => $template->is_active,
                'template_data' => $template->template_data,
                'settings' => $template->settings,
                'creator' => $template->creator?->only(['id', 'name']),
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
                'summary' => $template->getSummary(),
                'validation_errors' => $template->validateTemplate(),
            ],
        ]);
    }

    /**
     * Update the specified invoice template.
     */
    public function update(Request $request, InvoiceTemplate $template): JsonResponse
    {
        // Check authorization
        $this->authorize('update', $template);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'customer_id' => 'sometimes|nullable|uuid|exists:acct.customers,id',
            'currency' => 'sometimes|required|string|size:3',
            'template_data' => 'sometimes|required|array',
            'template_data.notes' => 'sometimes|nullable|string',
            'template_data.terms' => 'sometimes|nullable|string',
            'template_data.payment_terms' => 'sometimes|nullable|integer|min:1|max:365',
            'template_data.line_items' => 'sometimes|required|array|min:1',
            'template_data.line_items.*.description' => 'required|string',
            'template_data.line_items.*.quantity' => 'required|numeric|min:0',
            'template_data.line_items.*.unit_price' => 'required|numeric|min:0',
            'template_data.line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'template_data.line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'settings' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $updatedTemplate = $this->templateService->updateTemplate($template, $validated, $request->user());

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => [
                'id' => $updatedTemplate->id,
                'name' => $updatedTemplate->name,
                'description' => $updatedTemplate->description,
                'customer' => $updatedTemplate->customer?->only(['id', 'name']),
                'currency' => $updatedTemplate->currency,
                'is_active' => $updatedTemplate->is_active,
                'updated_at' => $updatedTemplate->updated_at,
            ],
        ]);
    }

    /**
     * Remove the specified invoice template.
     */
    public function destroy(Request $request, InvoiceTemplate $template): JsonResponse
    {
        // Check authorization
        $this->authorize('delete', $template);

        $this->templateService->deleteTemplate($template, $request->user());

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Apply template to create invoice data.
     */
    public function apply(Request $request, InvoiceTemplate $template): JsonResponse
    {
        // Check authorization
        $this->authorize('apply', $template);

        $validated = $request->validate([
            'customer_id' => 'nullable|uuid|exists:acct.customers,id',
            'overrides' => 'nullable|array',
            'overrides.currency' => 'nullable|string|size:3',
            'overrides.issue_date' => 'nullable|date',
            'overrides.due_date' => 'nullable|date|after:overrides.issue_date',
            'overrides.notes' => 'nullable|string',
            'overrides.terms' => 'nullable|string',
            'overrides.line_items_overrides' => 'nullable|array',
            'overrides.additional_line_items' => 'nullable|array',
        ]);

        $customer = null;
        if (isset($validated['customer_id'])) {
            $customer = Customer::find($validated['customer_id']);
            if (! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'Customer not found']);
            }
        }

        $invoiceData = $this->templateService->applyTemplate(
            $template,
            $customer,
            $validated['overrides'] ?? [],
            $request->user()
        );

        return response()->json([
            'message' => 'Template applied successfully',
            'invoice_data' => $invoiceData,
            'preview' => [
                'template_name' => $template->name,
                'customer' => $customer?->only(['id', 'name']),
                'subtotal' => $this->calculateInvoiceDataTotal($invoiceData)['subtotal'],
                'tax' => $this->calculateInvoiceDataTotal($invoiceData)['tax'],
                'total' => $this->calculateInvoiceDataTotal($invoiceData)['total'],
            ],
        ]);
    }

    /**
     * Create template from existing invoice.
     */
    public function createFromInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|uuid|exists:acct.invoices,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $invoice = \App\Models\Invoice::findOrFail($validated['invoice_id']);

        // Check authorization for the invoice
        if (! $request->user()->companies()->where('companies.id', $invoice->company_id)->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $template = $this->templateService->createTemplateFromInvoice(
            $invoice,
            $validated['name'],
            $validated['description'] ?? null,
            $request->user()
        );

        return response()->json([
            'message' => 'Template created from invoice successfully',
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'source_invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'created_at' => $template->created_at,
            ],
        ], 201);
    }

    /**
     * Duplicate an existing template.
     */
    public function duplicate(Request $request, InvoiceTemplate $template): JsonResponse
    {
        // Check authorization
        $this->authorize('create', $template->company);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'modifications' => 'nullable|array',
        ]);

        $duplicate = $this->templateService->duplicateTemplate(
            $template,
            $validated['name'],
            $validated['modifications'] ?? [],
            $request->user()
        );

        return response()->json([
            'message' => 'Template duplicated successfully',
            'template' => [
                'id' => $duplicate->id,
                'name' => $duplicate->name,
                'description' => $duplicate->description,
                'customer' => $duplicate->customer?->only(['id', 'name']),
                'currency' => $duplicate->currency,
                'is_active' => $duplicate->is_active,
                'created_at' => $duplicate->created_at,
            ],
        ], 201);
    }

    /**
     * Activate or deactivate a template.
     */
    public function toggleStatus(Request $request, InvoiceTemplate $template): JsonResponse
    {
        // Check authorization
        $this->authorize('update', $template);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $this->templateService->updateTemplate($template, [
            'is_active' => $validated['is_active'],
        ], $request->user());

        $status = $validated['is_active'] ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "Template {$status} successfully",
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'is_active' => $template->is_active,
                'updated_at' => $template->updated_at,
            ],
        ]);
    }

    /**
     * Get template statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $statistics = $this->templateService->getTemplateStatistics($company, $request->user());

        return response()->json([
            'statistics' => $statistics,
        ]);
    }

    /**
     * Get available customers for template assignment.
     */
    public function availableCustomers(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'customers' => $customers,
            'general_option' => [
                'id' => null,
                'name' => 'General (no specific customer)',
            ],
        ]);
    }

    /**
     * Validate template structure.
     */
    public function validate(Request $request, ?InvoiceTemplate $template = null): JsonResponse
    {
        $templateData = $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
            'template_data' => 'required|array',
            'template_data.line_items' => 'required|array|min:1',
            'template_data.line_items.*.description' => 'required|string',
            'template_data.line_items.*.quantity' => 'required|numeric|min:0',
            'template_data.line_items.*.unit_price' => 'required|numeric|min:0',
            'template_data.line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $errors = [];

        // Additional validation logic
        if (empty($templateData['name'])) {
            $errors['name'] = ['Template name is required'];
        }

        if (empty($templateData['currency'])) {
            $errors['currency'] = ['Currency is required'];
        }

        if (empty($templateData['template_data']['line_items'])) {
            $errors['line_items'] = ['Template must have at least one line item'];
        }

        if (! empty($errors)) {
            return response()->json([
                'valid' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Template structure is valid',
        ]);
    }

    /**
     * Calculate total amount for a template.
     */
    protected function calculateTemplateTotal(InvoiceTemplate $template): float
    {
        $templateData = $template->template_data ?? [];

        return $this->calculateInvoiceDataTotal([
            'line_items' => $templateData['line_items'] ?? [],
        ])['total'];
    }

    /**
     * Calculate totals from invoice data.
     */
    protected function calculateInvoiceDataTotal(array $invoiceData): array
    {
        $lineItems = $invoiceData['line_items'] ?? [];

        $subtotal = 0;
        $tax = 0;

        foreach ($lineItems as $item) {
            $quantity = $item['quantity'] ?? 0;
            $unitPrice = $item['unit_price'] ?? 0;
            $taxRate = $item['tax_rate'] ?? 0;
            $discount = $item['discount_amount'] ?? 0;

            $itemTotal = $quantity * $unitPrice;
            $itemTax = $itemTotal * ($taxRate / 100);

            $subtotal += $itemTotal - $discount;
            $tax += $itemTax;
        }

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ];
    }
}
