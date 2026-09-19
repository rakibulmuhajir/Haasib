<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reopening of a locked Daily Close: who, when, why, and the lock it replaced.
 *
 * Append-only at the database (fuel.prevent_unlock_trail_mutation), so this model has no
 * updated_at and never updates or deletes. Record a further unlock rather than editing one.
 */
class DailyCloseUnlock extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.daily_close_unlocks';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'close_transaction_id',
        'unlocked_at',
        'unlocked_by_user_id',
        'reason',
        'previously_locked_at',
        'previously_locked_by_user_id',
        'previous_lock_reason',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'previously_locked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function closeTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'close_transaction_id');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by_user_id');
    }

    public function previouslyLockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previously_locked_by_user_id');
    }
}
