<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\Customer as BaseCustomer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing.customer_addresses';

    protected $fillable = [
        'customer_id',
        'company_id',
        'label',
        'type',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the customer that owns the address.
     */
    public function customer()
    {
        return $this->belongsTo(BaseCustomer::class, 'customer_id');
    }

    /**
     * Get the company that owns the address.
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    /**
     * Scope to get default addresses.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get addresses by type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get addresses by country.
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Scope to search addresses by various fields.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('label', 'ILIKE', "%{$search}%")
                ->orWhere('line1', 'ILIKE', "%{$search}%")
                ->orWhere('line2', 'ILIKE', "%{$search}%")
                ->orWhere('city', 'ILIKE', "%{$search}%")
                ->orWhere('state', 'ILIKE', "%{$search}%")
                ->orWhere('postal_code', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Get the full formatted address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->line1,
            $this->line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the country name attribute.
     */
    public function getCountryNameAttribute(): string
    {
        // Could integrate with a country name library
        return strtoupper($this->country);
    }

    /**
     * Set as default address for the type.
     * This will unset other default addresses for the same type.
     */
    public function setAsDefault(): void
    {
        static::where('customer_id', $this->customer_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get the default address for a specific type.
     */
    public static function getDefaultForType(BaseCustomer $customer, string $type): ?self
    {
        return static::where('customer_id', $customer->id)
            ->where('type', $type)
            ->default()
            ->first();
    }

    /**
     * Get all addresses for a customer grouped by type.
     */
    public static function getGroupedByType(BaseCustomer $customer): array
    {
        return static::where('customer_id', $customer->id)
            ->get()
            ->groupBy('type')
            ->toArray();
    }

    /**
     * Check if the address is valid based on country-specific rules.
     */
    public function isValid(): bool
    {
        // Basic validation
        if (empty($this->line1) || empty($this->country)) {
            return false;
        }

        // Country-specific validation could be added here
        switch (strtoupper($this->country)) {
            case 'US':
                return ! empty($this->city) && ! empty($this->state) && ! empty($this->postal_code);
            case 'CA':
                return ! empty($this->city) && ! empty($this->postal_code);
            default:
                return true;
        }
    }

    /**
     * Format address for mailing labels.
     */
    public function formatForMailing(): array
    {
        $lines = [];

        if ($this->line1) {
            $lines[] = $this->line1;
        }

        if ($this->line2) {
            $lines[] = $this->line2;
        }

        $cityLine = array_filter([$this->city, $this->state, $this->postal_code]);
        if (! empty($cityLine)) {
            $lines[] = implode(' ', $cityLine);
        }

        if ($this->country && strtoupper($this->country) !== 'US') {
            $lines[] = $this->country_name;
        }

        return $lines;
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($address) {
            // Set company context from customer if not provided
            if (! $address->company_id && $address->customer_id) {
                $address->company_id = BaseCustomer::find($address->customer_id)?->company_id;
            }
        });

        static::updating(function ($address) {
            // If setting as default, unset others
            if ($address->is_default && $address->wasChanged('is_default')) {
                static::where('customer_id', $address->customer_id)
                    ->where('type', $address->type)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
