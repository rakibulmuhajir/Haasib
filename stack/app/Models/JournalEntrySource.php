<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntrySource extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.journal_entry_sources';

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
     * Indicates if the model should timestamp.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'journal_entry_id',
        'journal_transaction_id',
        'source_type',
        'source_id',
        'source_reference',
        'link_type',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the journal entry that owns the source.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the journal transaction that owns the source (if applicable).
     */
    public function journalTransaction(): BelongsTo
    {
        return $this->belongsTo(JournalTransaction::class, 'journal_transaction_id');
    }

    /**
     * Scope to get sources for a specific journal entry.
     */
    public function scopeForJournalEntry($query, string $journalEntryId)
    {
        return $query->where('journal_entry_id', $journalEntryId);
    }

    /**
     * Scope to get sources for a specific transaction.
     */
    public function scopeForTransaction($query, string $transactionId)
    {
        return $query->where('journal_transaction_id', $transactionId);
    }

    /**
     * Scope to get sources by source type.
     */
    public function scopeBySourceType($query, string $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Scope to get sources by source ID.
     */
    public function scopeBySourceId($query, string $sourceId)
    {
        return $query->where('source_id', $sourceId);
    }

    /**
     * Scope to get sources by link type.
     */
    public function scopeByLinkType($query, string $linkType)
    {
        return $query->where('link_type', $linkType);
    }

    /**
     * Scope to get origin sources.
     */
    public function scopeOrigin($query)
    {
        return $query->where('link_type', 'origin');
    }

    /**
     * Scope to get supporting sources.
     */
    public function scopeSupporting($query)
    {
        return $query->where('link_type', 'supporting');
    }

    /**
     * Scope to get reversal sources.
     */
    public function scopeReversal($query)
    {
        return $query->where('link_type', 'reversal');
    }

    /**
     * Scope to search by source reference.
     */
    public function scopeByReference($query, string $reference)
    {
        return $query->where('source_reference', 'like', "%{$reference}%");
    }

    /**
     * Check if this is an origin source.
     */
    public function isOrigin(): bool
    {
        return $this->link_type === 'origin';
    }

    /**
     * Check if this is a supporting source.
     */
    public function isSupporting(): bool
    {
        return $this->link_type === 'supporting';
    }

    /**
     * Check if this is a reversal source.
     */
    public function isReversal(): bool
    {
        return $this->link_type === 'reversal';
    }

    /**
     * Check if this source is linked to a specific transaction.
     */
    public function isLinkedToTransaction(): bool
    {
        return ! is_null($this->journal_transaction_id);
    }

    /**
     * Get the link type label.
     */
    public function getLinkTypeLabelAttribute(): string
    {
        $labels = [
            'origin' => 'Origin',
            'supporting' => 'Supporting',
            'reversal' => 'Reversal',
        ];

        return $labels[$this->link_type] ?? $this->link_type;
    }

    /**
     * Get the source type label.
     */
    public function getSourceTypeLabelAttribute(): string
    {
        $labels = [
            'Invoice' => 'Invoice',
            'Payment' => 'Payment',
            'Bill' => 'Bill',
            'PaymentAllocation' => 'Payment Allocation',
            'JournalEntry' => 'Journal Entry',
            'Manual' => 'Manual Entry',
            'RecurringTemplate' => 'Recurring Template',
        ];

        return $labels[$this->source_type] ?? $this->source_type;
    }

    /**
     * Create a new source record.
     */
    public static function createSource(
        string $journalEntryId,
        string $sourceType,
        string $sourceId,
        string $linkType = 'origin',
        ?string $journalTransactionId = null,
        ?string $sourceReference = null
    ): self {
        return static::create([
            'journal_entry_id' => $journalEntryId,
            'journal_transaction_id' => $journalTransactionId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_reference' => $sourceReference,
            'link_type' => $linkType,
            'created_at' => now(),
        ]);
    }

    /**
     * Get a readable description of the source.
     */
    public function getDescriptionAttribute(): string
    {
        $reference = $this->source_reference ?: $this->source_id;

        return "{$this->source_type}: {$reference} ({$this->link_type_label})";
    }
}
