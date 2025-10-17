<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankReconciliationMatch extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ledger.bank_reconciliation_matches';

    protected $primaryKey = 'id';

    protected $fillable = [
        'reconciliation_id',
        'statement_line_id',
        'source_type',
        'source_id',
        'matched_at',
        'matched_by',
        'amount',
        'auto_matched',
        'confidence_score',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'matched_at' => 'datetime',
        'confidence_score' => 'decimal:2',
        'auto_matched' => 'boolean',
    ];

    protected $attributes = [
        'auto_matched' => false,
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'statement_line_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForReconciliation($query, $reconciliationId)
    {
        return $query->where('reconciliation_id', $reconciliationId);
    }

    public function scopeForStatementLine($query, $statementLineId)
    {
        return $query->where('statement_line_id', $statementLineId);
    }

    public function scopeAutoMatched($query)
    {
        return $query->where('auto_matched', true);
    }

    public function scopeManuallyMatched($query)
    {
        return $query->where('auto_matched', false);
    }

    public function scopeWithConfidence($query, float $minScore, ?float $maxScore = null)
    {
        $query->where('confidence_score', '>=', $minScore);

        if ($maxScore !== null) {
            $query->where('confidence_score', '<=', $maxScore);
        }

        return $query;
    }

    public function scopeForSource($query, string $sourceType, string $sourceId)
    {
        return $query->where('source_type', $sourceType)
            ->where('source_id', $sourceId);
    }

    public function isAutoMatched(): bool
    {
        return $this->auto_matched;
    }

    public function isManualMatch(): bool
    {
        return ! $this->auto_matched;
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence_score !== null && $this->confidence_score >= 0.9;
    }

    public function isMediumConfidence(): bool
    {
        return $this->confidence_score !== null && $this->confidence_score >= 0.7 && $this->confidence_score < 0.9;
    }

    public function isLowConfidence(): bool
    {
        return $this->confidence_score !== null && $this->confidence_score < 0.7;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }

    public function getFormattedConfidenceScoreAttribute(): string
    {
        return $this->confidence_score ? number_format($this->confidence_score * 100, 1).'%' : 'N/A';
    }

    public function getConfidenceLevelAttribute(): string
    {
        if ($this->isHighConfidence()) {
            return 'high';
        } elseif ($this->isMediumConfidence()) {
            return 'medium';
        } elseif ($this->isLowConfidence()) {
            return 'low';
        } else {
            return 'manual';
        }
    }

    public function getMatchedAtAgoAttribute(): string
    {
        return $this->matched_at->diffForHumans();
    }

    public function getSourceDisplayNameAttribute(): string
    {
        if ($this->relationLoaded('source') && $this->source) {
            switch ($this->source_type) {
                case 'ledger.journal_entry':
                    return 'Journal Entry #'.$this->source->id;
                case 'acct.payment':
                    return 'Payment #'.$this->source->id;
                case 'acct.credit_note':
                    return 'Credit Note #'.$this->source->id;
                default:
                    return class_basename($this->source_type).' #'.$this->source->id;
            }
        }

        return class_basename($this->source_type).' #'.$this->source_id;
    }

    public function getSourceUrlAttribute(): ?string
    {
        if ($this->relationLoaded('source') && $this->source) {
            switch ($this->source_type) {
                case 'ledger.journal_entry':
                    return route('journal-entries.show', $this->source_id);
                case 'acct.payment':
                    return route('payments.show', $this->source_id);
                case 'acct.credit_note':
                    return route('credit-notes.show', $this->source_id);
                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * Create a new match with audit logging
     */
    public static function createMatch(array $data, User $user): self
    {
        $match = new static([
            'reconciliation_id' => $data['reconciliation_id'],
            'statement_line_id' => $data['statement_line_id'],
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'],
            'amount' => $data['amount'],
            'matched_by' => $user->id,
            'matched_at' => now(),
            'auto_matched' => $data['auto_matched'] ?? false,
            'confidence_score' => $data['confidence_score'] ?? null,
        ]);

        $match->save();

        // Load relationships for event
        $match->load(['reconciliation', 'statementLine']);

        // Log the match creation
        activity()
            ->performedOn($match)
            ->causedBy($user)
            ->withProperties([
                'reconciliation_id' => $match->reconciliation_id,
                'statement_line_id' => $match->statement_line_id,
                'source_type' => $match->source_type,
                'source_id' => $match->source_id,
                'amount' => $match->amount,
                'auto_matched' => $match->auto_matched,
            ])
            ->log('bank_reconciliation_match_created');

        // Emit match event for real-time updates
        event(new \Modules\Ledger\Events\BankReconciliationMatched(
            $match->reconciliation,
            $match,
            $match->auto_matched
        ));

        return $match;
    }

    /**
     * Delete the match with audit logging
     */
    public function deleteMatch(User $user): bool
    {
        activity()
            ->performedOn($this)
            ->causedBy($user)
            ->withProperties([
                'reconciliation_id' => $this->reconciliation_id,
                'statement_line_id' => $this->statement_line_id,
                'source_type' => $this->source_type,
                'source_id' => $this->source_id,
                'amount' => $this->amount,
            ])
            ->log('bank_reconciliation_match_deleted');

        return $this->delete();
    }
}
