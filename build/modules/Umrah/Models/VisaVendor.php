<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Umrah\Services\VisaVendorParty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaVendor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.visa_vendors';

    protected $keyType = 'string';

    public $incrementing = false;

    public const TYPE_GOVERNMENT = 'government';

    public const TYPE_VISA_PROVIDER = 'visa_provider';

    public const TYPE_TRANSPORT_PROVIDER = 'transport_provider';

    public const TYPE_HOTEL = 'hotel';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_GOVERNMENT => 'Government',
        self::TYPE_VISA_PROVIDER => 'Visa provider',
        self::TYPE_TRANSPORT_PROVIDER => 'Transport provider',
        self::TYPE_HOTEL => 'Hotel',
        self::TYPE_OTHER => 'Other',
    ];

    /**
     * name, phone, email and logo_url are still accepted here, but they are
     * no longer stored here. They are columns on acct.vendors, and the
     * mutators below divert them to the vendor this row extends, so every
     * existing caller keeps working and gets the supplier it always meant to
     * create.
     */
    protected $fillable = [
        'company_id',
        'vendor_id',
        'vendor_number',
        'name',
        'vendor_type',
        'is_company_owned',
        'is_default',
        'provides_mandatory_transport',
        'mandatory_transport_vendor_id',
        'phone',
        'email',
        'city',
        'logo_url',
        'notes',
        'adult_retail_amount',
        'adult_cost_amount',
        'child_retail_amount',
        'child_cost_amount',
        'included_bus_cost_amount',
        'standard_bus_retail_amount',
        'standard_bus_cost_amount',
        'charge_child_fare',
        'total_cost',
        'total_paid',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_id' => 'string',
        'is_company_owned' => 'boolean',
        'is_default' => 'boolean',
        'provides_mandatory_transport' => 'boolean',
        'mandatory_transport_vendor_id' => 'string',
        'adult_retail_amount' => 'decimal:2',
        'adult_cost_amount' => 'decimal:2',
        'child_retail_amount' => 'decimal:2',
        'child_cost_amount' => 'decimal:2',
        'included_bus_cost_amount' => 'decimal:2',
        'standard_bus_retail_amount' => 'decimal:2',
        'standard_bus_cost_amount' => 'decimal:2',
        'charge_child_fare' => 'boolean',
        'total_cost' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Always loaded, because four of this model's attributes live on it. Any
     * query that narrows the selected columns must keep vendor_id, or the
     * relation has no key to load from and the four fields come back null.
     */
    protected $with = ['vendor'];

    /** The supplier's details, served under the names callers already use. */
    protected $appends = ['name', 'email', 'phone', 'logo_url'];

    /**
     * Loaded but not serialized. The four appended fields are what a page wants
     * from the supplier; shipping the whole record alongside would put its
     * payment terms and notes into every vendor picker.
     */
    protected $hidden = ['vendor'];

    /**
     * Supplier details handed to create() or update() before they reach the
     * vendor. They never become columns on this table.
     *
     * @var array<string, mixed>
     */
    protected array $partyAttributes = [];

    protected static function booted(): void
    {
        /*
         * An umrah vendor cannot exist without the supplier it extends, so if
         * one was not supplied, the name given to create() builds it. This is
         * what makes the invariant hold in a seeder or a test, not only where a
         * controller remembered to call VisaVendorParty first.
         */
        static::creating(function (self $umrahVendor) {
            if ($umrahVendor->vendor_id || $umrahVendor->partyAttributes === []) {
                return;
            }

            $company = Company::findOrFail($umrahVendor->company_id);

            $umrahVendor->vendor_id = app(VisaVendorParty::class)
                ->createFor($company, $umrahVendor->partyAttributes)
                ->id;
        });
    }

    /**
     * On update the vendor already exists, so the edited details go straight to
     * it.
     *
     * A save() override rather than an updating hook, because the hook would
     * never run: these are not attributes of this model, so an edit touching
     * only the name leaves the row clean and Eloquent skips a clean update
     * without firing anything.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists && $this->partyAttributes !== []) {
            app(VisaVendorParty::class)->updateFrom($this, $this->partyAttributes);
            $this->partyAttributes = [];
        }

        return parent::save($options);
    }

    /**
     * The acct.vendor this row extends. Not nullable and not optional: the
     * column is NOT NULL and UNIQUE, so one supplier is one umrah vendor and
     * one balance covers everything it is owed.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->partyAttributes['name'] = $value;
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->partyAttributes['email'] = $value;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->partyAttributes['phone'] = $value;
    }

    public function setLogoUrlAttribute(?string $value): void
    {
        $this->partyAttributes['logo_url'] = $value;
    }

    public function getNameAttribute(): ?string
    {
        return $this->vendor?->name;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->vendor?->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->vendor?->phone;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->vendor?->logo_url;
    }

    /**
     * Order by the extended supplier's name without a manual join, for the
     * registers and pickers that used to say orderBy('name').
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy(
            Vendor::select('name')->whereColumn('acct.vendors.id', 'umrah.visa_vendors.vendor_id'),
            $direction,
        );
    }

    /**
     * Search by the extended supplier's name, replacing where('name', 'ilike').
     */
    public function scopeWhereNameLike(Builder $query, string $term): Builder
    {
        return $query->whereHas('vendor', fn (Builder $q) => $q->where('name', 'ilike', "%{$term}%"));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWithCompleteVisaRates(Builder $query): Builder
    {
        return $query
            ->where('adult_retail_amount', '>', 0)
            ->where('adult_cost_amount', '>', 0)
            ->where('child_retail_amount', '>', 0)
            ->where('child_cost_amount', '>', 0);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class, 'vendor_id');
    }

    public function mandatoryTransportVendor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mandatory_transport_vendor_id');
    }

    public function resolvedMandatoryTransportVendorId(): ?string
    {
        return $this->provides_mandatory_transport ? $this->id : $this->mandatory_transport_vendor_id;
    }
}
