<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Customers\Models\CustomerContact;

class DeleteCustomerContactAction
{
    /**
     * Delete (soft delete) a customer contact.
     */
    public function execute(CustomerContact $contact): bool
    {
        return DB::transaction(function () use ($contact) {
            // Store values for audit before deletion
            $contactData = [
                'id' => $contact->id,
                'customer_id' => $contact->customer_id,
                'company_id' => $contact->company_id,
                'email' => $contact->email,
                'role' => $contact->role,
                'is_primary' => $contact->is_primary,
                'full_name' => $contact->full_name,
            ];

            // Soft delete the contact
            $result = $contact->delete();

            if ($result) {
                // Emit audit event
                $this->emitAuditEvent('customer_contact_deleted', $contactData);
            }

            return $result;
        });
    }

    /**
     * Permanently delete a customer contact.
     * This requires special permissions.
     */
    public function executePermanent(CustomerContact $contact): bool
    {
        $this->validatePermanentDeletionPermissions();

        return DB::transaction(function () use ($contact) {
            // Store values for audit before permanent deletion
            $contactData = [
                'id' => $contact->id,
                'customer_id' => $contact->customer_id,
                'company_id' => $contact->company_id,
                'email' => $contact->email,
                'role' => $contact->role,
                'full_name' => $contact->full_name,
            ];

            // Force delete the contact
            $result = $contact->forceDelete();

            if ($result) {
                // Emit audit event
                $this->emitAuditEvent('customer_contact_permanently_deleted', $contactData);
            }

            return $result;
        });
    }

    /**
     * Restore a soft-deleted contact.
     */
    public function restore(CustomerContact $contact): CustomerContact
    {
        return DB::transaction(function () use ($contact) {
            // Restore the contact
            $contact->restore();

            // Emit audit event
            $this->emitAuditEvent('customer_contact_restored', [
                'id' => $contact->id,
                'customer_id' => $contact->customer_id,
                'company_id' => $contact->company_id,
                'email' => $contact->email,
                'full_name' => $contact->full_name,
            ]);

            return $contact->fresh();
        });
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

        // Check for specific permission or admin role
        if (! $user->hasPermission('accounting.customers.permanent_delete') &&
            ! $user->hasRole('admin')) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You do not have permission to permanently delete contacts.'
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
