<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'fuel.investors';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'cnic',
        'total_invested',
        'total_commission_earned',
        'total_commission_paid',
        'is_active',
        'investor_account_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'total_invested' => 'decimal:2',
        'total_commission_earned' => 'decimal:2',
        'total_commission_paid' => 'decimal:2',
        'is_active' => 'boolean',
        'investor_account_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function investorAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'investor_account_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InvestorLot::class);
    }

    public function activeLots(): HasMany
    {
        return $this->lots()->where('status', InvestorLot::STATUS_ACTIVE);
    }

    /**
     * Get the outstanding commission (earned - paid).
     */
    public function getOutstandingCommissionAttribute(): float
    {
        return $this->total_commission_earned - $this->total_commission_paid;
    }

    /**
     * Get total remaining units across all active lots.
     */
    public function getTotalUnitsRemainingAttribute(): float
    {
        return $this->activeLots()->sum('units_remaining');
    }

    /**
     * Recalculate totals from lots.
     */
    public function recalculateTotals(): void
    {
        $this->total_invested = $this->lots()->sum('investment_amount');
        $this->total_commission_earned = $this->lots()->sum('commission_earned');
        $this->save();
    }
}
