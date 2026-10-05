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
        'capital_account_id',
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
        'capital_account_id' => 'string',
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

    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'capital_account_id');
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
     * Net capital from the books: Capital account balance less Drawings account balance
     * (profit shares included). Before the partner has accounts: invested less withdrawn.
     */
    public function getNetCapitalAttribute(): float
    {
        if ($this->capital_account_id && $this->drawing_account_id) {
            return app(\App\Services\PartnerLedgerService::class)->balance($this);
        }

        return (float) $this->total_invested - (float) $this->total_withdrawn;
    }

    /**
     * What the partner has withdrawn in the limit's current period (the calendar month or year
     * of $asOf), from their withdrawal transactions by transaction_date. The old
     * current_period_withdrawn counter is no longer read: nothing ever reset it.
     */
    public function withdrawnThisPeriod(\DateTimeInterface|string|null $asOf = null): float
    {
        $at = $asOf ? \Carbon\Carbon::parse($asOf) : now();
        $query = PartnerTransaction::where('partner_id', $this->id)->where('transaction_type', 'withdrawal');
        if ($this->drawing_limit_period === 'yearly') {
            $query->whereBetween('transaction_date', [$at->copy()->startOfYear()->toDateString(), $at->copy()->endOfYear()->toDateString()]);
        } else {
            $query->whereBetween('transaction_date', [$at->copy()->startOfMonth()->toDateString(), $at->copy()->endOfMonth()->toDateString()]);
        }

        return round((float) $query->sum('amount'), 2);
    }

    public function getWithdrawnThisPeriodAttribute(): float
    {
        return $this->withdrawnThisPeriod();
    }

    /**
     * Get remaining drawing limit for current period (limit less withdrawn this period; may be
     * negative when the limit was exceeded). Null when there is no limit.
     */
    public function getRemainingDrawingLimitAttribute(): ?float
    {
        if ($this->drawing_limit_period === 'none' || $this->drawing_limit_amount === null) {
            return null; // No limit
        }

        return round((float) $this->drawing_limit_amount - $this->withdrawnThisPeriod(), 2);
    }

    /**
     * Within the drawing limit? Informational: going over only warns, it never blocks.
     */
    public function canWithdraw(float $amount): bool
    {
        $remaining = $this->remaining_drawing_limit;

        return $remaining === null || $amount <= $remaining;
    }

    /**
     * Recompute total_invested, total_withdrawn and the period counter from the transactions.
     * (The auth.update_partner_totals trigger does the same sums; this keeps the model fresh.)
     */
    public function refreshTotals(): void
    {
        $sum = fn (string $type) => round((float) PartnerTransaction::where('partner_id', $this->id)->where('transaction_type', $type)->sum('amount'), 2);
        $this->forceFill([
            'total_invested' => $sum('investment'),
            'total_withdrawn' => $sum('withdrawal'),
            'current_period_withdrawn' => $this->withdrawnThisPeriod(),
        ])->saveQuietly();
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
