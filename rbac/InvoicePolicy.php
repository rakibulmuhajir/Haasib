<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\CurrentCompany;

class InvoicePolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Super admin bypasses all checks
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('accounts_invoice_view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }

        return $user->can('accounts_invoice_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('accounts_invoice_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }

        if (!$user->can('accounts_invoice_update')) {
            return false;
        }

        // Status-based rule: cannot update paid or voided invoices
        if (in_array($invoice->status, ['paid', 'voided'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }

        if (!$user->can('accounts_invoice_delete')) {
            return false;
        }

        // Status-based rule: can only delete draft invoices
        if ($invoice->status !== 'draft') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can approve the invoice.
     */
    public function approve(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }

        if (!$user->can('accounts_invoice_approve')) {
            return false;
        }

        // Status-based rule: can only approve pending invoices
        if ($invoice->status !== 'pending') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can void the invoice.
     */
    public function void(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }

        if (!$user->can('accounts_invoice_void')) {
            return false;
        }

        // Status-based rule: cannot void already voided or draft
        if (in_array($invoice->status, ['voided', 'draft'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can send the invoice.
     */
    public function send(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }

        if (!$user->can('accounts_invoice_send')) {
            return false;
        }

        // Status-based rule: can only send approved invoices
        if ($invoice->status !== 'approved') {
            return false;
        }

        return true;
    }

    /**
     * Check if the invoice belongs to the current company context.
     */
    private function belongsToCurrentCompany(Invoice $invoice): bool
    {
        $currentCompany = app(CurrentCompany::class)->get();

        return $currentCompany && $invoice->company_id === $currentCompany->id;
    }
}
