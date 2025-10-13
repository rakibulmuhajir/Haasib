<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.credit_notes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'invoice_id',
        'credit_note_number',
        'reason',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'notes',
        'terms',
        'sent_at',
        'posted_at',
        'cancelled_at',
        'cancellation_reason',
        'journal_entry_id',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'company_id' => 'string',
            'invoice_id' => 'string',
            'journal_entry_id' => 'string',
            'created_by_user_id' => 'string',
        ];
    }

    /**
     * The attributes that should be appended to the model.
     *
     * @var list<string>
     */
    protected $appends = [
        'can_be_posted',
        'can_be_cancelled',
        'is_posted',
        'is_cancelled',
        'remaining_balance',
    ];

    /**
     * Get the company that owns the credit note.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the invoice this credit note applies to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who created the credit note.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the items for the credit note.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /**
     * Get the applications for the credit note.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    /**
     * Scope a query to only include credit notes for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include credit notes with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include posted credit notes.
     */
    public function scopePosted($query)
    {
        return $query->whereNotNull('posted_at')->where('status', 'posted');
    }

    /**
     * Scope a query to only include draft credit notes.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include cancelled credit notes.
     */
    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at')->where('status', 'cancelled');
    }

    /**
     * Check if the credit note can be posted.
     */
    public function getCanBePostedAttribute(): bool
    {
        return $this->status === 'draft' && is_null($this->cancelled_at);
    }

    /**
     * Check if the credit note can be cancelled.
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['draft', 'posted']) && is_null($this->cancelled_at);
    }

    /**
     * Check if the credit note is posted.
     */
    public function getIsPostedAttribute(): bool
    {
        return ! is_null($this->posted_at) && $this->status === 'posted';
    }

    /**
     * Check if the credit note is cancelled.
     */
    public function getIsCancelledAttribute(): bool
    {
        return ! is_null($this->cancelled_at) && $this->status === 'cancelled';
    }

    /**
     * Calculate the remaining balance that can be applied to the invoice.
     */
    public function getRemainingBalanceAttribute(): float
    {
        if ($this->is_cancelled) {
            return 0;
        }

        $appliedAmount = DB::table('invoicing.credit_note_applications')
            ->where('credit_note_id', $this->id)
            ->sum('amount_applied');

        return max(0, $this->total_amount - $appliedAmount);
    }

    /**
     * Post the credit note to the ledger.
     */
    public function post(): bool
    {
        if (! $this->can_be_posted) {
            return false;
        }

        $this->status = 'posted';
        $this->posted_at = now();

        return $this->save();
    }

    /**
     * Cancel the credit note with a reason.
     */
    public function cancel(string $reason): bool
    {
        if (! $this->can_be_cancelled) {
            return false;
        }

        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;

        return $this->save();
    }

    /**
     * Generate a unique credit note number.
     */
    public static function generateCreditNoteNumber(int $companyId): string
    {
        $prefix = 'CN-';
        $year = now()->format('Y');

        // Get the next sequence number for this company and year
        $lastNumber = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->orderBy('credit_note_number', 'desc')
            ->value('credit_note_number');

        if ($lastNumber) {
            // Extract sequence number from existing format (CN-YYYY-XXXX)
            $sequence = (int) substr($lastNumber, -4) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.$year.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get a summary of the credit note.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'credit_note_number' => $this->credit_note_number,
            'invoice_number' => $this->invoice?->invoice_number,
            'customer_name' => $this->invoice?->customer?->name,
            'reason' => $this->reason,
            'amount' => (float) $this->amount,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'remaining_balance' => $this->remaining_balance,
            'created_at' => $this->created_at,
            'posted_at' => $this->posted_at,
        ];
    }

    /**
     * Validate the credit note before posting.
     */
    public function validateForPosting(): array
    {
        $errors = [];

        if ($this->status !== 'draft') {
            $errors['status'] = 'Only draft credit notes can be posted';
        }

        if ($this->is_cancelled) {
            $errors['status'] = 'Cancelled credit notes cannot be posted';
        }

        if ($this->total_amount <= 0) {
            $errors['amount'] = 'Credit note amount must be greater than zero';
        }

        if (! $this->invoice) {
            $errors['invoice'] = 'Credit note must be associated with an invoice';
        } elseif ($this->invoice->status !== 'posted') {
            $errors['invoice'] = 'Credit note can only be applied to posted invoices';
        }

        $availableBalance = $this->invoice->balance_due;
        if ($this->total_amount > $availableBalance) {
            $errors['amount'] = "Credit note amount ({$this->total_amount}) cannot exceed invoice balance due ({$availableBalance})";
        }

        return $errors;
    }

    /**
     * Apply the credit note to the invoice balance.
     */
    public function applyToInvoice(?User $user = null, ?string $notes = null): bool
    {
        if (! $this->is_posted) {
            return false;
        }

        if ($this->remaining_balance <= 0) {
            return false;
        }

        if ($this->invoice->balance_due <= 0) {
            return false; // Invoice already fully paid
        }

        DB::beginTransaction();

        try {
            // Create credit note application record
            $applicationAmount = min($this->remaining_balance, $this->invoice->balance_due);
            $balanceBefore = $this->invoice->balance_due;

            $application = DB::table('invoicing.credit_note_applications')->insert([
                'id' => str()->uuid(),
                'credit_note_id' => $this->id,
                'invoice_id' => $this->invoice_id,
                'amount_applied' => $applicationAmount,
                'applied_at' => now(),
                'user_id' => $user?->id,
                'notes' => $notes,
                'invoice_balance_before' => $balanceBefore,
                'invoice_balance_after' => $balanceBefore - $applicationAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update invoice balance
            $this->invoice->balance_due -= $applicationAmount;

            // Update invoice payment status if fully paid
            if ($this->invoice->balance_due <= 0) {
                $this->invoice->payment_status = 'paid';
                $this->invoice->paid_at = now();
            } elseif ($this->invoice->balance_due < $this->invoice->total_amount) {
                $this->invoice->payment_status = 'partially_paid';
            }

            $this->invoice->save();

            // Log the application
            activity()
                ->performedOn($this)
                ->causedBy($user ?? auth()->user())
                ->withProperties([
                    'amount_applied' => $applicationAmount,
                    'invoice_balance_before' => $balanceBefore,
                    'invoice_balance_after' => $this->invoice->balance_due,
                    'notes' => $notes,
                ])
                ->log('Credit note applied to invoice');

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
