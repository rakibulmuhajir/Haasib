<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'payment_number',
        'payment_date',
        'payment_method',
        'reference_number',
        'amount',
        'currency',
        'status',
        'notes',
        'paymentable_id',
        'paymentable_type',
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
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'company_id' => 'string',
            'customer_id' => 'string',
            'paymentable_id' => 'string',
            'created_by_user_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the payment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer for the payment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created the payment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the payable model (invoice, etc.).
     */
    public function paymentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include payments with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to filter by payment method.
     */
    public function scopeWithMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Mark the payment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->save();

        // Update the invoice balance if applicable
        if ($this->paymentable && method_exists($this->paymentable, 'markAsPaid')) {
            $this->paymentable->calculateTotals();
            $this->paymentable->markAsPaid();
        }
    }

    /**
     * Mark the payment as failed.
     */
    public function markAsFailed(): void
    {
        $this->status = 'failed';
        $this->save();
    }

    /**
     * Generate a unique payment number.
     */
    public static function generatePaymentNumber(string $companyId): string
    {
        $prefix = 'PAY';
        $year = now()->format('Y');
        $sequence = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->withTrashed()
            ->count() + 1;

        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\PaymentFactory::new();
    }
}
