<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostLayer extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'inv.cost_layers';
    protected $keyType = 'string';
    public $incrementing = false;

    public const UPDATED_AT = null; // Immutable - no updated_at

    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'source_type',
        'source_id',
        'layer_date',
        'original_qty',
        'qty_remaining',
        'unit_cost',
    ];

    protected $casts = [
        'company_id' => 'string',
        'item_id' => 'string',
        'warehouse_id' => 'string',
        'source_id' => 'string',
        'layer_date' => 'date',
        'original_qty' => 'decimal:3',
        'qty_remaining' => 'decimal:3',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
