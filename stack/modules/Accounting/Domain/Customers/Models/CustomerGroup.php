<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use SoftDeletes;

    protected $table = 'acct.customer_groups';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the company that owns the group.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the members of the group.
     */
    public function members()
    {
        return $this->belongsToMany(
            \App\Models\Customer::class,
            'acct.customer_group_members',
            'group_id',
            'customer_id'
        )->withTimestamps()
            ->withPivot(['joined_at', 'added_by_user_id'])
            ->using(CustomerGroupMember::class);
    }

    /**
     * Get the membership records.
     */
    public function groupMembers()
    {
        return $this->hasMany(CustomerGroupMember::class, 'group_id');
    }

    /**
     * Scope to get default groups.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to search groups by name or description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
                ->orWhere('description', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Add a customer to the group.
     */
    public function addCustomer(\App\Models\Customer $customer, ?\App\Models\User $addedBy = null): CustomerGroupMember
    {
        // Check if customer is already in the group
        if ($this->hasCustomer($customer)) {
            throw new \InvalidArgumentException('Customer is already a member of this group');
        }

        return CustomerGroupMember::create([
            'customer_id' => $customer->id,
            'group_id' => $this->id,
            'company_id' => $this->company_id,
            'added_by_user_id' => $addedBy?->id ?? auth()->id(),
        ]);
    }

    /**
     * Remove a customer from the group.
     */
    public function removeCustomer(\App\Models\Customer $customer): bool
    {
        return CustomerGroupMember::where('customer_id', $customer->id)
            ->where('group_id', $this->id)
            ->delete() > 0;
    }

    /**
     * Check if a customer is in the group.
     */
    public function hasCustomer(\App\Models\Customer $customer): bool
    {
        return $this->members()->where('customer_id', $customer->id)->exists();
    }

    /**
     * Get the count of members in the group.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->members()->count();
    }

    /**
     * Get members added within a date range.
     */
    public function getMembersAddedBetween(\DateTime $startDate, \DateTime $endDate)
    {
        return $this->groupMembers()
            ->whereBetween('joined_at', [$startDate, $endDate])
            ->with(['customer', 'addedBy'])
            ->get();
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($group) {
            // Set default group flag if no default exists for the company
            if (! $group->is_default) {
                $hasDefault = static::where('company_id', $group->company_id)
                    ->where('is_default', true)
                    ->exists();
                if (! $hasDefault) {
                    $group->is_default = true;
                }
            }
        });

        static::updating(function ($group) {
            // If setting as default, unset others
            if ($group->is_default && $group->wasChanged('is_default')) {
                static::where('company_id', $group->company_id)
                    ->where('id', '!=', $group->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
