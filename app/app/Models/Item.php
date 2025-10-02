<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'item_code',
        'name',
        'description',
        'unit_price',
        'currency_id',
        'item_type',
        'category_id',
        'taxable',
        'track_inventory',
        'reorder_level',
        'stock_quantity',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'taxable' => 'boolean',
        'track_inventory' => 'boolean',
        'reorder_level' => 'decimal:4',
        'stock_quantity' => 'decimal:4',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'item_type' => 'service',
        'taxable' => true,
        'track_inventory' => false,
        'reorder_level' => 0,
        'stock_quantity' => 0,
        'is_active' => true,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });

        static::creating(function ($item) {
            if (! $item->item_code) {
                $item->item_code = $item->generateItemCode();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeTaxable($query, $taxable = true)
    {
        return $query->where('taxable', $taxable);
    }

    public function scopeTrackInventory($query, $track = true)
    {
        return $query->where('track_inventory', $track);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_quantity <= reorder_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function generateItemCode(): string
    {
        $company = $this->company;
        $year = now()->year;

        $prefix = $company->settings['item_prefix'] ?? 'ITEM';
        $pattern = $company->settings['item_code_pattern'] ?? '{prefix}-{year}-{sequence:4}';

        $latestItem = static::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->orderByRaw('CAST(SUBSTRING(item_code FROM GREATEST(POSITION("-" IN item_code), POSITION(" " IN item_code)) + 1) AS UNSIGNED) DESC')
            ->first();

        $sequence = $latestItem ? ((int) preg_replace('/.*?(\d+)$/', '$1', $latestItem->item_code)) + 1 : 1;

        return str_replace(
            ['{prefix}', '{year}', '{sequence:4}', '{sequence:5}', '{sequence:6}'],
            [$prefix, $year, str_pad($sequence, 4, '0', STR_PAD_LEFT), str_pad($sequence, 5, '0', STR_PAD_LEFT), str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $pattern
        );
    }

    public function isProduct(): bool
    {
        return $this->item_type === 'product';
    }

    public function isService(): bool
    {
        return $this->item_type === 'service';
    }

    public function isInventoryTracked(): bool
    {
        return $this->track_inventory;
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= 0;
    }

    public function isInStock(): bool
    {
        return ! $this->track_inventory || $this->stock_quantity > 0;
    }

    public function getStockStatus(): string
    {
        if (! $this->track_inventory) {
            return 'Not Tracked';
        }

        if ($this->stock_quantity <= 0) {
            return 'Out of Stock';
        }

        if ($this->stock_quantity <= $this->reorder_level) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    public function getStockStatusColor(): string
    {
        return match ($this->getStockStatus()) {
            'Out of Stock' => 'red',
            'Low Stock' => 'yellow',
            'In Stock' => 'green',
            default => 'gray',
        };
    }

    public function adjustStock(float $quantity, string $movementType, ?string $reference = null, ?string $notes = null): StockMovement
    {
        if (! $this->track_inventory) {
            throw new \InvalidArgumentException('Inventory tracking is not enabled for this item');
        }

        $newQuantity = $this->stock_quantity + $quantity;

        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('Insufficient stock quantity');
        }

        $movement = StockMovement::create([
            'item_id' => $this->id,
            'company_id' => $this->company_id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'previous_quantity' => $this->stock_quantity,
            'new_quantity' => $newQuantity,
            'reference' => $reference,
            'notes' => $notes,
        ]);

        $this->stock_quantity = $newQuantity;
        $this->save();

        return $movement;
    }

    public function getStockMovements(int $limit = 50)
    {
        return $this->stockMovements()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getTotalStockValue(): float
    {
        if (! $this->track_inventory) {
            return 0;
        }

        return $this->stock_quantity * $this->unit_price;
    }

    public function getInventoryTurnoverRatio(): float
    {
        $soldQuantity = $this->invoiceItems()
            ->whereHas('invoice', fn ($q) => $q->where('status', 'paid'))
            ->sum('quantity');

        if ($soldQuantity <= 0) {
            return 0;
        }

        return $soldQuantity / max($this->stock_quantity, 1);
    }

    public function getDaysOfInventory(): float
    {
        $turnoverRatio = $this->getInventoryTurnoverRatio();

        if ($turnoverRatio <= 0) {
            return 0;
        }

        return 365 / $turnoverRatio;
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->item_code;
    }

    public function getDisplayUnitPrice(): string
    {
        return number_format($this->unit_price, 2).' '.$this->currency->code;
    }

    public function getDisplayStockQuantity(): string
    {
        if (! $this->track_inventory) {
            return 'N/A';
        }

        return number_format($this->stock_quantity, 4);
    }

    public function getDisplayReorderLevel(): string
    {
        if (! $this->track_inventory) {
            return 'N/A';
        }

        return number_format($this->reorder_level, 4);
    }

    public function getItemTypeLabel(): string
    {
        return match ($this->item_type) {
            'product' => 'Product',
            'service' => 'Service',
            default => ucfirst($this->item_type),
        };
    }

    public function canBeDeleted(): bool
    {
        return ! $this->invoiceItems()->exists() && ! $this->stockMovements()->exists();
    }

    public function duplicate(): self
    {
        $duplicate = $this->replicate([
            'id',
            'item_code',
            'created_at',
            'updated_at',
        ]);

        $duplicate->item_code = $this->generateItemCode();
        $duplicate->name = $this->name.' (Copy)';
        $duplicate->save();

        return $duplicate;
    }
}
