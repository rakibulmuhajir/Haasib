<?php

namespace App\Models;

use App\StateMachines\JournalEntryStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.journal_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'reference',
        'date',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'source_type',
        'source_id',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'draft',
        'total_debit' => 0,
        'total_credit' => 0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

        static::deleting(function ($journalEntry) {
            // Prevent hard-deletes of posted entries. They must be voided.
            if ($journalEntry->isPosted() && ! $journalEntry->isForceDeleting()) {
                throw new \LogicException('Posted journal entries cannot be deleted. They must be voided.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeVoid($query)
    {
        return $query->where('status', 'void');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function canBePosted(): bool
    {
        return $this->isDraft() && $this->journalLines()->count() >= 2;
    }

    public function canBeVoided(): bool
    {
        return $this->isPosted();
    }

    public function isBalanced(): bool
    {
        return $this->total_debit === $this->total_credit;
    }

    /**
     * Recalculates and sets the total debit and credit amounts from the lines.
     * Does not save the model.
     */
    public function calculateTotals(): void
    {
        $totals = $this->journalLines()
            ->selectRaw('SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->first();

        $this->total_debit = $totals->total_debit ?? 0;
        $this->total_credit = $totals->total_credit ?? 0;
    }

    public function stateMachine(): JournalEntryStateMachine
    {
        return new JournalEntryStateMachine($this);
    }
}
