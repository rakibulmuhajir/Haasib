<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateChange extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.rate_changes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'item_id',
        'effective_date',
        'purchase_rate',
        'sale_rate',
        'stock_quantity_at_change',
        'margin_impact',
        'revaluation_amount',
        'previous_avg_cost',
        'journal_entry_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'item_id' => 'string',
        'effective_date' => 'date',
        'purchase_rate' => 'decimal:2',
        'sale_rate' => 'decimal:2',
        'stock_quantity_at_change' => 'decimal:2',
        'margin_impact' => 'decimal:2',
        'revaluation_amount' => 'decimal:2',
        'previous_avg_cost' => 'decimal:4',
        'journal_entry_id' => 'string',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'journal_entry_id');
    }

    /**
     * Check if this rate change has a revaluation posted.
     */
    public function hasRevaluation(): bool
    {
        return $this->journal_entry_id !== null;
    }

    /**
     * Get the current rate for a fuel item (most recent effective rate).
     */
    public static function getCurrentRate(string $companyId, string $itemId): ?self
    {
        return static::getRateForDate($companyId, $itemId, now()->toDateString());
    }

    /**
     * Get the rate for a specific date (most recent effective rate on or before that date).
     */
    public static function getRateForDate(string $companyId, string $itemId, string $date): ?self
    {
        return static::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();
    }

    /**
     * Get the margin (sale_rate - purchase_rate).
     */
    public function getMarginAttribute(): float
    {
        return $this->sale_rate - $this->purchase_rate;
    }
}
