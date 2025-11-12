<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PaymentRefund extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.payment_refunds';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'payment_id',
        'amount',
        'reason',
        'refund_date',
        'refund_method',
        'reference',
        'notes',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'refund_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Constants for status
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSED = 'processed';

    const STATUS_FAILED = 'failed';

    /**
     * Get the payment for this refund.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the user who created this refund.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSED => 'Processed',
            self::STATUS_FAILED => 'Failed',
            default => $this->status,
        };
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($refund) {
            if (! $refund->id) {
                $refund->id = Str::uuid();
            }

            if (! $refund->created_by_user_id) {
                $refund->created_by_user_id = auth()->id();
            }

            if (! $refund->status) {
                $refund->status = self::STATUS_PROCESSED;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
}
