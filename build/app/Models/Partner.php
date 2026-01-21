<?php

namespace App\Models;

use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'auth.partners';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'phone',
        'email',
        'cnic',
        'address',
        'profit_share_percentage',
        'drawing_limit_period',
        'drawing_limit_amount',
        'drawing_account_id',
        'total_invested',
        'total_withdrawn',
        'current_period_withdrawn',
        'period_reset_date',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'user_id' => 'string',
        'drawing_account_id' => 'string',
        'profit_share_percentage' => 'decimal:2',
        'drawing_limit_amount' => 'decimal:2',
        'total_invested' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'current_period_withdrawn' => 'decimal:2',
        'period_reset_date' => 'date',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
    ];

    protected $hidden = [
        'cnic',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function drawingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'drawing_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PartnerTransaction::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(PartnerTransaction::class)
            ->where('transaction_type', 'investment');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(PartnerTransaction::class)
            ->where('transaction_type', 'withdrawal');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Accessors & Computed Properties
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get net capital (invested - withdrawn)
     */
    public function getNetCapitalAttribute(): float
    {
        return (float) $this->total_invested - (float) $this->total_withdrawn;
    }

    /**
     * Get remaining drawing limit for current period
     */
    public function getRemainingDrawingLimitAttribute(): ?float
    {
        if ($this->drawing_limit_period === 'none' || $this->drawing_limit_amount === null) {
            return null; // No limit
        }

        return max(0, (float) $this->drawing_limit_amount - (float) $this->current_period_withdrawn);
    }

    /**
     * Check if partner can withdraw a specific amount
     */
    public function canWithdraw(float $amount): bool
    {
        // No limit set
        if ($this->drawing_limit_period === 'none' || $this->drawing_limit_amount === null) {
            return true;
        }

        $remaining = $this->remaining_drawing_limit;
        return $remaining !== null && $amount <= $remaining;
    }

    /**
     * Check if period needs to be reset
     */
    public function shouldResetPeriod(): bool
    {
        if ($this->drawing_limit_period === 'none') {
            return false;
        }

        if ($this->period_reset_date === null) {
            return true;
        }

        $now = now();
        $resetDate = $this->period_reset_date;

        if ($this->drawing_limit_period === 'monthly') {
            return $now->startOfMonth()->gt($resetDate);
        }

        if ($this->drawing_limit_period === 'yearly') {
            return $now->startOfYear()->gt($resetDate);
        }

        return false;
    }

    /**
     * Reset period withdrawal counter
     */
    public function resetPeriod(): void
    {
        $this->current_period_withdrawn = 0;
        $this->period_reset_date = now()->startOfDay();
        $this->save();
    }
}
