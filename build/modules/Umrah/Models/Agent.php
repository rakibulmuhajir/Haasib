<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Umrah\Services\AgentParty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const COUNTRIES = [
        'Pakistan' => 'Pakistan',
        'Bangladesh' => 'Bangladesh',
        'India' => 'India',
        'Turkiye' => 'Turkiye',
        'United Kingdom' => 'United Kingdom',
        'United States' => 'United States',
    ];

    protected $connection = 'pgsql';

    protected $table = 'umrah.agents';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * name, phone, email and logo_url are still accepted here, but they are no
     * longer stored here. They are columns on acct.customers, and the mutators
     * below divert them to the customer this agent extends. Keeping them
     * fillable means every existing caller -- controllers, seeders, the
     * twenty-odd tests that say Agent::create(['name' => ...]) -- keeps working
     * and gets the party record it always meant to create.
     *
     * city and country ARE stored here, and are not a duplicate of the
     * customer's address: a customer's billing_address is a jsonb blob keyed by
     * ISO-2 code, an agent's country is one of the six display names in
     * COUNTRIES. Folding one into the other would change what the value means.
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'user_id',
        'agent_number',
        'name',
        'phone',
        'email',
        'logo_url',
        'city',
        'country',
        'notes',
        'can_create_voucher',
        'can_approve_voucher',
        'can_edit_group',
        'can_edit_voucher',
        'voucher_cutoff_hours',
        'total_receivable',
        'total_paid',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'user_id' => 'string',
        'total_receivable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'can_create_voucher' => 'boolean',
        'can_approve_voucher' => 'boolean',
        'can_edit_group' => 'boolean',
        'can_edit_voucher' => 'boolean',
        'voucher_cutoff_hours' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Always loaded, because four of this model's attributes live on it. Without
     * it every serialized agent would lazy-load its customer one row at a time.
     * Any query that narrows the selected columns must keep customer_id, or the
     * relation has no key to load from and the four fields come back null.
     */
    protected $with = ['customer'];

    /** The party fields, served under the names callers already use. */
    protected $appends = ['name', 'email', 'phone', 'logo_url'];

    /**
     * Loaded but not serialized. The four appended fields are what a page wants
     * from the customer; shipping the whole record alongside would put a
     * counterparty's credit limit and notes into every agent picker.
     */
    protected $hidden = ['customer'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The acct.customer this agent extends. Not nullable and not optional: the
     * column is NOT NULL and UNIQUE, so exactly one customer is exactly one
     * agent. One statement covers everything the agent owes -- tickets, Umrah
     * packages, anything else -- because there is only one party to owe it.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Party fields handed to create() or update() before they reach the
     * customer. They never become columns on this table -- the mutators below
     * park them here, and the model events route them to acct.customers.
     *
     * @var array<string, mixed>
     */
    protected array $partyAttributes = [];

    protected static function booted(): void
    {
        /*
         * An agent cannot exist without its party, so if one was not supplied,
         * the name given to create() builds it. This is what makes the
         * invariant hold everywhere rather than only where a controller
         * remembered to call AgentParty first: a seeder, a test, a future
         * command that says Agent::create(['name' => 'X']) gets a real customer
         * out of it, because customer_id is NOT NULL and there is nothing else
         * it could mean.
         */
        static::creating(function (self $agent) {
            if ($agent->customer_id || $agent->partyAttributes === []) {
                return;
            }

            $company = Company::findOrFail($agent->company_id);

            $agent->customer_id = app(AgentParty::class)
                ->createFor($company, $agent->partyAttributes)
                ->id;
        });

    }

    /**
     * On update the customer already exists, so the edited party fields go
     * straight to it.
     *
     * This is a save() override rather than an updating hook because the hook
     * would never run: the party fields are not attributes of this model, so an
     * edit that touches only the name leaves the agent clean, and Eloquent
     * skips a clean update without firing anything. Cleared after writing so a
     * second save does not repeat it.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists && $this->partyAttributes !== []) {
            app(AgentParty::class)->updateFrom($this, $this->partyAttributes);
            $this->partyAttributes = [];
        }

        return parent::save($options);
    }

    /*
     * The four party fields divert on write and read back through the customer,
     * under the names callers already use. An agent has no name of its own; it
     * has the name of the party it extends.
     */

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
        return $this->customer?->name;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->customer?->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->customer?->phone;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->customer?->logo_url;
    }

    /**
     * Order by the extended customer's name without a manual join, for the
     * registers and pickers that used to say orderBy('name').
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy(
            Customer::select('name')->whereColumn('acct.customers.id', 'umrah.agents.customer_id'),
            $direction,
        );
    }

    /**
     * Search by the extended customer's name, replacing where('name', 'ilike').
     */
    public function scopeWhereNameLike(Builder $query, string $term): Builder
    {
        return $query->whereHas('customer', fn (Builder $q) => $q->where('name', 'ilike', "%{$term}%"));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GroupPayment::class);
    }
}
