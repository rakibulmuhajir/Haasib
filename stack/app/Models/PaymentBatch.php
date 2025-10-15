<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PaymentBatch extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'invoicing.payment_receipt_batches';

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
        'status',
        'receipt_count',
        'total_amount',
        'currency',
        'processed_at',
        'processing_started_at',
        'processing_finished_at',
        'created_by_user_id',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'processing_finished_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'status_label',
        'source_type',
        'processing_duration_minutes',
        'estimated_completion',
        'progress_percentage',
        'has_payments',
    ];

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($batch) {
            if ($batch->status === null) {
                $batch->status = 'pending';
            }
            
            if ($batch->receipt_count === null) {
                $batch->receipt_count = 0;
            }
            
            if ($batch->total_amount === null) {
                $batch->total_amount = 0;
            }
        });

        static::updating(function ($batch) {
            // Set processing timestamps based on status changes
            if ($batch->isDirty('status')) {
                switch ($batch->status) {
                    case 'processing':
                        if (!$batch->processing_started_at) {
                            $batch->processing_started_at = now();
                        }
                        break;
                    case 'completed':
                    case 'failed':
                        if (!$batch->processing_finished_at) {
                            $batch->processing_finished_at = now();
                        }
                        if (!$batch->processed_at) {
                            $batch->processed_at = now();
                        }
                        break;
                }
            }
        });
    }

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
     * Get the payments for this batch.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'batch_id');
    }

    /**
     * Scope to get batches for a specific company.
     */
    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get batches by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get processing batches.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Scope to get completed batches.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed batches.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Check if batch can be processed.
     */
    public function canBeProcessed(): bool
    {
        return in_array($this->status, ['pending', 'failed']);
    }

    /**
     * Check if batch is currently processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if batch has completed processing.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if batch failed processing.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the status label attribute.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'archived' => 'Archived',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get the source type from metadata.
     */
    public function getSourceTypeAttribute(): string
    {
        return $this->metadata['source_type'] ?? 'manual';
    }

    /**
     * Get processing duration in minutes.
     */
    public function getProcessingDurationMinutesAttribute(): ?float
    {
        if ($this->processing_started_at && $this->processing_finished_at) {
            return $this->processing_started_at->diffInMinutes($this->processing_finished_at);
        }

        return null;
    }

    /**
     * Get estimated completion time.
     */
    public function getEstimatedCompletionAttribute(): ?string
    {
        if ($this->isProcessing() && $this->processing_started_at) {
            // Estimate based on average processing time (e.g., 2 seconds per payment)
            $avgTimePerPayment = 2; // seconds
            $estimatedTotalSeconds = $this->receipt_count * $avgTimePerPayment;
            $elapsedSeconds = $this->processing_started_at->diffInSeconds(now());
            
            if ($elapsedSeconds < $estimatedTotalSeconds) {
                $remainingSeconds = $estimatedTotalSeconds - $elapsedSeconds;
                return now()->addSeconds($remainingSeconds)->toISOString();
            }
        }

        return null;
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        if ($this->isProcessing() && $this->processing_started_at) {
            $avgTimePerPayment = 2; // seconds
            $estimatedTotalSeconds = $this->receipt_count * $avgTimePerPayment;
            $elapsedSeconds = $this->processing_started_at->diffInSeconds(now());
            
            return min(100, (int) (($elapsedSeconds / $estimatedTotalSeconds) * 100));
        }

        return 0;
    }

    /**
     * Check if batch has associated payments.
     */
    public function getHasPaymentsAttribute(): bool
    {
        return $this->payments()->exists();
    }

    /**
     * Get error details from metadata.
     */
    public function getErrorDetails(): array
    {
        return $this->metadata['error_details'] ?? [];
    }

    /**
     * Get error type from metadata.
     */
    public function getErrorType(): ?string
    {
        return $this->metadata['error_type'] ?? null;
    }
}