<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.credit_notes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'credit_note_number',
        'credit_date',
        'reason',
        'amount',
        'base_currency',
        'status',
        'notes',
        'terms',
        'sent_at',
        'posted_at',
        'voided_at',
        'cancellation_reason',
        'journal_entry_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_date' => 'date',
            'sent_at' => 'datetime',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'amount' => 'decimal:2',
            'base_currency' => 'string',
            'company_id' => 'string',
            'customer_id' => 'string',
            'invoice_id' => 'string',
            'journal_entry_id' => 'string',
            'created_by_user_id' => 'string',
            'updated_by_user_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Cached remaining balance to avoid repeated sum queries per request.
     */
    protected ?float $remainingBalanceCache = null;

    /**
     * Get the company that owns the credit note.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer this credit note belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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
     * Determine if the credit note can be posted.
     */
    public function canBePosted(): bool
    {
        return $this->status === 'draft' && is_null($this->cancelled_at);
    }

    /**
     * @deprecated Use canBePosted().
     */
    public function getCanBePostedAttribute(): bool
    {
        return $this->canBePosted();
    }

    /**
     * Determine if the credit note can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'posted'], true) && is_null($this->cancelled_at);
    }

    /**
     * @deprecated Use canBeCancelled().
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    /**
     * Determine if the credit note is posted.
     */
    public function isPosted(): bool
    {
        return ! is_null($this->posted_at) && $this->status === 'posted';
    }

    /**
     * @deprecated Use isPosted().
     */
    public function getIsPostedAttribute(): bool
    {
        return $this->isPosted();
    }

    /**
     * Determine if the credit note is cancelled.
     */
    public function isCancelled(): bool
    {
        return ! is_null($this->cancelled_at) && $this->status === 'cancelled';
    }

    /**
     * @deprecated Use isCancelled().
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->isCancelled();
    }

    /**
     * Calculate the remaining balance that can be applied to the invoice.
     */
    public function remainingBalance(): float
    {
        if ($this->isCancelled()) {
            return 0.0;
        }

        if ($this->remainingBalanceCache !== null) {
            return $this->remainingBalanceCache;
        }

        $appliedAmount = DB::table('acct.credit_note_applications')
            ->where('credit_note_id', $this->id)
            ->sum('amount_applied');

        $this->remainingBalanceCache = max(0, (float) $this->total_amount - (float) $appliedAmount);

        return $this->remainingBalanceCache;
    }

    /**
     * @deprecated Use remainingBalance().
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->remainingBalance();
    }

    /**
     * Post the credit note to the ledger.
     */
    public function post(): bool
    {
        if (! $this->canBePosted()) {
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
        if (! $this->canBeCancelled()) {
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
    public static function generateCreditNoteNumber(string $companyId): string
    {
        $prefix = 'CN-';
        $year = now()->format('Y');

        $resolver = function () use ($companyId, $prefix, $year) {
            $lastNumber = static::query()
                ->where('company_id', $companyId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->orderByDesc('credit_note_number')
                ->value('credit_note_number');

            $sequence = $lastNumber
                ? ((int) substr($lastNumber, -4)) + 1
                : 1;

            return $prefix.$year.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $resolver();
        }

        return DB::transaction($resolver, 5);
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
            'remaining_balance' => $this->remainingBalance(),
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

        if (! $this->canBePosted()) {
            $errors['status'] = 'Only draft credit notes can be posted';
        }

        if ($this->isCancelled()) {
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
        if (! $this->isPosted()) {
            return false;
        }

        if ($this->remainingBalance() <= 0) {
            return false;
        }

        if ($this->invoice->balance_due <= 0) {
            return false; // Invoice already fully paid
        }

        DB::beginTransaction();

        try {
            // Create credit note application record
            $applicationAmount = min($this->remainingBalance(), $this->invoice->balance_due);
            $balanceBefore = $this->invoice->balance_due;

            $application = DB::table('acct.credit_note_applications')->insert([
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
