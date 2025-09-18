<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Check if the payment belongs to the user's current company
        return $payment->company_id === $user->current_company_id;
    }

    /**
     * Determine whether the user can create payments.
     */
    public function create(User $user): bool
    {
        // Users with a current company can create payments
        return $user->current_company_id !== null;
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Check if the payment belongs to the user's current company
        return $payment->company_id === $user->current_company_id;
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Check if the payment belongs to the user's current company
        return $payment->company_id === $user->current_company_id;
    }
}
