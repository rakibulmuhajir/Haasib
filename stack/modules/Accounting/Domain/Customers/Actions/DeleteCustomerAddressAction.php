<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Customers\Models\CustomerAddress;

class DeleteCustomerAddressAction
{
    /**
     * Delete (soft delete) a customer address.
     */
    public function execute(CustomerAddress $address): bool
    {
        return DB::transaction(function () use ($address) {
            // Store values for audit before deletion
            $addressData = [
                'id' => $address->id,
                'customer_id' => $address->customer_id,
                'company_id' => $address->company_id,
                'type' => $address->type,
                'country' => $address->country,
                'label' => $address->label,
                'full_address' => $address->full_address,
                'was_default' => $address->is_default,
            ];

            // Soft delete the address
            $result = $address->delete();

            if ($result) {
                // If this was a default address, set another as default if available
                if ($addressData['was_default']) {
                    $this->setNewDefaultAddress($address->customer_id, $address->type);
                }

                // Emit audit event
                $this->emitAuditEvent('customer_address_deleted', $addressData);
            }

            return $result;
        });
    }

    /**
     * Permanently delete a customer address.
     * This requires special permissions.
     */
    public function executePermanent(CustomerAddress $address): bool
    {
        $this->validatePermanentDeletionPermissions();

        return DB::transaction(function () use ($address) {
            // Store values for audit before permanent deletion
            $addressData = [
                'id' => $address->id,
                'customer_id' => $address->customer_id,
                'company_id' => $address->company_id,
                'type' => $address->type,
                'country' => $address->country,
                'label' => $address->label,
                'full_address' => $address->full_address,
            ];

            // Force delete the address
            $result = $address->forceDelete();

            if ($result) {
                // Emit audit event
                $this->emitAuditEvent('customer_address_permanently_deleted', $addressData);
            }

            return $result;
        });
    }

    /**
     * Restore a soft-deleted address.
     */
    public function restore(CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($address) {
            // Restore the address
            $address->restore();

            // Emit audit event
            $this->emitAuditEvent('customer_address_restored', [
                'id' => $address->id,
                'customer_id' => $address->customer_id,
                'company_id' => $address->company_id,
                'type' => $address->type,
                'label' => $address->label,
                'full_address' => $address->full_address,
            ]);

            return $address->fresh();
        });
    }

    /**
     * Set a new default address when the current default is deleted.
     */
    private function setNewDefaultAddress(int $customerId, string $type): void
    {
        $newDefault = CustomerAddress::where('customer_id', $customerId)
            ->where('type', $type)
            ->where('is_default', false)
            ->first();

        if ($newDefault) {
            $newDefault->update(['is_default' => true]);

            // Emit audit event for default change
            $this->emitAuditEvent('customer_address_default_changed', [
                'address_id' => $newDefault->id,
                'customer_id' => $customerId,
                'type' => $type,
                'new_default_address' => $newDefault->full_address,
                'reason' => 'Previous default address was deleted',
            ]);
        }
    }

    /**
     * Validate that the user has permission for permanent deletion.
     */
    private function validatePermanentDeletionPermissions(): void
    {
        $user = auth()->user();

        if (! $user) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User not authenticated.');
        }

        if (! $user->hasPermission('accounting.customers.permanent_delete') &&
            ! $user->hasRole('admin')) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You do not have permission to permanently delete addresses.'
            );
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
