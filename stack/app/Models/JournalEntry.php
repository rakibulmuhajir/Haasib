<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.journal_entries';

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
        'batch_id',
        'template_id',
        'reference',
        'description',
        'date',
        'type',
        'status',
        'approval_note',
        'created_by',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'currency',
        'exchange_rate',
        'fiscal_year_id',
        'accounting_period_id',
        'source_document_type',
        'source_document_id',
        'origin_command',
        'auto_generated',
        'reverse_of_entry_id',
        'reversal_entry_id',
        'attachments',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'exchange_rate' => 'decimal:8',
            'auto_generated' => 'boolean',
            'attachments' => 'array',
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
     * Get the company that owns the journal entry.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the batch that contains the journal entry.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'batch_id');
    }

    /**
     * Get the recurring template that generated this entry.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurringJournalTemplate::class, 'template_id');
    }

    /**
     * Get the user who created the journal entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the journal entry.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who posted the journal entry.
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who voided the journal entry.
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Get the fiscal year for this entry.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    /**
     * Get the accounting period for this entry.
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    /**
     * Get the journal entry that this entry reverses.
     */
    public function reverseOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverse_of_entry_id');
    }

    /**
     * Get the journal entry that reverses this entry.
     */
    public function reversal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_entry_id');
    }

    /**
     * Get the transactions for this journal entry.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class, 'journal_entry_id')
            ->orderBy('line_number');
    }

    /**
     * Get the sources for this journal entry.
     */
    public function sources(): HasMany
    {
        return $this->hasMany(JournalEntrySource::class, 'journal_entry_id');
    }

    /**
     * Get the audit log entries for this journal entry.
     */
    public function auditLog(): HasMany
    {
        return $this->hasMany(JournalAudit::class, 'journal_entry_id')
            ->orderBy('created_at');
    }

    /**
     * Scope to get entries for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get entries by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get entries by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get entries in a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get entries for a specific period.
     */
    public function scopeForPeriod($query, string $periodId)
    {
        return $query->where('accounting_period_id', $periodId);
    }

    /**
     * Scope to get manual entries (not auto-generated).
     */
    public function scopeManual($query)
    {
        return $query->where('auto_generated', false);
    }

    /**
     * Scope to get automatic entries.
     */
    public function scopeAutomatic($query)
    {
        return $query->where('auto_generated', true);
    }

    /**
     * Scope to get entries with source documents.
     */
    public function scopeWithSourceDocument($query, string $type, string $id)
    {
        return $query->where('source_document_type', $type)
            ->where('source_document_id', $id);
    }

    /**
     * Check if entry is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if entry is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    /**
     * Check if entry is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if entry is posted.
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Check if entry is void.
     */
    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Check if entry can be submitted for approval.
     */
    public function canBeSubmitted(): bool
    {
        return $this->isDraft() && $this->transactions->count() > 0;
    }

    /**
     * Check if entry can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->isPendingApproval() && $this->isBalanced();
    }

    /**
     * Check if entry can be posted.
     */
    public function canBePosted(): bool
    {
        return $this->isApproved() && $this->isBalanced();
    }

    /**
     * Check if entry is balanced (debits = credits).
     */
    public function isBalanced(): bool
    {
        $totals = $this->transactions()
            ->selectRaw('SUM(CASE WHEN debit_credit = \'debit\' THEN amount ELSE 0 END) as total_debits')
            ->selectRaw('SUM(CASE WHEN debit_credit = \'credit\' THEN amount ELSE 0 END) as total_credits')
            ->first();

        return abs($totals->total_debits - $totals->total_credits) < 0.01;
    }

    /**
     * Get the total debits for this entry.
     */
    public function getTotalDebitsAttribute(): float
    {
        return $this->transactions()
            ->where('debit_credit', 'debit')
            ->sum('amount');
    }

    /**
     * Get the total credits for this entry.
     */
    public function getTotalCreditsAttribute(): float
    {
        return $this->transactions()
            ->where('debit_credit', 'credit')
            ->sum('amount');
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'posted' => 'Posted',
            'void' => 'Void',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'sales' => 'Sales',
            'purchase' => 'Purchase',
            'payment' => 'Payment',
            'receipt' => 'Receipt',
            'adjustment' => 'Adjustment',
            'closing' => 'Closing',
            'opening' => 'Opening',
            'reversal' => 'Reversal',
            'automation' => 'Automation',
        ];

        return $labels[$this->type] ?? $this->type;
    }
}
