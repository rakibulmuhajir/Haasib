<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Models\CustomerGroup;
use Modules\Accounting\Domain\Customers\Models\CustomerGroupMember;

class AssignCustomerToGroupAction
{
    /**
     * Assign a customer to a group.
     */
    public function execute(Customer $customer, CustomerGroup $group): CustomerGroupMember
    {
        return DB::transaction(function () use ($customer, $group) {
            // Validate assignment
            $this->validateAssignment($customer, $group);

            // Create the membership
            $membership = CustomerGroupMember::create([
                'customer_id' => $customer->id,
                'group_id' => $group->id,
                'company_id' => $customer->company_id,
                'added_by_user_id' => auth()->id(),
            ]);

            // Emit audit event
            $this->emitAuditEvent('customer_assigned_to_group', [
                'membership_id' => $membership->id,
                'customer_id' => $customer->id,
                'group_id' => $group->id,
                'company_id' => $customer->company_id,
                'group_name' => $group->name,
                'customer_name' => $customer->name,
            ]);

            return $membership;
        });
    }

    /**
     * Validate the assignment.
     */
    private function validateAssignment(Customer $customer, CustomerGroup $group): void
    {
        // Check if customer belongs to the same company as the group
        if ($customer->company_id !== $group->company_id) {
            throw ValidationException::withMessages([
                'customer' => 'Customer and group must belong to the same company.',
            ]);
        }

        // Check if customer is already in the group
        if ($group->hasCustomer($customer)) {
            throw ValidationException::withMessages([
                'customer' => 'Customer is already a member of this group.',
            ]);
        }
    }

    /**
     * Emit audit event for the action.
     */
    private function emitAuditEvent(string $event, array $data): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, array_merge($data, [
                'performed_by' => auth()->id(),
                'performed_at' => now()->toISOString(),
            ]));
        }
    }
}
