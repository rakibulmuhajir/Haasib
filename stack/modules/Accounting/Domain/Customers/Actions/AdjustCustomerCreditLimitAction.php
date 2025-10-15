<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Domain\Customers\Events\CreditLimitAdjusted;
use Modules\Accounting\Domain\Customers\Events\CreditLimitAdjustmentRequested;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerCreditLimit;
use Modules\Accounting\Domain\Customers\Services\CustomerCreditService;
use Modules\Accounting\Domain\Customers\Telemetry\CreditMetrics;

class AdjustCustomerCreditLimitAction
{
    public function __construct(
        private CustomerCreditService $creditService
    ) {}

    /**
     * Adjust a customer's credit limit
     */
    public function execute(
        Customer $customer,
        float $newLimit,
        \DateTime $effectiveAt,
        array $options = []
    ): CustomerCreditLimit {
        // Validate input
        $this->validateInput($newLimit, $effectiveAt, $options);

        // Check for conflicts with existing limits
        $this->checkForConflicts($customer, $newLimit, $effectiveAt, $options);

        return DB::transaction(function () use ($customer, $newLimit, $effectiveAt, $options) {
            // Create the new credit limit record
            $creditLimit = CustomerCreditLimit::create([
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'limit_amount' => $newLimit,
                'effective_at' => $effectiveAt,
                'expires_at' => $options['expires_at'] ?? null,
                'status' => $options['status'] ?? 'approved',
                'reason' => $options['reason'] ?? null,
                'changed_by_user_id' => $options['changed_by_user_id'] ?? auth()->id(),
                'approval_reference' => $options['approval_reference'] ?? null,
            ]);

            // Update customer record if this is an approved limit
            if ($creditLimit->status === 'approved' && $this->shouldUpdateCustomerRecord($creditLimit)) {
                $customer->update([
                    'credit_limit' => $newLimit,
                    'credit_limit_effective_at' => $effectiveAt,
                ]);
            }

            // Fire appropriate events
            $this->fireEvents($creditLimit, $customer, $options);

            return $creditLimit;
        });
    }

    /**
     * Validate the input parameters
     */
    private function validateInput(float $newLimit, \DateTime $effectiveAt, array $options): void
    {
        if ($newLimit < 0) {
            throw new \InvalidArgumentException('Credit limit amount cannot be negative');
        }

        $isApproved = ($options['status'] ?? 'approved') === 'approved';
        if ($isApproved && $effectiveAt < now() && ! $options['allow_backdating'] ?? false) {
            throw new \InvalidArgumentException('Effective date for approved credit limits cannot be in the past');
        }

        if (! empty($options['expires_at']) && $options['expires_at'] <= $effectiveAt) {
            throw new \InvalidArgumentException('Expiry date must be after the effective date');
        }

        if (empty($options['changed_by_user_id'])) {
            throw new \InvalidArgumentException('User ID is required for credit limit adjustments');
        }
    }

    /**
     * Check for conflicts with existing credit limits
     */
    private function checkForConflicts(Customer $customer, float $newLimit, \DateTime $effectiveAt, array $options): void
    {
        $status = $options['status'] ?? 'approved';

        if ($status !== 'approved') {
            return; // Pending limits don't conflict with existing ones
        }

        // Check for overlapping approved limits
        $conflictingLimits = CustomerCreditLimit::where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->where('id', '!=', $options['exclude_limit_id'] ?? null)
            ->where(function ($query) use ($effectiveAt) {
                $query->where('effective_at', '<=', $effectiveAt)
                    ->where(function ($q) use ($effectiveAt) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $effectiveAt);
                    });
            })
            ->get();

        if ($conflictingLimits->isNotEmpty()) {
            // Auto-expire conflicting limits if requested
            if ($options['auto_expire_conflicts'] ?? false) {
                foreach ($conflictingLimits as $conflictingLimit) {
                    $conflictingLimit->update([
                        'expires_at' => $effectiveAt->copy()->subSecond(),
                        'reason' => 'Auto-expired due to new credit limit: '.($options['reason'] ?? 'Credit limit adjustment'),
                    ]);
                }
            } else {
                throw new \InvalidArgumentException(
                    'Credit limit conflicts with existing active limit. Use auto_expire_conflicts option or specify a different effective date.'
                );
            }
        }
    }

    /**
     * Determine if customer record should be updated
     */
    private function shouldUpdateCustomerRecord(CustomerCreditLimit $creditLimit): bool
    {
        // Don't update if it's a future limit
        if ($creditLimit->effective_at->gt(now())) {
            return false;
        }

        // Don't update if it's expired
        if ($creditLimit->expires_at && $creditLimit->expires_at->lt(now())) {
            return false;
        }

        // Don't update if it's not approved
        if ($creditLimit->status !== 'approved') {
            return false;
        }

        // Check if this is the most current approved limit
        $currentActive = CustomerCreditLimit::getActiveForCustomer($creditLimit->customer_id);

        return ! $currentActive || $creditLimit->effective_at->gte($currentActive->effective_at);
    }

    /**
     * Fire appropriate events
     */
    private function fireEvents(CustomerCreditLimit $creditLimit, Customer $customer, array $options): void
    {
        // Always fire the adjustment event
        Event::dispatch(new CreditLimitAdjusted($creditLimit, $customer, $options));

        // Track telemetry
        CreditMetrics::trackCreditLimitAdjusted(new CreditLimitAdjusted($creditLimit, $customer, $options));

        // Fire approval request event if pending
        if ($creditLimit->status === 'pending') {
            Event::dispatch(new CreditLimitAdjustmentRequested($creditLimit, $customer, $options));
        }
    }

    /**
     * Create a pending credit limit adjustment request
     */
    public function createRequest(
        Customer $customer,
        float $requestedLimit,
        \DateTime $requestedEffectiveDate,
        array $options = []
    ): CustomerCreditLimit {
        return $this->execute(
            $customer,
            $requestedLimit,
            $requestedEffectiveDate,
            array_merge($options, ['status' => 'pending'])
        );
    }

    /**
     * Approve a pending credit limit adjustment
     */
    public function approveRequest(
        CustomerCreditLimit $creditLimit,
        ?string $approvalReference = null,
        ?string $approvalReason = null
    ): CustomerCreditLimit {
        if ($creditLimit->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending credit limits can be approved');
        }

        return DB::transaction(function () use ($creditLimit, $approvalReference, $approvalReason) {
            $creditLimit->update([
                'status' => 'approved',
                'approval_reference' => $approvalReference,
                'reason' => $approvalReason ?? $creditLimit->reason,
            ]);

            // Update customer record if applicable
            $customer = $creditLimit->customer;
            if ($this->shouldUpdateCustomerRecord($creditLimit)) {
                $customer->update([
                    'credit_limit' => $creditLimit->limit_amount,
                    'credit_limit_effective_at' => $creditLimit->effective_at,
                ]);
            }

            Event::dispatch(new CreditLimitAdjusted($creditLimit, $customer, [
                'action' => 'approved',
                'approval_reference' => $approvalReference,
            ]));

            return $creditLimit;
        });
    }

    /**
     * Reject a pending credit limit adjustment
     */
    public function rejectRequest(
        CustomerCreditLimit $creditLimit,
        string $rejectionReason
    ): CustomerCreditLimit {
        if ($creditLimit->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending credit limits can be rejected');
        }

        $creditLimit->update([
            'status' => 'revoked',
            'reason' => $rejectionReason,
        ]);

        Event::dispatch(new CreditLimitAdjusted($creditLimit, $creditLimit->customer, [
            'action' => 'rejected',
            'rejection_reason' => $rejectionReason,
        ]));

        return $creditLimit;
    }
}
