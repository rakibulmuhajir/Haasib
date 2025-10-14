<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.products';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'product_code',
        'name',
        'description',
        'unit_price',
        'cost',
        'tax_rate',
        'account_code',
        'inventory_tracking',
        'stock_quantity',
        'min_stock_level',
        'is_active',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'stock_quantity' => 'decimal:4',
            'min_stock_level' => 'decimal:4',
            'inventory_tracking' => 'boolean',
            'is_active' => 'boolean',
            'company_id' => 'string',
            'created_by_user_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the product.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the invoice line items for the product.
     */
    public function invoiceLineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search products by name or code.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('product_code', 'ilike', "%{$term}%")
                ->orWhere('description', 'ilike', "%{$term}%");
        });
    }

    /**
     * Check if product is low in stock.
     */
    public function isLowStock(): bool
    {
        return $this->inventory_tracking &&
            $this->stock_quantity <= $this->min_stock_level;
    }

    /**
     * Decrease stock quantity.
     */
    public function decreaseStock(float $quantity): void
    {
        if ($this->inventory_tracking) {
            $this->stock_quantity -= $quantity;
            $this->save();
        }
    }

    /**
     * Increase stock quantity.
     */
    public function increaseStock(float $quantity): void
    {
        if ($this->inventory_tracking) {
            $this->stock_quantity += $quantity;
            $this->save();
        }
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\ProductFactory::new();
    }
}
