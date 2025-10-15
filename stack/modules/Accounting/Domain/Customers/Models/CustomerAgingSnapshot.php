<?php

namespace Modules\Accounting\Domain\Customers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer Aging Snapshot Model
 *
 * Represents periodic aging snapshots for customers with bucket-based analysis.
 */
class CustomerAgingSnapshot extends Model
{
    use HasFactory;

    protected $table = 'invoicing.customer_aging_snapshots';

    protected $fillable = [
        'customer_id',
        'company_id',
        'snapshot_date',
        'generated_via',
        'generated_by_user_id',
        'bucket_current',
        'bucket_1_30',
        'bucket_31_60',
        'bucket_61_90',
        'bucket_90_plus',
        'total_outstanding',
        'health_score',
        'risk_level',
        'trend_data',
        'metadata',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'generated_at' => 'datetime',
        'bucket_current' => 'decimal:15,2',
        'bucket_1_30' => 'decimal:15,2',
        'bucket_31_60' => 'decimal:15,2',
        'bucket_61_90' => 'decimal:15,2',
        'bucket_90_plus' => 'decimal:15,2',
        'total_outstanding' => 'decimal:15,2',
        'health_score' => 'integer',
        'trend_data' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the aging snapshot.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the company that owns the aging snapshot.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * Get the user who generated the snapshot.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'foreign_key', 'generated_by_user_id');
    }

    /**
     * Scope snapshots by snapshot date.
     */
    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->where('snapshot_date', $date);
    }

    /**
     * Scope snapshots within a date range.
     */
    public function scopeBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->where('snapshot_date', '>=', $startDate)
            ->where('snapshot_date', '<=', $endDate);
    }

    /**
     * Scope snapshots by generation method.
     */
    public function scopeGeneratedVia(Builder $query, string $via): Builder
    {
        return $query->where('generated_via', $via);
    }

    /**
     * Scope snapshots by risk level.
     */
    public function scopeByRiskLevel(Builder $query, string $riskLevel): Builder
    {
        return $query->where('risk_level', $riskLevel);
    }

    /**
     * Scope snapshots with outstanding balances.
     */
    public function scopeWithOutstandingBalance(Builder $query): Builder
    {
        return $query->where('total_outstanding', '>', 0);
    }

    /**
     * Scope snapshots by health score range.
     */
    public function scopeByHealthScore(Builder $query, int $minScore, int $maxScore = 100): Builder
    {
        return $query->where('health_score', '>=', $minScore)
            ->where('health_score', '<=', $maxScore);
    }

    /**
     * Get the latest snapshot for a customer.
     */
    public static function latestForCustomer(string $customerId): ?self
    {
        return static::where('customer_id', $customerId)
            ->orderBy('snapshot_date', 'desc')
            ->first();
    }

    /**
     * Get the aging bucket distribution as a percentage.
     */
    public function getBucketDistributionAttribute(): array
    {
        $total = $this->total_outstanding;

        if ($total <= 0) {
            return [
                'current' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_90_plus' => 0,
            ];
        }

        return [
            'current' => round(($this->bucket_current / $total) * 100, 2),
            'days_1_30' => round(($this->bucket_1_30 / $total) * 100, 2),
            'days_31_60' => round(($this->bucket_31_60 / $total) * 100, 2),
            'days_61_90' => round(($this->bucket_61_90 / $total) * 100, 2),
            'days_90_plus' => round(($this->bucket_90_plus / $total) * 100, 2),
        ];
    }

    /**
     * Get the aging risk level based on bucket distribution.
     */
    public function getCalculatedRiskLevelAttribute(): string
    {
        $distribution = $this->bucket_distribution;

        if ($distribution['days_90_plus'] > 50) {
            return 'critical';
        } elseif (($distribution['days_61_90'] + $distribution['days_90_plus']) > 30) {
            return 'high';
        } elseif ($distribution['days_90_plus'] > 0) {
            return 'elevated';
        } elseif (($distribution['days_31_60'] + $distribution['days_61_90']) > 20) {
            return 'moderate';
        } else {
            return 'low';
        }
    }

    /**
     * Check if the snapshot shows a healthy aging profile.
     */
    public function isHealthy(): bool
    {
        return $this->health_score >= 70 &&
               $this->risk_level === 'low' &&
               $this->bucket_distribution['current'] > 50;
    }

    /**
     * Check if the snapshot is for the current month.
     */
    public function isCurrentMonth(): bool
    {
        $now = now();

        return $this->snapshot_date->month === $now->month &&
               $this->snapshot_date->year === $now->year;
    }

    /**
     * Check if the snapshot is outdated (older than 30 days).
     */
    public function isOutdated(): bool
    {
        return $this->snapshot_date->lt(now()->subDays(30));
    }

    /**
     * Get the aging trend compared to previous snapshot.
     */
    public function getTrendAttribute(): ?string
    {
        if (! $this->trend_data || ! isset($this->trend_data['previous_total'])) {
            return null;
        }

        $previous = $this->trend_data['previous_total'];
        $current = $this->total_outstanding;

        if ($current > $previous * 1.1) {
            return 'deteriorating';
        } elseif ($current < $previous * 0.9) {
            return 'improving';
        } else {
            return 'stable';
        }
    }

    /**
     * Get formatted aging buckets for display.
     */
    public function getFormattedBucketsAttribute(): array
    {
        return [
            'current' => [
                'amount' => $this->bucket_current,
                'percentage' => $this->bucket_distribution['current'],
                'label' => 'Current',
            ],
            'days_1_30' => [
                'amount' => $this->bucket_1_30,
                'percentage' => $this->bucket_distribution['days_1_30'],
                'label' => '1-30 Days',
            ],
            'days_31_60' => [
                'amount' => $this->bucket_31_60,
                'percentage' => $this->bucket_distribution['days_31_60'],
                'label' => '31-60 Days',
            ],
            'days_61_90' => [
                'amount' => $this->bucket_61_90,
                'percentage' => $this->bucket_distribution['days_61_90'],
                'label' => '61-90 Days',
            ],
            'days_90_plus' => [
                'amount' => $this->bucket_90_plus,
                'percentage' => $this->bucket_distribution['days_90_plus'],
                'label' => '90+ Days',
            ],
        ];
    }

    /**
     * Get the aging color based on risk level.
     */
    public function getRiskColorAttribute(): string
    {
        return match ($this->risk_level) {
            'critical' => 'red',
            'high' => 'orange',
            'elevated' => 'yellow',
            'moderate' => 'blue',
            'low' => 'green',
            default => 'gray',
        };
    }

    /**
     * Scope snapshots for customers with outstanding balances.
     */
    public function scopeOutstandingCustomers(Builder $query): Builder
    {
        return $query->with('customer')
            ->where('total_outstanding', '>', 0)
            ->orderBy('total_outstanding', 'desc');
    }

    /**
     * Scope snapshots for high-risk customers.
     */
    public function scopeHighRisk(Builder $query, float $threshold = 5000.00): Builder
    {
        return $query->where('total_outstanding', '>', $threshold)
            ->whereIn('risk_level', ['critical', 'high', 'elevated'])
            ->orderBy('total_outstanding', 'desc');
    }

    /**
     * Get previous snapshot for comparison.
     */
    public function previousSnapshot(): ?self
    {
        return static::where('customer_id', $this->customer_id)
            ->where('snapshot_date', '<', $this->snapshot_date)
            ->orderBy('snapshot_date', 'desc')
            ->first();
    }

    /**
     * Get next snapshot for comparison.
     */
    public function nextSnapshot(): ?self
    {
        return static::where('customer_id', $this->customer_id)
            ->where('snapshot_date', '>', $this->snapshot_date)
            ->orderBy('snapshot_date', 'asc')
            ->first();
    }
}
