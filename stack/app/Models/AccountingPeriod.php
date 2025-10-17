<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountingPeriod extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.accounting_periods';

    /**
     * The attributes that are not mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'period_number' => 'integer',
            'fiscal_year_id' => 'string',
            'closed_by' => 'string',
            'reopened_by' => 'string',
            'company_id' => 'string',
        ];
    }

    /**
     * Get the fiscal year for the period.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the user who closed the period.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the journal entries for the period.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get the period close record for this period.
     */
    public function periodClose(): HasOne
    {
        return $this->hasOne(\Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::class, 'accounting_period_id');
    }

    /**
     * Scope a query to only include open periods.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include closed periods.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope a query to only include closing periods.
     */
    public function scopeClosing($query)
    {
        return $query->where('status', 'closing');
    }

    /**
     * Scope a query to only include reopened periods.
     */
    public function scopeReopened($query)
    {
        return $query->where('status', 'reopened');
    }

    /**
     * Check if the period is currently active.
     */
    public function isCurrent(): bool
    {
        $now = now();

        return $this->status === 'open' && $this->start_date <= $now && $this->end_date >= $now;
    }

    /**
     * Check if the period can be closed.
     */
    public function canBeClosed(): bool
    {
        return in_array($this->status, ['open', 'reopened']);
    }

    /**
     * Check if the period is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if the period is closing.
     */
    public function isClosing(): bool
    {
        return $this->status === 'closing';
    }

    /**
     * Check if the period has been reopened.
     */
    public function isReopened(): bool
    {
        return $this->status === 'reopened';
    }

    /**
     * Close the period.
     */
    public function close(User $user, ?string $summary = null): void
    {
        $this->status = 'closed';
        $this->closed_at = now();
        $this->closed_by = $user->id;
        if ($summary) {
            $this->closing_notes = $summary;
        }
        $this->save();
    }

    /**
     * Reopen the period.
     */
    public function reopen(User $user): void
    {
        $this->status = 'reopened';
        $this->reopened_at = now();
        $this->reopened_by = $user->id;
        $this->save();
    }

    /**
     * Start closing the period.
     */
    public function startClosing(): void
    {
        $this->status = 'closing';
        $this->save();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\AccountingPeriodFactory::new();
    }
}
