<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillLineItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.bill_line_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bill_id',
        'item_id',
        'warehouse_id',
        'line_number',
        'description',
        'quantity',
        'quantity_received',
        'unit_price',
        'tax_rate',
        'discount_rate',
        'line_total',
        'tax_amount',
        'total',
        'expense_account_id',
        'account_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bill_id' => 'string',
        'item_id' => 'string',
        'warehouse_id' => 'string',
        'account_id' => 'string',
        'line_number' => 'integer',
        'quantity' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:6',
        'tax_rate' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'line_total' => 'decimal:6',
        'tax_amount' => 'decimal:6',
        'total' => 'decimal:6',
        'expense_account_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Check if this line item is for an inventory item that should be tracked.
     */
    public function isInventoryItem(): bool
    {
        return $this->item_id !== null && $this->item?->track_inventory === true;
    }

    /**
     * Get the remaining quantity to be received.
     */
    public function getRemainingQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_received);
    }

    /**
     * Check if this line item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }
}
