<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCreditLimit extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'acct.customer_credit_limits';

    protected $fillable = [
        'customer_id',
        'company_id',
        'limit_amount',
        'effective_at',
        'expires_at',
        'status',
        'reason',
        'changed_by_user_id',
        'approval_reference',
    ];

    protected $casts = [
        'limit_amount' => 'decimal:2',
        'effective_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the credit limit.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the company that owns the credit limit.
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * Get the user who changed the credit limit.
     */
    public function changedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by_user_id');
    }

    /**
     * Check if the credit limit is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        $now = now();

        // Must be effective as of now
        if ($this->effective_at->gt($now)) {
            return false;
        }

        // Must not be expired
        if ($this->expires_at && $this->expires_at->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the credit limit is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the credit limit has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    /**
     * Approve the credit limit.
     */
    public function approve(?string $approvalReference = null): bool
    {
        $this->status = 'approved';
        $this->approval_reference = $approvalReference;

        return $this->save();
    }

    /**
     * Revoke the credit limit.
     */
    public function revoke(?string $reason = null): bool
    {
        $this->status = 'revoked';
        if ($reason) {
            $this->reason = $reason;
        }

        return $this->save();
    }

    /**
     * Get the formatted limit amount.
     */
    public function getFormattedLimitAmountAttribute(): string
    {
        return number_format($this->limit_amount, 2);
    }

    /**
     * Scope to get only active credit limits.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'approved')
            ->where('effective_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get credit limits effective as of a specific date.
     */
    public function scopeEffectiveAsOf($query, $date)
    {
        return $query->where('effective_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $date);
            });
    }

    /**
     * Scope to get future credit limits.
     */
    public function scopeFuture($query)
    {
        return $query->where('effective_at', '>', now());
    }

    /**
     * Scope to get credit limits by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get the most recent active credit limit for a customer.
     */
    public static function getActiveForCustomer($customerId): ?self
    {
        return static::where('customer_id', $customerId)
            ->active()
            ->orderBy('effective_at', 'desc')
            ->first();
    }

    /**
     * Get the credit limit that will be active on a specific date.
     */
    public static function getForCustomerOnDate($customerId, $date): ?self
    {
        return static::where('customer_id', $customerId)
            ->where('status', 'approved')
            ->where('effective_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $date);
            })
            ->orderBy('effective_at', 'desc')
            ->first();
    }

    /**
     * Check if this credit limit conflicts with another active limit.
     */
    public function hasConflictWith(?self $otherLimit): bool
    {
        if (! $otherLimit || $otherLimit->id === $this->id) {
            return false;
        }

        if ($otherLimit->status !== 'approved' || $this->status !== 'approved') {
            return false;
        }

        $thisStart = $this->effective_at;
        $thisEnd = $this->expires_at;
        $otherStart = $otherLimit->effective_at;
        $otherEnd = $otherLimit->expires_at;

        // Check for overlap in effective periods
        return ($thisStart->lte($otherEnd) && (! $thisEnd || $thisEnd->gte($otherStart))) ||
               ($otherStart->lte($thisEnd) && (! $otherEnd || $otherEnd->gte($thisStart)));
    }
}
