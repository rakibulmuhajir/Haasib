<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Check if the invoice belongs to the user's current company
        return $invoice->company_id === $user->current_company_id;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        // Users with a current company can create invoices
        return $user->current_company_id !== null;
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Check if the invoice belongs to the user's current company
        return $invoice->company_id === $user->current_company_id;
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Check if the invoice belongs to the user's current company
        return $invoice->company_id === $user->current_company_id;
    }
}
