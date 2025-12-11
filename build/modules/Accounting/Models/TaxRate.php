<?php

namespace App\Modules\Accounting\Models;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.tax_rates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'jurisdiction_id',
        'code',
        'name',
        'rate',
        'tax_type',
        'is_compound',
        'compound_priority',
        'gl_account_id',
        'recoverable_account_id',
        'effective_from',
        'effective_to',
        'is_default',
        'is_active',
        'description',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'jurisdiction_id' => 'string',
        'rate' => 'decimal:4',
        'is_compound' => 'boolean',
        'compound_priority' => 'integer',
        'gl_account_id' => 'string',
        'recoverable_account_id' => 'string',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jurisdiction()
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function recoverableAccount()
    {
        return $this->belongsTo(Account::class, 'recoverable_account_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function taxGroupComponents()
    {
        return $this->hasMany(TaxGroupComponent::class);
    }

    public function taxGroups()
    {
        return $this->belongsToMany(TaxGroup::class, 'acct.tax_group_components')
            ->withPivot('priority')
            ->orderByPivot('priority');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOfType($query, $taxType)
    {
        return $query->where('tax_type', $taxType);
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_to')
                ->orWhere('effective_to', '>', $date);
        })->where('effective_from', '<=', $date);
    }

    public static function createSaudiVAT(Company $company, float $rate = 15.00): self
    {
        $jurisdiction = Jurisdiction::getSaudiArabia();

        return static::create([
            'company_id' => $company->id,
            'jurisdiction_id' => $jurisdiction->id,
            'code' => 'VAT-SA',
            'name' => "Saudi VAT {$rate}%",
            'rate' => $rate,
            'tax_type' => 'both',
            'is_compound' => false,
            'compound_priority' => 0,
            'effective_from' => now(),
            'is_default' => true,
            'is_active' => true,
            'description' => 'Saudi Arabia Value Added Tax',
            'created_by_user_id' => auth()->id(),
        ]);
    }
}
