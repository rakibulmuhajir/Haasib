<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Customers\Models\CustomerCommunication;

class DeleteCustomerCommunicationAction
{
    /**
     * Delete a customer communication log.
     */
    public function execute(CustomerCommunication $communication): bool
    {
        return DB::transaction(function () use ($communication) {
            // Validate deletion permissions
            $this->validateDeletionPermissions($communication);

            // Store values for audit before deletion
            $communicationData = [
                'id' => $communication->id,
                'customer_id' => $communication->customer_id,
                'company_id' => $communication->company_id,
                'channel' => $communication->channel,
                'direction' => $communication->direction,
                'subject' => $communication->subject,
                'occurred_at' => $communication->occurred_at->toISOString(),
                'logged_by_user_id' => $communication->logged_by_user_id,
                'had_attachments' => $communication->has_attachments,
            ];

            // Delete the communication
            $result = $communication->delete();

            if ($result) {
                // Emit audit event
                $this->emitAuditEvent('customer_communication_deleted', $communicationData);
            }

            return $result;
        });
    }

    /**
     * Validate that the user can delete this communication.
     */
    private function validateDeletionPermissions(CustomerCommunication $communication): void
    {
        $user = auth()->user();

        if (! $user) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User not authenticated.');
        }

        // Users can delete their own communications or need special permissions
        $canDelete = $communication->logged_by_user_id === $user->id ||
                    $user->hasPermission('accounting.customers.delete_communications') ||
                    $user->hasRole('admin');

        if (! $canDelete) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You do not have permission to delete this communication log.'
            );
        }

        // Additional rule: Cannot delete communications older than 1 year without admin role
        $yearAgo = now()->subYear();
        if ($communication->occurred_at < $yearAgo && ! $user->hasRole('admin')) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Communications older than 1 year can only be deleted by administrators.'
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
