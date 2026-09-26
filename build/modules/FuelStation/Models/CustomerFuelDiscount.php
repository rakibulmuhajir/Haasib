<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's negotiated fuel discount, per fuel item: percent of the sale, or a flat
 * amount per litre. See CustomerFuelDiscountService for the one place its maths is done.
 */
class CustomerFuelDiscount extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.customer_fuel_discounts';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_PER_LITRE = 'per_litre';

    protected $fillable = [
        'company_id',
        'customer_id',
        'item_id',
        'discount_type',
        'value',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'item_id' => 'string',
        'value' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public static function getDiscountTypes(): array
    {
        return [self::TYPE_PERCENT, self::TYPE_PER_LITRE];
    }
}
