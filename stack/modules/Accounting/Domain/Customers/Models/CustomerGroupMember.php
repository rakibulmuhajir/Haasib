<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomerGroupMember extends Pivot
{
    protected $table = 'invoicing.customer_group_members';

    protected $fillable = [
        'customer_id',
        'group_id',
        'company_id',
        'joined_at',
        'added_by_user_id',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $dates = [
        'joined_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the customer.
     */
    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    /**
     * Get the group.
     */
    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    /**
     * Get the company.
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    /**
     * Get the user who added the customer to the group.
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * Get the membership duration in days.
     */
    public function getMembershipDurationAttribute(): int
    {
        return $this->joined_at->diffInDays(now());
    }

    /**
     * Get a formatted membership duration.
     */
    public function getFormattedMembershipDurationAttribute(): string
    {
        $duration = $this->membership_duration;

        if ($duration < 30) {
            return "{$duration} days";
        } elseif ($duration < 365) {
            $months = round($duration / 30);

            return "{$months} month".($months > 1 ? 's' : '');
        } else {
            $years = floor($duration / 365);
            $remainingMonths = round(($duration % 365) / 30);

            $result = "{$years} year".($years > 1 ? 's' : '');
            if ($remainingMonths > 0) {
                $result .= ", {$remainingMonths} month".($remainingMonths > 1 ? 's' : '');
            }

            return $result;
        }
    }

    /**
     * Check if the membership is recent (joined in the last 30 days).
     */
    public function isRecent(): bool
    {
        return $this->joined_at->greaterThan(now()->subDays(30));
    }

    /**
     * Get membership statistics for a company.
     */
    public static function getMembershipStats(Company $company): array
    {
        $total = static::where('company_id', $company->id)->count();
        $recent = static::where('company_id', $company->id)
            ->where('joined_at', '>=', now()->subDays(30))
            ->count();

        $groupBreakdown = static::select('customer_groups.name', \DB::raw('count(*) as count'))
            ->join('invoicing.customer_groups', 'customer_group_members.group_id', '=', 'customer_groups.id')
            ->where('customer_group_members.company_id', $company->id)
            ->groupBy('customer_groups.id', 'customer_groups.name')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'total_memberships' => $total,
            'recent_memberships' => $recent,
            'group_breakdown' => $groupBreakdown->toArray(),
        ];
    }

    /**
     * Get customers who are members of multiple groups.
     */
    public static function getCustomersInMultipleGroups(Company $company, int $minGroups = 2)
    {
        return static::select('customer_id', \DB::raw('count(*) as group_count'))
            ->where('company_id', $company->id)
            ->groupBy('customer_id')
            ->having('group_count', '>=', $minGroups)
            ->with('customer')
            ->orderBy('group_count', 'desc')
            ->get();
    }

    /**
     * Get membership trends over time.
     */
    public static function getMembershipTrends(Company $company, int $months = 12)
    {
        return static::select(
            \DB::raw('DATE_TRUNC(\'month\', joined_at) as month'),
            \DB::raw('count(*) as new_memberships')
        )
            ->where('company_id', $company->id)
            ->where('joined_at', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Boot the pivot model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($membership) {
            if (! $membership->joined_at) {
                $membership->joined_at = now();
            }

            if (! $membership->added_by_user_id) {
                $membership->added_by_user_id = auth()->id();
            }
        });
    }
}
