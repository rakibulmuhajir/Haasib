<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.invoice_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'customer_id',
        'currency',
        'template_data',
        'settings',
        'is_active',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_data' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'company_id' => 'string',
            'customer_id' => 'string',
            'created_by_user_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the template.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer associated with the template.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include templates for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include templates for a specific customer.
     */
    public function scopeForCustomer($query, ?string $customerId)
    {
        if ($customerId) {
            return $query->where('customer_id', $customerId);
        }

        return $query->whereNull('customer_id');
    }

    /**
     * Apply template to create a new invoice.
     */
    public function applyToInvoice(?Customer $customer = null, array $overrides = []): array
    {
        $templateData = $this->template_data;

        // Build invoice data from template
        $invoiceData = [
            'company_id' => $this->company_id,
            'customer_id' => $customer?->id ?? $this->customer_id,
            'currency' => $overrides['currency'] ?? $this->currency,
            'notes' => $overrides['notes'] ?? $templateData['notes'] ?? null,
            'terms' => $overrides['terms'] ?? $templateData['terms'] ?? null,
            'issue_date' => $overrides['issue_date'] ?? now()->format('Y-m-d'),
            'due_date' => $overrides['due_date'] ?? now()->addDays($templateData['payment_terms'] ?? 30)->format('Y-m-d'),
            'line_items' => $this->processLineItems($templateData['line_items'] ?? [], $overrides),
        ];

        // Apply any field-level overrides
        return array_merge($invoiceData, array_intersect_key($overrides, $invoiceData));
    }

    /**
     * Process line items for template application.
     */
    protected function processLineItems(array $templateItems, array $overrides): array
    {
        $processedItems = [];

        foreach ($templateItems as $item) {
            $processedItem = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'discount_amount' => $item['discount_amount'] ?? 0,
            ];

            // Apply item-level overrides if they exist
            if (isset($overrides['line_items_overrides'][$item['id']])) {
                $itemOverrides = $overrides['line_items_overrides'][$item['id']];
                $processedItem = array_merge($processedItem, $itemOverrides);
            }

            $processedItems[] = $processedItem;
        }

        // Add any additional line items from overrides
        if (isset($overrides['additional_line_items'])) {
            $processedItems = array_merge($processedItems, $overrides['additional_line_items']);
        }

        return $processedItems;
    }

    /**
     * Validate template structure and data.
     */
    public function validateTemplate(): array
    {
        $errors = [];

        // Check required fields
        if (empty($this->name)) {
            $errors[] = 'Template name is required';
        }

        if (empty($this->company_id)) {
            $errors[] = 'Company is required';
        }

        if (empty($this->currency)) {
            $errors[] = 'Currency is required';
        }

        // Validate template data structure
        $templateData = $this->template_data ?? [];

        if (empty($templateData['line_items'])) {
            $errors[] = 'Template must have at least one line item';
        } else {
            foreach ($templateData['line_items'] as $index => $item) {
                if (empty($item['description'])) {
                    $errors[] = 'Line item '.($index + 1).' description is required';
                }

                if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                    $errors[] = 'Line item '.($index + 1).' quantity must be greater than 0';
                }

                if (! isset($item['unit_price']) || $item['unit_price'] < 0) {
                    $errors[] = 'Line item '.($index + 1).' unit price cannot be negative';
                }
            }
        }

        return $errors;
    }

    /**
     * Get template summary information.
     */
    public function getSummary(): array
    {
        $templateData = $this->template_data ?? [];
        $lineItems = $templateData['line_items'] ?? [];

        $subtotal = 0;
        $taxAmount = 0;

        foreach ($lineItems as $item) {
            $itemTotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
            $itemTax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
            $subtotal += $itemTotal;
            $taxAmount += $itemTax;
        }

        return [
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'customer_name' => $this->customer?->name,
            'line_items_count' => count($lineItems),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Duplicate template with optional modifications.
     */
    public function duplicate(string $newName, array $modifications = []): self
    {
        $duplicate = $this->replicate();
        $duplicate->name = $newName;
        $duplicate->created_by_user_id = auth()->id();

        // Apply modifications
        if (! empty($modifications)) {
            $templateData = $duplicate->template_data ?? [];

            if (isset($modifications['description'])) {
                $duplicate->description = $modifications['description'];
            }

            if (isset($modifications['customer_id'])) {
                $duplicate->customer_id = $modifications['customer_id'];
            }

            if (isset($modifications['currency'])) {
                $duplicate->currency = $modifications['currency'];
            }

            if (isset($modifications['notes'])) {
                $templateData['notes'] = $modifications['notes'];
            }

            if (isset($modifications['terms'])) {
                $templateData['terms'] = $modifications['terms'];
            }

            if (isset($modifications['payment_terms'])) {
                $templateData['payment_terms'] = $modifications['payment_terms'];
            }

            if (isset($modifications['line_items'])) {
                $templateData['line_items'] = $modifications['line_items'];
            }

            $duplicate->template_data = $templateData;
        }

        $duplicate->save();

        return $duplicate;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\Invoicing\InvoiceTemplateFactory::new();
    }
}
