<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    /**
     * Determine if the user can view any credit notes.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('credit_notes.view');
    }

    /**
     * Determine if the user can view the credit note.
     */
    public function view(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to view credit notes
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can create credit notes.
     */
    public function create(User $user, Company $company): bool
    {
        // User must have permission to create credit notes
        if (! $user->hasPermissionTo('credit_notes.create')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can create credit notes for a specific invoice.
     */
    public function createForInvoice(User $user, $invoice): bool
    {
        // User must have permission to create credit notes
        if (! $user->hasPermissionTo('credit_notes.create')) {
            return false;
        }

        // User must have access to the invoice's company
        return $this->userHasCompanyAccess($user, $invoice->company);
    }

    /**
     * Determine if the user can update the credit note.
     */
    public function update(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to update credit notes
        if (! $user->hasPermissionTo('credit_notes.update')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can delete the credit note.
     */
    public function delete(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to delete credit notes
        if (! $user->hasPermissionTo('credit_notes.delete')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can post the credit note to ledger.
     */
    public function post(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to post credit notes
        if (! $user->hasPermissionTo('credit_notes.post')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can cancel the credit note.
     */
    public function cancel(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to cancel credit notes
        if (! $user->hasPermissionTo('credit_notes.cancel')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can apply the credit note to invoice balance.
     */
    public function apply(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to apply credit notes
        if (! $user->hasPermissionTo('credit_notes.apply')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can unapply the credit note from invoice.
     */
    public function unapply(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to apply credit notes (unapplying is reverse operation)
        if (! $user->hasPermissionTo('credit_notes.apply')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can generate PDF for the credit note.
     */
    public function generatePdf(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to view credit notes or generate PDFs
        if (! $user->hasPermissionTo('credit_notes.view') &&
            ! $user->hasPermissionTo('credit_notes.pdf')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can email the credit note.
     */
    public function email(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to email credit notes
        if (! $user->hasPermissionTo('credit_notes.email')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can schedule credit note emails.
     */
    public function scheduleEmail(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to email credit notes
        if (! $user->hasPermissionTo('credit_notes.email')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can process scheduled credit note emails.
     */
    public function processScheduledEmails(User $user, Company $company): bool
    {
        // User must have permission to email credit notes
        if (! $user->hasPermissionTo('credit_notes.email')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can send reminder emails for credit notes.
     */
    public function sendReminders(User $user, Company $company): bool
    {
        // User must have permission to email credit notes
        if (! $user->hasPermissionTo('credit_notes.email')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can bulk operate on credit notes.
     */
    public function bulkOperate(User $user, Company $company): bool
    {
        // Bulk operations require appropriate permissions
        return $user->hasPermissionTo('credit_notes.bulk') ||
               $user->hasPermissionTo('credit_notes.update') ||
               $user->hasPermissionTo('credit_notes.delete') ||
               $user->hasPermissionTo('credit_notes.post') ||
               $user->hasPermissionTo('credit_notes.cancel');
    }

    /**
     * Determine if the user can view credit note statistics.
     */
    public function viewStatistics(User $user, Company $company): bool
    {
        // User must have permission to view credit notes
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can export credit notes.
     */
    public function export(User $user, Company $company): bool
    {
        // Exporting requires view permission
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can view credit note audit trail.
     */
    public function viewAuditTrail(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to view credit notes
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can sync credit note with ledger.
     */
    public function syncWithLedger(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to post credit notes (ledger sync is related)
        if (! $user->hasPermissionTo('credit_notes.post')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can view credit note applications history.
     */
    public function viewApplications(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to view credit notes
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can modify credit note items.
     */
    public function modifyItems(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to update credit notes
        if (! $user->hasPermissionTo('credit_notes.update')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can modify credit note reason.
     */
    public function modifyReason(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to update credit notes
        if (! $user->hasPermissionTo('credit_notes.update')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can view credit note financial impact.
     */
    public function viewFinancialImpact(User $user, CreditNote $creditNote): bool
    {
        // User must have permission to view credit notes
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        // User must have access to the credit note's company
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Determine if the user can access credit notes for a specific customer.
     */
    public function accessCustomerCreditNotes(User $user, Company $company): bool
    {
        // User must be able to view credit notes and access the company
        if (! $user->hasPermissionTo('credit_notes.view')) {
            return false;
        }

        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can process automatic credit note adjustments.
     */
    public function processAutomaticAdjustments(User $user, Company $company): bool
    {
        // This is typically an automated process but may require admin permission
        return $user->hasPermissionTo('credit_notes.apply') &&
               $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Helper method to check if user has access to a company.
     */
    protected function userHasCompanyAccess(User $user, Company $company): bool
    {
        // If user is super admin, allow all access
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Check if user is a member of the company
        if ($user->companies()->where('companies.id', $company->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Helper method to check if user has specific role-based access.
     */
    protected function userHasRequiredRole(User $user, array $allowedRoles): bool
    {
        foreach ($allowedRoles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if user can access credit notes based on their role hierarchy.
     */
    protected function hasRoleBasedAccess(User $user, string $permission): bool
    {
        // Owner can do everything
        if ($user->hasRole('owner')) {
            return true;
        }

        // Admin can do most credit note operations
        if ($user->hasRole('admin') && in_array($permission, [
            'credit_notes.view', 'credit_notes.create', 'credit_notes.update',
            'credit_notes.delete', 'credit_notes.post', 'credit_notes.cancel',
            'credit_notes.apply', 'credit_notes.email', 'credit_notes.pdf',
            'credit_notes.export', 'credit_notes.bulk',
        ])) {
            return true;
        }

        // Accountant can create, update, post, and apply credit notes
        if ($user->hasRole('accountant') && in_array($permission, [
            'credit_notes.view', 'credit_notes.create', 'credit_notes.update',
            'credit_notes.post', 'credit_notes.apply', 'credit_notes.email',
            'credit_notes.pdf',
        ])) {
            return true;
        }

        // Viewer can only view credit notes
        if ($user->hasRole('viewer') && $permission === 'credit_notes.view') {
            return true;
        }

        return false;
    }

    /**
     * Additional business rule validations
     */

    /**
     * Check if credit note can be modified based on its status.
     */
    public function canModifyBasedOnStatus(User $user, CreditNote $creditNote): bool
    {
        // Draft credit notes can be modified
        if ($creditNote->status === 'draft') {
            return $this->update($user, $creditNote);
        }

        // Posted credit notes have restrictions
        if ($creditNote->status === 'posted') {
            // Only certain operations allowed on posted credit notes
            return in_array($creditNote->status, ['cancelled']) ? false : true;
        }

        // Cancelled credit notes cannot be modified
        if ($creditNote->status === 'cancelled') {
            return false;
        }

        return $this->update($user, $creditNote);
    }

    /**
     * Check if user can perform financial operations on credit note.
     */
    public function canPerformFinancialOperations(User $user, CreditNote $creditNote): bool
    {
        // Financial operations require higher permissions
        if (! $user->hasAnyPermission(['credit_notes.post', 'credit_notes.apply', 'credit_notes.cancel'])) {
            return false;
        }

        // Must have company access
        return $this->userHasCompanyAccess($user, $creditNote->company);
    }

    /**
     * Check if user can access credit note during specific business hours (for sensitive operations).
     */
    public function canPerformSensitiveOperations(User $user, CreditNote $creditNote): bool
    {
        // Only allow sensitive operations during business hours for non-admin users
        if (! $user->hasRole(['admin', 'owner', 'super_admin'])) {
            $hour = now()->hour;
            if ($hour < 8 || $hour > 18) {
                return false;
            }
        }

        return $this->canPerformFinancialOperations($user, $creditNote);
    }
}
