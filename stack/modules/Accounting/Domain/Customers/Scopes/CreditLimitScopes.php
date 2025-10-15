<?php

namespace Modules\Accounting\Domain\Customers\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CreditLimitScopes
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        // Automatically scope to current company for multi-tenancy
        if (config('tenancy.enabled')) {
            $builder->where('company_id', current_company_id());
        }
    }

    /**
     * Scope to get credit limits for a specific customer.
     */
    public function scopeForCustomer(Builder $builder, $customerId)
    {
        return $builder->where('customer_id', $customerId);
    }

    /**
     * Scope to get credit limits for a specific company.
     */
    public function scopeForCompany(Builder $builder, $companyId)
    {
        return $builder->where('company_id', $companyId);
    }

    /**
     * Scope to get credit limits that are currently valid.
     */
    public function scopeCurrentlyValid(Builder $builder)
    {
        return $builder->where('status', 'approved')
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get credit limits that will be valid in the future.
     */
    public function scopeFutureValid(Builder $builder)
    {
        return $builder->where('status', 'approved')
            ->where('effective_at', '>', now());
    }

    /**
     * Scope to get credit limits that are expiring soon.
     */
    public function scopeExpiringSoon(Builder $builder, int $days = 30)
    {
        return $builder->where('status', 'approved')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired credit limits.
     */
    public function scopeExpired(Builder $builder)
    {
        return $builder->where('status', 'approved')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope to get credit limits pending approval.
     */
    public function scopePending(Builder $builder)
    {
        return $builder->where('status', 'pending');
    }

    /**
     * Scope to get revoked credit limits.
     */
    public function scopeRevoked(Builder $builder)
    {
        return $builder->where('status', 'revoked');
    }

    /**
     * Scope to get credit limits changed by a specific user.
     */
    public function scopeChangedBy(Builder $builder, $userId)
    {
        return $builder->where('changed_by_user_id', $userId);
    }

    /**
     * Scope to get credit limits within a specific amount range.
     */
    public function scopeWithAmountBetween(Builder $builder, $min, $max)
    {
        return $builder->whereBetween('limit_amount', [$min, $max]);
    }

    /**
     * Scope to get credit limits above a specific amount.
     */
    public function scopeWithAmountAbove(Builder $builder, $amount)
    {
        return $builder->where('limit_amount', '>', $amount);
    }

    /**
     * Scope to get credit limits below a specific amount.
     */
    public function scopeWithAmountBelow(Builder $builder, $amount)
    {
        return $builder->where('limit_amount', '<', $amount);
    }

    /**
     * Scope to get credit limits with a specific approval reference.
     */
    public function scopeWithApprovalReference(Builder $builder, $reference)
    {
        return $builder->where('approval_reference', $reference);
    }

    /**
     * Scope to search credit limits by reason.
     */
    public function scopeSearchReason(Builder $builder, $term)
    {
        return $builder->where('reason', 'ILIKE', "%{$term}%");
    }

    /**
     * Scope to get credit limits effective in a date range.
     */
    public function scopeEffectiveBetween(Builder $builder, $startDate, $endDate)
    {
        return $builder->whereBetween('effective_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get credit limits expiring in a date range.
     */
    public function scopeExpiringBetween(Builder $builder, $startDate, $endDate)
    {
        return $builder->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get the latest credit limit for each customer.
     */
    public function scopeLatestPerCustomer(Builder $builder)
    {
        return $builder->orderBy('customer_id')
            ->orderBy('effective_at', 'desc')
            ->distinct('customer_id');
    }

    /**
     * Scope to include credit limit usage statistics.
     */
    public function scopeWithUsageStats(Builder $builder)
    {
        return $builder->with(['customer' => function ($query) {
            $query->select('id', 'name');
        }]);
    }

    /**
     * Scope to get credit limits that can be modified.
     */
    public function scopeModifiable(Builder $builder)
    {
        return $builder->whereIn('status', ['approved', 'pending'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()->addDay());
            });
    }
}
