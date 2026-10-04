<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One entry of a product's sale price history: the price from effective_date until a later entry. */
class ItemPrice extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'inv.item_prices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id', 'item_id', 'effective_date', 'sale_price', 'purchase_price', 'notes',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
        'sale_price' => 'decimal:4',
        'purchase_price' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
