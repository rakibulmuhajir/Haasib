<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CogsEntry extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'inv.cogs_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    public const UPDATED_AT = null; // Immutable - no updated_at

    protected $fillable = [
        'company_id',
        'movement_id',
        'item_id',
        'warehouse_id',
        'qty_issued',
        'unit_cost',
        'cost_amount',
        'gl_transaction_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'movement_id' => 'string',
        'item_id' => 'string',
        'warehouse_id' => 'string',
        'qty_issued' => 'decimal:3',
        'unit_cost' => 'decimal:6',
        'cost_amount' => 'decimal:2',
        'gl_transaction_id' => 'string',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function glTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'gl_transaction_id');
    }
}
