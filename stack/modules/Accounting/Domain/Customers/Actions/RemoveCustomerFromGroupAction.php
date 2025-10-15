<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Models\CustomerGroup;
use Modules\Accounting\Domain\Customers\Models\CustomerGroupMember;

class RemoveCustomerFromGroupAction
{
    /**
     * Remove a customer from a group.
     */
    public function execute(Customer $customer, CustomerGroup $group): bool
    {
        return DB::transaction(function () use ($customer, $group) {
            // Validate removal
            $this->validateRemoval($customer, $group);

            // Find the membership record
            $membership = CustomerGroupMember::where('customer_id', $customer->id)
                ->where('group_id', $group->id)
                ->first();

            if (! $membership) {
                throw ValidationException::withMessages([
                    'customer' => 'Customer is not a member of this group.',
                ]);
            }

            // Store data for audit
            $membershipData = [
                'membership_id' => $membership->id,
                'customer_id' => $customer->id,
                'group_id' => $group->id,
                'company_id' => $customer->company_id,
                'group_name' => $group->name,
                'customer_name' => $customer->name,
                'joined_at' => $membership->joined_at,
                'membership_duration' => $membership->formatted_membership_duration,
            ];

            // Delete the membership
            $result = $membership->delete();

            if ($result) {
                // Emit audit event
                $this->emitAuditEvent('customer_removed_from_group', $membershipData);
            }

            return $result;
        });
    }

    /**
     * Validate the removal.
     */
    private function validateRemoval(Customer $customer, CustomerGroup $group): void
    {
        // Check if customer belongs to the same company as the group
        if ($customer->company_id !== $group->company_id) {
            throw ValidationException::withMessages([
                'customer' => 'Customer and group must belong to the same company.',
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
