<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only trail of item price entries being added, changed or deleted. */
class ItemPriceChange extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'pgsql';
    protected $table = 'inv.item_price_changes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id', 'item_id', 'effective_date', 'action',
        'old_sale_price', 'new_sale_price', 'old_purchase_price', 'new_purchase_price',
        'changed_by_user_id', 'changed_at',
    ];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
        'old_sale_price' => 'decimal:4',
        'new_sale_price' => 'decimal:4',
        'old_purchase_price' => 'decimal:4',
        'new_purchase_price' => 'decimal:4',
        'changed_at' => 'datetime',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
