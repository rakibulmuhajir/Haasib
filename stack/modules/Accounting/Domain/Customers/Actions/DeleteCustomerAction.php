<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Exceptions\CustomerDeletionException;

class DeleteCustomerAction
{
    /**
     * Soft delete a customer.
     */
    public function execute(Company $company, string $customerId, User $deletedBy): bool
    {
        // Find customer
        $customer = $this->findCustomer($company, $customerId);

        // Validate customer can be deleted
        $this->validateCanDelete($customer);

        try {
            DB::beginTransaction();

            // Store data for audit before deletion
            $auditData = [
                'customer_id' => $customer->id,
                'company_id' => $company->id,
                'user_id' => $deletedBy->id,
                'customer_number' => $customer->customer_number,
                'name' => $customer->name,
                'email' => $customer->email,
                'status' => $customer->status,
                'deleted_at' => now()->toISOString(),
            ];

            // Soft delete the customer
            $customer->delete();

            // Emit audit event
            Event::dispatch('customer.deleted', $auditData);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomerDeletionException('Failed to delete customer: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Find customer or throw exception.
     */
    private function findCustomer(Company $company, string $customerId): Customer
    {
        $customer = Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw new CustomerDeletionException('Customer not found');
        }

        return $customer;
    }

    /**
     * Validate that customer can be deleted.
     */
    private function validateCanDelete(Customer $customer): void
    {
        // Check if customer has unpaid invoices
        $unpaidInvoicesCount = $customer->invoices()
            ->where('status', '!=', 'paid')
            ->count();

        if ($unpaidInvoicesCount > 0) {
            throw ValidationException::withMessages([
                'customer' => "Cannot delete customer with {$unpaidInvoicesCount} unpaid invoice(s). Settle all invoices before deletion.",
            ]);
        }

        // Check if customer has pending credit limit adjustments
        $pendingCreditAdjustments = $customer->creditLimits()
            ->where('status', 'pending')
            ->count();

        if ($pendingCreditAdjustments > 0) {
            throw ValidationException::withMessages([
                'customer' => "Cannot delete customer with {$pendingCreditAdjustments} pending credit adjustment(s).",
            ]);
        }

        // Additional business rules can be added here
        // For example: check for open statements, recent communications, etc.
    }

    /**
     * Force delete a customer (bypassing validations).
     * Use with caution and ensure proper authorization.
     */
    public function forceDelete(Company $company, string $customerId, User $deletedBy): bool
    {
        // Find customer (including soft-deleted)
        $customer = Customer::where('company_id', $company->id)
            ->withTrashed()
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw new CustomerDeletionException('Customer not found');
        }

        try {
            DB::beginTransaction();

            // Store data for audit
            $auditData = [
                'customer_id' => $customer->id,
                'company_id' => $company->id,
                'user_id' => $deletedBy->id,
                'customer_number' => $customer->customer_number,
                'name' => $customer->name,
                'force_deleted_at' => now()->toISOString(),
            ];

            // Force delete
            $customer->forceDelete();

            // Emit audit event
            Event::dispatch('customer.force_deleted', $auditData);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomerDeletionException('Failed to force delete customer: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Restore a soft-deleted customer.
     */
    public function restore(Company $company, string $customerId, User $restoredBy): Customer
    {
        $customer = Customer::where('company_id', $company->id)
            ->withTrashed()
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw new CustomerDeletionException('Customer not found');
        }

        if (! $customer->trashed()) {
            throw new CustomerDeletionException('Customer is not deleted');
        }

        try {
            DB::beginTransaction();

            $customer->restore();

            // Emit audit event
            Event::dispatch('customer.restored', [
                'customer_id' => $customer->id,
                'company_id' => $company->id,
                'user_id' => $restoredBy->id,
                'restored_at' => now()->toISOString(),
            ]);

            DB::commit();

            return $customer->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomerDeletionException('Failed to restore customer: '.$e->getMessage(), 0, $e);
        }
    }
}
