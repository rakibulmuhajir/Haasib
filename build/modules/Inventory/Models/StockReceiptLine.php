<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\BillLineItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptLine extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'inv.stock_receipt_lines';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'stock_receipt_id',
        'bill_line_item_id',
        'item_id',
        'warehouse_id',
        'expected_quantity',
        'received_quantity',
        'variance_quantity',
        'unit_cost',
        'total_cost',
        'variance_cost',
        'variance_reason',
        'stock_movement_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'stock_receipt_id' => 'string',
        'bill_line_item_id' => 'string',
        'item_id' => 'string',
        'warehouse_id' => 'string',
        'expected_quantity' => 'decimal:3',
        'received_quantity' => 'decimal:3',
        'variance_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
        'variance_cost' => 'decimal:2',
        'variance_reason' => 'string',
        'stock_movement_id' => 'string',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function billLineItem(): BelongsTo
    {
        return $this->belongsTo(BillLineItem::class, 'bill_line_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
