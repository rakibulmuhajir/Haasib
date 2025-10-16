<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringJournalTemplate extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.recurring_journal_templates';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'frequency',
        'custom_cron',
        'next_run_at',
        'last_run_at',
        'auto_post',
        'active',
        'created_by',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'auto_post' => 'boolean',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * Get the company that owns the template.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the user who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the template lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RecurringJournalTemplateLine::class, 'template_id')
            ->orderBy('line_number');
    }

    /**
     * Get the journal entries generated from this template.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'template_id');
    }

    /**
     * Scope to get templates for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get inactive templates.
     */
    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    /**
     * Scope to get templates that are due to run.
     */
    public function scopeDueToRun($query)
    {
        return $query->active()
            ->where('next_run_at', '<=', now());
    }

    /**
     * Scope to get templates by frequency.
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope to get templates that auto-post.
     */
    public function scopeAutoPost($query)
    {
        return $query->where('auto_post', true);
    }

    /**
     * Scope to get templates that create drafts.
     */
    public function scopeCreateDrafts($query)
    {
        return $query->where('auto_post', false);
    }

    /**
     * Check if template is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Check if template is inactive.
     */
    public function isInactive(): bool
    {
        return ! $this->active;
    }

    /**
     * Check if template auto-posts generated entries.
     */
    public function doesAutoPost(): bool
    {
        return $this->auto_post;
    }

    /**
     * Check if template creates draft entries.
     */
    public function createsDrafts(): bool
    {
        return ! $this->auto_post;
    }

    /**
     * Check if template is due to run.
     */
    public function isDueToRun(): bool
    {
        return $this->active && $this->next_run_at->isPast();
    }

    /**
     * Check if template has custom cron expression.
     */
    public function hasCustomCron(): bool
    {
        return $this->frequency === 'custom' && ! empty($this->custom_cron);
    }

    /**
     * Check if template is balanced (lines sum to zero).
     */
    public function isBalanced(): bool
    {
        $debits = $this->lines()->where('debit_credit', 'debit')->count();
        $credits = $this->lines()->where('debit_credit', 'credit')->count();

        // For true balance checking, we'd need to evaluate the amount formulas
        // This is a simplified check that we have both debits and credits
        return $debits > 0 && $credits > 0;
    }

    /**
     * Get the frequency label.
     */
    public function getFrequencyLabelAttribute(): string
    {
        $labels = [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annually' => 'Annually',
            'custom' => 'Custom',
        ];

        return $labels[$this->frequency] ?? $this->frequency;
    }

    /**
     * Get the cron expression for this template.
     */
    public function getCronExpressionAttribute(): string
    {
        if ($this->frequency === 'custom') {
            return $this->custom_cron ?? '* * * * *';
        }

        $patterns = [
            'daily' => '0 0 * * *',         // At midnight
            'weekly' => '0 0 * * 0',        // At midnight on Sunday
            'monthly' => '0 0 1 * *',       // At midnight on 1st of month
            'quarterly' => '0 0 1 */3 *',   // At midnight on 1st of month, every 3 months
            'annually' => '0 0 1 1 *',      // At midnight on Jan 1st
        ];

        return $patterns[$this->frequency] ?? '* * * * *';
    }

    /**
     * Calculate the next run date based on frequency.
     */
    public function calculateNextRunDate(): \DateTime
    {
        $now = now();

        switch ($this->frequency) {
            case 'daily':
                return $now->addDay();
            case 'weekly':
                return $now->addWeek();
            case 'monthly':
                return $now->addMonth();
            case 'quarterly':
                return $now->addMonths(3);
            case 'annually':
                return $now->addYear();
            case 'custom':
                // For custom cron, we'd need a cron parser
                // For now, just add a day as a fallback
                return $now->addDay();
            default:
                return $now->addDay();
        }
    }

    /**
     * Update the next run date.
     */
    public function updateNextRunDate(): void
    {
        $this->update([
            'next_run_at' => $this->calculateNextRunDate(),
        ]);
    }

    /**
     * Mark as last run now.
     */
    public function markAsLastRun(): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRunDate(),
        ]);
    }

    /**
     * Activate the template.
     */
    public function activate(): void
    {
        if (! $this->active) {
            $this->update([
                'active' => true,
                'next_run_at' => $this->calculateNextRunDate(),
            ]);
        }
    }

    /**
     * Deactivate the template.
     */
    public function deactivate(): void
    {
        $this->update(['active' => false]);
    }

    /**
     * Toggle auto-post setting.
     */
    public function toggleAutoPost(): void
    {
        $this->update(['auto_post' => ! $this->auto_post]);
    }

    /**
     * Generate a preview of the journal entry that would be created.
     */
    public function generatePreview(): array
    {
        return [
            'template_id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'next_run_at' => $this->next_run_at,
            'auto_post' => $this->auto_post,
            'lines' => $this->lines->map(function ($line) {
                return [
                    'account_id' => $line->account_id,
                    'debit_credit' => $line->debit_credit,
                    'amount_formula' => $line->amount_formula,
                    'description' => $line->description,
                ];
            })->toArray(),
        ];
    }
}
