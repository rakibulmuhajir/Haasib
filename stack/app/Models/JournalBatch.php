<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalBatch extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.journal_batches';

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
        'batch_number',
        'description',
        'batch_date',
        'status',
        'total_debits',
        'total_credits',
        'total_entries',
        'metadata',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'batch_date' => 'date',
            'posted_at' => 'datetime',
            'total_entries' => 'decimal:2',
            'total_debits' => 'decimal:2',
            'total_credits' => 'decimal:2',
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
     * Get the company that owns the batch.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the user who created the batch.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who posted the batch.
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /**
     * Get the journal entries in this batch.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'batch_id');
    }

    /**
     * Scope to get batches for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get batches by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get draft batches.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get ready batches.
     */
    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    /**
     * Scope to get scheduled batches.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_post_at');
    }

    /**
     * Scope to get posted batches.
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope to get void batches.
     */
    public function scopeVoid($query)
    {
        return $query->where('status', 'void');
    }

    /**
     * Check if batch is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if batch is ready for approval.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Check if batch is scheduled for posting.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if batch is posted.
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Check if batch is void.
     */
    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Check if batch can be submitted for approval.
     */
    public function canBeSubmitted(): bool
    {
        return $this->isDraft() &&
               $this->journalEntries->count() > 0 &&
               $this->isBalanced();
    }

    /**
     * Check if batch can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->isReady() && $this->isBalanced();
    }

    /**
     * Check if batch can be posted.
     */
    public function canBePosted(): bool
    {
        return $this->isReady() && $this->isBalanced();
    }

    /**
     * Check if batch is balanced (total debits = total credits).
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debits - $this->total_credits) < 0.01;
    }

    /**
     * Check if batch is scheduled for future posting.
     */
    public function isScheduledForFuture(): bool
    {
        return $this->scheduled_post_at && $this->scheduled_post_at->isFuture();
    }

    /**
     * Check if batch can be scheduled (is ready and has future date).
     */
    public function canBeScheduled(): bool
    {
        return $this->isReady() && $this->isBalanced();
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'ready' => 'Ready',
            'scheduled' => 'Scheduled',
            'posted' => 'Posted',
            'void' => 'Void',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Calculate and update totals based on journal entries.
     */
    public function recalculateTotals(): void
    {
        $totals = $this->journalEntries()
            ->selectRaw('COUNT(*) as total_entries')
            ->selectRaw('SUM(total_debits) as total_debits')
            ->selectRaw('SUM(total_credits) as total_credits')
            ->first();

        $this->update([
            'total_entries' => $totals->total_entries ?? 0,
            'total_debits' => $totals->total_debits ?? 0,
            'total_credits' => $totals->total_credits ?? 0,
        ]);
    }

    /**
     * Add journal entries to this batch.
     */
    public function addJournalEntries(array $entryIds): bool
    {
        foreach ($entryIds as $entryId) {
            $entry = JournalEntry::find($entryId);
            if ($entry && $entry->company_id === $this->company_id) {
                $entry->update(['batch_id' => $this->id]);
            }
        }

        $this->recalculateTotals();

        return true;
    }

    /**
     * Remove journal entries from this batch.
     */
    public function removeJournalEntries(array $entryIds): bool
    {
        JournalEntry::whereIn('id', $entryIds)
            ->where('batch_id', $this->id)
            ->update(['batch_id' => null]);

        $this->recalculateTotals();

        return true;
    }
}
