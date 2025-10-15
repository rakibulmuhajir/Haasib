<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Exceptions\CustomerStatusChangeException;

class ChangeCustomerStatusAction
{
    /**
     * Allowed status transitions
     */
    private const ALLOWED_TRANSITIONS = [
        'active' => ['inactive', 'blocked'],
        'inactive' => ['active'],
        'blocked' => ['active'],
    ];

    /**
     * Change customer status with validation.
     */
    public function execute(Company $company, string $customerId, string $newStatus, array $reason, User $changedBy): Customer
    {
        // Find customer
        $customer = $this->findCustomer($company, $customerId);

        // Validate status transition
        $this->validateStatusTransition($customer, $newStatus, $reason);

        try {
            DB::beginTransaction();

            $oldStatus = $customer->status;

            // Update customer status
            $customer->update(['status' => $newStatus]);

            // Emit audit event
            Event::dispatch('customer.status.changed', [
                'customer_id' => $customer->id,
                'company_id' => $company->id,
                'user_id' => $changedBy->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason['reason'] ?? null,
                'approval_reference' => $reason['approval_reference'] ?? null,
                'changed_at' => now()->toISOString(),
            ]);

            DB::commit();

            return $customer->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomerStatusChangeException('Failed to change customer status: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Find customer or throw exception.
     */
    private function findCustomer(Company $company, string $customerId): Customer
    {
        $customer = Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw new CustomerStatusChangeException('Customer not found');
        }

        return $customer;
    }

    /**
     * Validate status transition.
     */
    private function validateStatusTransition(Customer $customer, string $newStatus, array $reason): void
    {
        // Validate new status value
        $validator = Validator::make(['status' => $newStatus], [
            'status' => 'required|in:active,inactive,blocked',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check if status is actually changing
        if ($customer->status === $newStatus) {
            throw ValidationException::withMessages([
                'status' => 'Customer already has this status',
            ]);
        }

        // Validate transition is allowed
        $currentStatus = $customer->status;
        if (! isset(self::ALLOWED_TRANSITIONS[$currentStatus]) ||
            ! in_array($newStatus, self::ALLOWED_TRANSITIONS[$currentStatus])) {
            throw ValidationException::withMessages([
                'status' => "Cannot change status from {$currentStatus} to {$newStatus}. Allowed transitions from {$currentStatus}: ".
                          implode(', ', self::ALLOWED_TRANSITIONS[$currentStatus] ?? ['none']),
            ]);
        }

        // Validate additional requirements based on transition
        $this->validateTransitionRequirements($customer, $newStatus, $reason);
    }

    /**
     * Validate specific transition requirements.
     */
    private function validateTransitionRequirements(Customer $customer, string $newStatus, array $reason): void
    {
        switch ($newStatus) {
            case 'blocked':
                // Blocking requires a reason
                if (empty($reason['reason'])) {
                    throw ValidationException::withMessages([
                        'reason' => 'A reason is required when blocking a customer',
                    ]);
                }

                // Check if customer has significant outstanding balance
                $outstandingBalance = $customer->current_balance;
                if ($outstandingBalance > 1000) { // Configurable threshold
                    // Require approval reference for high-value customers
                    if (empty($reason['approval_reference'])) {
                        throw ValidationException::withMessages([
                            'approval_reference' => 'Approval reference is required when blocking a customer with outstanding balance over $1,000',
                        ]);
                    }
                }
                break;

            case 'active':
                // Activating from blocked requires approval
                if ($customer->status === 'blocked') {
                    if (empty($reason['approval_reference'])) {
                        throw ValidationException::withMessages([
                            'approval_reference' => 'Approval reference is required when activating a blocked customer',
                        ]);
                    }
                }
                break;
        }
    }

    /**
     * Get allowed transitions for a status.
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Check if a transition is allowed.
     */
    public function isTransitionAllowed(string $fromStatus, string $toStatus): bool
    {
        return isset(self::ALLOWED_TRANSITIONS[$fromStatus]) &&
               in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus]);
    }

    /**
     * Bulk status change for multiple customers.
     */
    public function bulkChangeStatus(Company $company, array $customerIds, string $newStatus, array $reason, User $changedBy): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($customerIds as $customerId) {
            try {
                $customer = $this->execute($company, $customerId, $newStatus, $reason, $changedBy);
                $results['success'][] = [
                    'customer_id' => $customerId,
                    'customer_number' => $customer->customer_number,
                    'name' => $customer->name,
                    'old_status' => $customer->fresh()->getOriginal('status'),
                    'new_status' => $newStatus,
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Emit bulk operation audit event
        Event::dispatch('customer.bulk_status_changed', [
            'company_id' => $company->id,
            'user_id' => $changedBy->id,
            'new_status' => $newStatus,
            'reason' => $reason['reason'] ?? null,
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
            'results' => $results,
            'changed_at' => now()->toISOString(),
        ]);

        return $results;
    }
}
