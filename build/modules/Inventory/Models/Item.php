<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\TaxRate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'inv.items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'category_id',
        'sku',
        'name',
        'description',
        'item_type',
        'unit_of_measure',
        'track_inventory',
        'is_purchasable',
        'is_sellable',
        'cost_price',
        'selling_price',
        'currency',
        'tax_rate_id',
        'income_account_id',
        'expense_account_id',
        'asset_account_id',
        'reorder_point',
        'reorder_quantity',
        'weight',
        'weight_unit',
        'dimensions',
        'barcode',
        'manufacturer',
        'brand',
        'image_url',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'category_id' => 'string',
        'track_inventory' => 'boolean',
        'is_purchasable' => 'boolean',
        'is_sellable' => 'boolean',
        'cost_price' => 'decimal:6',
        'selling_price' => 'decimal:6',
        'tax_rate_id' => 'string',
        'income_account_id' => 'string',
        'expense_account_id' => 'string',
        'asset_account_id' => 'string',
        'reorder_point' => 'decimal:3',
        'reorder_quantity' => 'decimal:3',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
