<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCost extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'inv.item_costs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'avg_unit_cost',
        'qty_on_hand',
        'value_on_hand',
    ];

    protected $casts = [
        'company_id' => 'string',
        'item_id' => 'string',
        'warehouse_id' => 'string',
        'avg_unit_cost' => 'decimal:6',
        'qty_on_hand' => 'decimal:3',
        'value_on_hand' => 'decimal:2',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
