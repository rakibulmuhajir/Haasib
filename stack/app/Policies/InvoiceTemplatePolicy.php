<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;

class InvoiceTemplatePolicy
{
    /**
     * Determine if the user can view any invoice templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('templates.view');
    }

    /**
     * Determine if the user can view the invoice template.
     */
    public function view(User $user, InvoiceTemplate $template): bool
    {
        // User must have permission to view templates
        if (! $user->hasPermissionTo('templates.view')) {
            return false;
        }

        // User must have access to the template's company
        return $this->userHasCompanyAccess($user, $template->company);
    }

    /**
     * Determine if the user can create invoice templates.
     */
    public function create(User $user, Company $company): bool
    {
        // User must have permission to create templates
        if (! $user->hasPermissionTo('templates.create')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can update the invoice template.
     */
    public function update(User $user, InvoiceTemplate $template): bool
    {
        // User must have permission to update templates
        if (! $user->hasPermissionTo('templates.update')) {
            return false;
        }

        // User must have access to the template's company
        return $this->userHasCompanyAccess($user, $template->company);
    }

    /**
     * Determine if the user can delete the invoice template.
     */
    public function delete(User $user, InvoiceTemplate $template): bool
    {
        // User must have permission to delete templates
        if (! $user->hasPermissionTo('templates.delete')) {
            return false;
        }

        // User must have access to the template's company
        return $this->userHasCompanyAccess($user, $template->company);
    }

    /**
     * Determine if the user can apply the invoice template.
     */
    public function apply(User $user, InvoiceTemplate $template): bool
    {
        // User must have permission to apply templates (which usually means creating invoices)
        if (! $user->hasPermissionTo('templates.apply') && ! $user->hasPermissionTo('invoices.create')) {
            return false;
        }

        // User must have access to the template's company
        return $this->userHasCompanyAccess($user, $template->company);
    }

    /**
     * Determine if the user can duplicate the invoice template.
     */
    public function duplicate(User $user, InvoiceTemplate $template): bool
    {
        // Duplicating is essentially creating a new template, so require create permission
        if (! $user->hasPermissionTo('templates.create')) {
            return false;
        }

        // User must have access to the template's company
        return $this->userHasCompanyAccess($user, $template->company);
    }

    /**
     * Determine if the user can manage template settings.
     */
    public function manageSettings(User $user, InvoiceTemplate $template): bool
    {
        // Managing settings is essentially updating the template
        return $this->update($user, $template);
    }

    /**
     * Determine if the user can activate/deactivate the invoice template.
     */
    public function toggleStatus(User $user, InvoiceTemplate $template): bool
    {
        // Toggling status is essentially updating the template
        return $this->update($user, $template);
    }

    /**
     * Determine if the user can create templates from invoices.
     */
    public function createFromInvoice(User $user, $invoice): bool
    {
        // User must have permission to create templates
        if (! $user->hasPermissionTo('templates.create')) {
            return false;
        }

        // User must have access to the invoice's company
        return $this->userHasCompanyAccess($user, $invoice->company);
    }

    /**
     * Determine if the user can view template statistics.
     */
    public function viewStatistics(User $user, Company $company): bool
    {
        // User must have permission to view templates
        if (! $user->hasPermissionTo('templates.view')) {
            return false;
        }

        // User must have access to the company
        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if the user can export templates.
     */
    public function export(User $user, Company $company): bool
    {
        // Exporting requires view permission
        return $this->viewAny($user);
    }

    /**
     * Determine if the user can bulk operate on templates.
     */
    public function bulkOperate(User $user, Company $company): bool
    {
        // Bulk operations require appropriate permissions
        return $user->hasPermissionTo('templates.bulk') ||
               $user->hasPermissionTo('templates.update') ||
               $user->hasPermissionTo('templates.delete');
    }

    /**
     * Additional methods for fine-grained control
     */

    /**
     * Determine if user can access templates for a specific customer.
     */
    public function accessCustomerTemplates(User $user, Company $company): bool
    {
        // User must be able to view templates and access the company
        if (! $user->hasPermissionTo('templates.view')) {
            return false;
        }

        return $this->userHasCompanyAccess($user, $company);
    }

    /**
     * Determine if user can access general (non-customer-specific) templates.
     */
    public function accessGeneralTemplates(User $user, Company $company): bool
    {
        // Same permissions as customer templates
        return $this->accessCustomerTemplates($user, $company);
    }

    /**
     * Determine if user can modify template currency.
     */
    public function modifyCurrency(User $user, InvoiceTemplate $template): bool
    {
        // Currency modification is essentially updating the template
        return $this->update($user, $template);
    }

    /**
     * Determine if user can modify template customer assignment.
     */
    public function modifyCustomerAssignment(User $user, InvoiceTemplate $template): bool
    {
        // Customer assignment modification is essentially updating the template
        return $this->update($user, $template);
    }

    /**
     * Determine if user can modify template line items.
     */
    public function modifyLineItems(User $user, InvoiceTemplate $template): bool
    {
        // Line item modification is essentially updating the template
        return $this->update($user, $template);
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
     * Determine if user can access templates based on their role hierarchy.
     */
    protected function hasRoleBasedAccess(User $user, string $permission): bool
    {
        // Owner can do everything
        if ($user->hasRole('owner')) {
            return true;
        }

        // Admin can do most template operations
        if ($user->hasRole('admin') && in_array($permission, [
            'templates.view', 'templates.create', 'templates.update',
            'templates.delete', 'templates.apply', 'templates.export',
        ])) {
            return true;
        }

        // Accountant can create, update, and apply templates
        if ($user->hasRole('accountant') && in_array($permission, [
            'templates.view', 'templates.create', 'templates.update', 'templates.apply',
        ])) {
            return true;
        }

        // Viewer can only view templates
        if ($user->hasRole('viewer') && $permission === 'templates.view') {
            return true;
        }

        return false;
    }
}
