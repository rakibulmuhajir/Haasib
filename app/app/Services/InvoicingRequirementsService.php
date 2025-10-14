<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class InvoicingRequirementsService
{
    /**
     * Get requirements for a specific invoicing action
     *
     * @param  string  $actionType  The type of action (status-change, payment-allocation, etc.)
     * @param  array  $context  Context data including entity type, current values, etc.
     * @param  User  $user  The user performing the action
     */
    public function getRequirements(string $actionType, array $context, User $user): array
    {
        $cacheKey = $this->getCacheKey($actionType, $context, $user);

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($actionType, $context, $user) {
            return $this->generateRequirements($actionType, $context, $user);
        });
    }

    /**
     * Generate requirements based on business logic
     */
    protected function generateRequirements(string $actionType, array $context, User $user): array
    {
        switch ($actionType) {
            case 'status-change':
                return $this->getStatusChangeRequirements($context, $user);

            case 'payment-allocation':
                return $this->getPaymentAllocationRequirements($context, $user);

            case 'invoice-creation':
                return $this->getInvoiceCreationRequirements($context, $user);

            case 'update_exchange_rate':
                return $this->getExchangeRateRequirements($context, $user);

            default:
                return [
                    'requiresAdditionalInfo' => false,
                    'fields' => [],
                ];
        }
    }

    /**
     * Get requirements for status changes
     */
    protected function getStatusChangeRequirements(array $context, User $user): array
    {
        $newStatus = $context['new_status'] ?? null;
        $entityType = $context['entity_type'] ?? 'invoice';
        $currentStatus = $context['current_status'] ?? null;

        if (! $newStatus) {
            return [
                'requiresAdditionalInfo' => false,
                'fields' => [],
            ];
        }

        $requirements = [
            'requiresAdditionalInfo' => false,
            'fields' => [],
            'validation' => [],
        ];

        // Cancellation requirements
        if ($newStatus === 'cancelled') {
            $requirements['requiresAdditionalInfo'] = true;
            $requirements['fields'][] = [
                'name' => 'cancellation_reason',
                'label' => 'Reason for Cancellation',
                'type' => 'textarea',
                'required' => true,
                'placeholder' => 'Please explain why this invoice is being cancelled...',
                'validation' => ['required', 'min:5'],
                'helpText' => 'This reason will be stored for audit purposes',
            ];

            // Some companies might require approval for cancellations
            if ($this->requiresCancellationApproval($user)) {
                $requirements['fields'][] = [
                    'name' => 'approved_by',
                    'label' => 'Approved By',
                    'type' => 'user_select',
                    'required' => true,
                    'helpText' => 'Select manager who approved this cancellation',
                ];
            }
        }

        // Void requirements (different from cancellation)
        if ($newStatus === 'void') {
            $requirements['requiresAdditionalInfo'] = true;
            $requirements['fields'][] = [
                'name' => 'void_reason',
                'label' => 'Reason for Void',
                'type' => 'textarea',
                'required' => true,
                'placeholder' => 'Please explain why this invoice is being voided...',
                'validation' => ['required', 'min:5'],
            ];

            // If invoice was posted, require reference to reversing journal entry
            if ($currentStatus === 'posted' || $currentStatus === 'paid') {
                $requirements['fields'][] = [
                    'name' => 'reversal_reference',
                    'label' => 'Reversal Reference',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Journal entry reference for reversal...',
                    'helpText' => 'Reference to the reversing journal entry',
                ];
            }
        }

        // Special requirements based on user permissions or company settings
        if ($this->requiresAdditionalDocumentation($user, $newStatus)) {
            $requirements['fields'][] = [
                'name' => 'documentation',
                'label' => 'Supporting Documentation',
                'type' => 'file',
                'required' => false,
                'accept' => '.pdf,.jpg,.png',
                'helpText' => 'Upload any supporting documents',
            ];
        }

        return $requirements;
    }

    /**
     * Get requirements for payment allocations
     */
    protected function getPaymentAllocationRequirements(array $context, User $user): array
    {
        $requirements = [
            'requiresAdditionalInfo' => false,
            'fields' => [],
        ];

        // If allocating to multiple invoices
        if (($context['allocation_count'] ?? 0) > 1) {
            $requirements['requiresAdditionalInfo'] = true;
            $requirements['fields'][] = [
                'name' => 'allocation_method',
                'label' => 'Allocation Method',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'pro_rata', 'label' => 'Pro Rata (by amount)'],
                    ['value' => 'fifo', 'label' => 'FIFO (oldest first)'],
                    ['value' => 'manual', 'label' => 'Manual Allocation'],
                ],
            ];
        }

        // If payment amount exceeds invoice total
        if (($context['overpayment_amount'] ?? 0) > 0) {
            $requirements['fields'][] = [
                'name' => 'overpayment_handling',
                'label' => 'Overpayment Handling',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'refund', 'label' => 'Refund to customer'],
                    ['value' => 'credit', 'label' => 'Apply as credit'],
                    ['value' => 'retain', 'label' => 'Retain for future invoices'],
                ],
            ];
        }

        return $requirements;
    }

    /**
     * Get requirements for invoice creation
     */
    protected function getInvoiceCreationRequirements(array $context, User $user): array
    {
        $requirements = [
            'requiresAdditionalInfo' => false,
            'fields' => [],
        ];

        // High-value invoices might require additional approval
        if (($context['invoice_amount'] ?? 0) > 10000) {
            $requirements['requiresAdditionalInfo'] = true;
            $requirements['fields'][] = [
                'name' => 'manager_approval',
                'label' => 'Manager Approval',
                'type' => 'user_select',
                'required' => true,
                'helpText' => 'Select manager for approval',
            ];
        }

        // International invoices might require additional info
        if ($context['is_international'] ?? false) {
            $requirements['fields'][] = [
                'name' => 'currency_exchange_rate',
                'label' => 'Exchange Rate Reference',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Reference for exchange rate used...',
            ];
        }

        return $requirements;
    }

    /**
     * Get requirements for exchange rate updates
     */
    protected function getExchangeRateRequirements(array $context, User $user): array
    {
        $requirements = [
            'requiresAdditionalInfo' => true,
            'fields' => [
                [
                    'name' => 'exchange_rate',
                    'label' => 'Exchange Rate',
                    'type' => 'number',
                    'required' => true,
                    'placeholder' => '1.000000',
                    'step' => '0.000001',
                    'min' => '0',
                    'helpText' => 'Rate relative to your base currency',
                ],
                [
                    'name' => 'effective_date',
                    'label' => 'Effective Date',
                    'type' => 'date',
                    'required' => true,
                    'default' => now()->format('Y-m-d'),
                    'helpText' => 'When this rate becomes effective',
                ],
                [
                    'name' => 'cease_date',
                    'label' => 'Cease Date (Optional)',
                    'type' => 'date',
                    'required' => false,
                    'helpText' => 'When this rate expires (optional)',
                ],
                [
                    'name' => 'notes',
                    'label' => 'Notes (Optional)',
                    'type' => 'textarea',
                    'required' => false,
                    'maxLength' => 255,
                    'placeholder' => 'Add any notes about this exchange rate...',
                ],
            ],
            'validation' => [
                'exchange_rate' => 'required|numeric|gt:0',
                'effective_date' => 'required|date',
                'cease_date' => 'nullable|date|after:effective_date',
                'notes' => 'nullable|string|max:255',
            ],
        ];

        return $requirements;
    }

    /**
     * Check if user requires approval for cancellations
     */
    protected function requiresCancellationApproval(User $user): bool
    {
        // Check user permissions or company settings
        return ! $user->hasPermissionTo('cancel_invoices_without_approval');
    }

    /**
     * Check if additional documentation is required
     */
    protected function requiresAdditionalDocumentation(User $user, string $status): bool
    {
        // Implement logic based on company settings or regulations
        return in_array($status, ['void', 'cancelled']) &&
               $user->currentCompany?->settings?->require_documentation ?? false;
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $actionType, array $context, User $user): string
    {
        $companyId = $user->current_company_id;
        $contextHash = md5(json_encode($context));

        return "invoicing_requirements:{$actionType}:{$companyId}:{$contextHash}";
    }

    /**
     * Clear cache for specific requirements
     */
    public function clearCache(string $actionType, ?string $companyId = null): void
    {
        $pattern = $companyId
            ? "invoicing_requirements:{$actionType}:{$companyId}:*"
            : "invoicing_requirements:{$actionType}:*";

        Cache::forget($pattern);
    }
}
