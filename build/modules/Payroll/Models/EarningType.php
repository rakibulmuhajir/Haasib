<?php

namespace Modules\Payroll\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EarningType extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'pay.earning_types';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_taxable',
        'affects_overtime',
        'is_recurring',
        'gl_account_id',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'is_taxable' => 'boolean',
        'affects_overtime' => 'boolean',
        'is_recurring' => 'boolean',
        'gl_account_id' => 'string',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payslipLines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }
}
