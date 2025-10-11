<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.fiscal_years';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'is_locked',
        'notes',
    ];

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
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'company_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the fiscal year.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the accounting periods for the fiscal year.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    /**
     * Get the journal entries for the fiscal year.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Scope a query to only include active fiscal years.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include closed fiscal years.
     */
    public function scopeClosed($query)
    {
        return $query->where('is_locked', true);
    }

    /**
     * Check if the fiscal year is currently active.
     */
    public function isCurrent(): bool
    {
        $now = now();

        return $this->is_active && $this->start_date <= $now && $this->end_date >= $now;
    }

    /**
     * Close the fiscal year.
     */
    public function close(): void
    {
        $this->is_locked = true;
        $this->save();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\FiscalYearFactory::new();
    }
}
