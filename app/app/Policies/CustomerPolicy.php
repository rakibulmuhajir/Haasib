<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        // Check if the customer belongs to the user's current company
        return $customer->company_id === $user->current_company_id;
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        // Users with a current company can create customers
        return $user->current_company_id !== null;
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        // Check if the customer belongs to the user's current company
        return $customer->company_id === $user->current_company_id;
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // Check if the customer belongs to the user's current company
        return $customer->company_id === $user->current_company_id;
    }
}
