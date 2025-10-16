<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalAudit extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.journal_audit_log';

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
        'event_type',
        'actor_id',
        'payload',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the journal entry that owns the audit record.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the user who performed the action (if applicable).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scope to get audit records for a specific journal entry.
     */
    public function scopeForJournalEntry($query, string $journalEntryId)
    {
        return $query->where('journal_entry_id', $journalEntryId);
    }

    /**
     * Scope to get audit records by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get audit records by actor.
     */
    public function scopeByActor($query, string $actorId)
    {
        return $query->where('actor_id', $actorId);
    }

    /**
     * Scope to get audit records in a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get created events.
     */
    public function scopeCreated($query)
    {
        return $query->where('event_type', 'created');
    }

    /**
     * Scope to get updated events.
     */
    public function scopeUpdated($query)
    {
        return $query->where('event_type', 'updated');
    }

    /**
     * Scope to get posted events.
     */
    public function scopePosted($query)
    {
        return $query->where('event_type', 'posted');
    }

    /**
     * Scope to get voided events.
     */
    public function scopeVoided($query)
    {
        return $query->where('event_type', 'voided');
    }

    /**
     * Scope to get approved events.
     */
    public function scopeApproved($query)
    {
        return $query->where('event_type', 'approved');
    }

    /**
     * Scope to get reversed events.
     */
    public function scopeReversed($query)
    {
        return $query->where('event_type', 'reversed');
    }

    /**
     * Scope to get attachment events.
     */
    public function scopeAttachmentAdded($query)
    {
        return $query->where('event_type', 'attachment_added');
    }

    /**
     * Scope to get system events (no actor).
     */
    public function scopeSystem($query)
    {
        return $query->whereNull('actor_id');
    }

    /**
     * Scope to get user events (has actor).
     */
    public function scopeByUser($query)
    {
        return $query->whereNotNull('actor_id');
    }

    /**
     * Check if this is a created event.
     */
    public function isCreated(): bool
    {
        return $this->event_type === 'created';
    }

    /**
     * Check if this is an updated event.
     */
    public function isUpdated(): bool
    {
        return $this->event_type === 'updated';
    }

    /**
     * Check if this is a posted event.
     */
    public function isPosted(): bool
    {
        return $this->event_type === 'posted';
    }

    /**
     * Check if this is a voided event.
     */
    public function isVoided(): bool
    {
        return $this->event_type === 'voided';
    }

    /**
     * Check if this is an approved event.
     */
    public function isApproved(): bool
    {
        return $this->event_type === 'approved';
    }

    /**
     * Check if this is a reversed event.
     */
    public function isReversed(): bool
    {
        return $this->event_type === 'reversed';
    }

    /**
     * Check if this is an attachment added event.
     */
    public function isAttachmentAdded(): bool
    {
        return $this->event_type === 'attachment_added';
    }

    /**
     * Check if this was performed by a system process.
     */
    public function isSystemAction(): bool
    {
        return is_null($this->actor_id);
    }

    /**
     * Check if this was performed by a user.
     */
    public function isUserAction(): bool
    {
        return ! is_null($this->actor_id);
    }

    /**
     * Get the event type label.
     */
    public function getEventTypeLabelAttribute(): string
    {
        $labels = [
            'created' => 'Created',
            'updated' => 'Updated',
            'posted' => 'Posted',
            'voided' => 'Voided',
            'approved' => 'Approved',
            'reversed' => 'Reversed',
            'attachment_added' => 'Attachment Added',
        ];

        return $labels[$this->event_type] ?? $this->event_type;
    }

    /**
     * Get a human-readable description of the event.
     */
    public function getDescriptionAttribute(): string
    {
        $actorName = $this->actor ? $this->actor->name : 'System';

        return "{$this->event_type_label} by {$actorName}";
    }

    /**
     * Create a new audit record.
     */
    public static function createEvent(
        string $journalEntryId,
        string $eventType,
        array $payload = [],
        ?string $actorId = null
    ): self {
        return static::create([
            'journal_entry_id' => $journalEntryId,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Create a system-generated audit record.
     */
    public static function createSystemEvent(
        string $journalEntryId,
        string $eventType,
        array $payload = []
    ): self {
        return static::createEvent($journalEntryId, $eventType, $payload);
    }

    /**
     * Create a user-generated audit record.
     */
    public static function createUserEvent(
        string $journalEntryId,
        string $eventType,
        string $actorId,
        array $payload = []
    ): self {
        return static::createEvent($journalEntryId, $eventType, $payload, $actorId);
    }

    /**
     * Get the previous state from the payload.
     */
    public function getPreviousStateAttribute(): ?array
    {
        return $this->payload['previous_state'] ?? null;
    }

    /**
     * Get the new state from the payload.
     */
    public function getNewStateAttribute(): ?array
    {
        return $this->payload['new_state'] ?? null;
    }

    /**
     * Get the changes from the payload.
     */
    public function getChangesAttribute(): ?array
    {
        return $this->payload['changes'] ?? null;
    }

    /**
     * Get any metadata from the payload.
     */
    public function getMetadataAttribute(): ?array
    {
        return $this->payload['metadata'] ?? null;
    }
}
