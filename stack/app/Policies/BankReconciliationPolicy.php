<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\Company;
use App\Models\User;

class BankReconciliationPolicy
{
    /**
     * Determine if the user can view any bank reconciliations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bank_reconciliations.view');
    }

    /**
     * Determine if the user can view the bank reconciliation.
     */
    public function view(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to view reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.view')) {
            return false;
        }

        // User must have access to the reconciliation's company
        return $this->userHasCompanyAccess($user, $reconciliation->company);
    }

    /**
     * Determine if the user can create bank reconciliations.
     */
    public function create(User $user, Company $company): bool
    {
        // User must have permission to create reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.create')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can update the bank reconciliation.
     */
    public function update(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to update reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.update')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be editable (not locked or completed without reopen permission)
        return $reconciliation->canBeEdited() || $user->can('reopen', $reconciliation);
    }

    /**
     * Determine if the user can delete the bank reconciliation.
     */
    public function delete(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to delete reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.delete')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Can only delete draft reconciliations
        return $reconciliation->isDraft();
    }

    /**
     * Determine if the user can complete the bank reconciliation.
     */
    public function complete(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to complete reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.complete')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be in progress and have zero variance
        return $reconciliation->canBeCompleted();
    }

    /**
     * Determine if the user can lock the bank reconciliation.
     */
    public function lock(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to lock reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.lock')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be completed
        return $reconciliation->canBeLocked();
    }

    /**
     * Determine if the user can reopen the bank reconciliation.
     */
    public function reopen(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to reopen reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.reopen')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be locked
        return $reconciliation->canBeReopened();
    }

    /**
     * Determine if the user can create matches in the reconciliation.
     */
    public function createMatch(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to create matches
        if (! $user->hasPermissionTo('bank_reconciliation_matches.create')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be editable
        return $reconciliation->canBeEdited();
    }

    /**
     * Determine if the user can delete matches in the reconciliation.
     */
    public function deleteMatch(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to delete matches
        if (! $user->hasPermissionTo('bank_reconciliation_matches.delete')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be editable
        return $reconciliation->canBeEdited();
    }

    /**
     * Determine if the user can run auto-match on the reconciliation.
     */
    public function autoMatch(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to run auto-match
        if (! $user->hasPermissionTo('bank_reconciliation_matches.auto_match')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be editable
        return $reconciliation->canBeEdited();
    }

    /**
     * Determine if the user can create adjustments in the reconciliation.
     */
    public function createAdjustment(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to create adjustments
        if (! $user->hasPermissionTo('bank_reconciliation_adjustments.create')) {
            return false;
        }

        // User must have access to the reconciliation's company
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Reconciliation must be editable
        return $reconciliation->canBeEdited();
    }

    /**
     * Determine if the user can view adjustments in the reconciliation.
     */
    public function viewAdjustments(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to view adjustments
        if (! $user->hasPermissionTo('bank_reconciliation_adjustments.view')) {
            return false;
        }

        // User must have access to the reconciliation's company
        return $this->userHasCompanyAccess($user, $reconciliation->company);
    }

    /**
     * Determine if the user can export the reconciliation.
     */
    public function export(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to export reconciliations
        if (! $user->hasPermissionTo('bank_reconciliations.export')) {
            return false;
        }

        // User must have access to the reconciliation's company
        return $this->userHasCompanyAccess($user, $reconciliation->company);
    }

    /**
     * Determine if the user can view reconciliation history/audit trail.
     */
    public function viewHistory(User $user, BankReconciliation $reconciliation): bool
    {
        // User must have permission to view reconciliations
        return $this->view($user, $reconciliation);
    }

    /**
     * Determine if the user can access reconciliation reports.
     */
    public function viewReports(User $user, Company $company): bool
    {
        // User must have permission to view reconciliation reports
        if (! $user->hasPermissionTo('bank_reconciliation_reports.view')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can bulk operate on reconciliations.
     */
    public function bulkOperate(User $user, Company $company): bool
    {
        // Bulk operations require appropriate permissions
        return $user->hasPermissionTo('bank_reconciliations.bulk') ||
               $user->hasPermissionTo('bank_reconciliations.update') ||
               $user->hasPermissionTo('bank_reconciliations.delete');
    }

    /**
     * Determine if the user can manage reconciliation settings.
     */
    public function manageSettings(User $user, Company $company): bool
    {
        // Settings management typically requires admin or owner role
        return $user->hasRole(['owner', 'admin']) &&
               $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Additional methods for fine-grained control
     */

    /**
     * Determine if user can access reconciliations for a specific bank account.
     */
    public function accessAccountReconciliations(User $user, $bankAccount): bool
    {
        // User must be able to view reconciliations and access the company
        if (! $user->hasPermissionTo('bank_reconciliations.view')) {
            return false;
        }

        return $this->userHasCompanyAccess($user, $bankAccount->company);
    }

    /**
     * Determine if user can override system warnings during completion.
     */
    public function overrideCompletionWarnings(User $user, BankReconciliation $reconciliation): bool
    {
        // Only users with admin/owner roles can override warnings
        if (! $user->hasRole(['owner', 'admin'])) {
            return false;
        }

        // Must have completion permission and company access
        return $this->complete($user, $reconciliation);
    }

    /**
     * Determine if user can force complete a reconciliation with minor variance.
     */
    public function forceComplete(User $user, BankReconciliation $reconciliation): bool
    {
        // Only users with admin/owner roles can force complete
        if (! $user->hasRole(['owner', 'admin'])) {
            return false;
        }

        // Must have company access and variance must be small
        if (! $this->userHasCompanyAccess($user, $reconciliation->company)) {
            return false;
        }

        // Only allow for small variances (less than $1.00)
        return abs($reconciliation->variance) < 1.00;
    }

    /**
     * Determine if user can reopen reconciliations from closed periods.
     */
    public function reopenClosedPeriod(User $user, BankReconciliation $reconciliation): bool
    {
        // Only users with owner role can reopen closed periods
        if (! $user->hasRole('owner')) {
            return false;
        }

        // Must have reopen permission and company access
        return $this->reopen($user, $reconciliation);
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
     * Determine if user can access reconciliations based on their role hierarchy.
     */
    protected function hasRoleBasedAccess(User $user, string $permission): bool
    {
        // Owner can do everything
        if ($user->hasRole('owner')) {
            return true;
        }

        // Admin can do most reconciliation operations
        if ($user->hasRole('admin') && in_array($permission, [
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.update',
            'bank_reconciliations.delete', 'bank_reconciliations.complete', 'bank_reconciliations.lock',
            'bank_reconciliations.reopen', 'bank_reconciliations.export',
            'bank_reconciliation_matches.create', 'bank_reconciliation_matches.delete',
            'bank_reconciliation_matches.auto_match',
            'bank_reconciliation_adjustments.create', 'bank_reconciliation_adjustments.view',
        ])) {
            return true;
        }

        // Accountant can perform most reconciliation tasks
        if ($user->hasRole('accountant') && in_array($permission, [
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.update',
            'bank_reconciliations.complete', 'bank_reconciliations.export',
            'bank_reconciliation_matches.create', 'bank_reconciliation_matches.delete',
            'bank_reconciliation_matches.auto_match',
            'bank_reconciliation_adjustments.create', 'bank_reconciliation_adjustments.view',
        ])) {
            return true;
        }

        // Bookkeeper can handle matching and adjustments
        if ($user->hasRole('bookkeeper') && in_array($permission, [
            'bank_reconciliations.view',
            'bank_reconciliation_matches.create', 'bank_reconciliation_matches.delete',
            'bank_reconciliation_matches.auto_match',
            'bank_reconciliation_adjustments.create', 'bank_reconciliation_adjustments.view',
        ])) {
            return true;
        }

        // Viewer can only view reconciliations
        if ($user->hasRole('viewer') && $permission === 'bank_reconciliations.view') {
            return true;
        }

        return false;
    }
}
