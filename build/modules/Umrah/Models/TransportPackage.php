<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportPackage extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.transport_packages';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['company_id', 'name', 'notes', 'is_active'];

    protected $casts = [
        'company_id' => 'string',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(TransportSector::class, 'umrah.transport_package_sectors')
            ->withPivot(['id', 'company_id', 'sort_order'])
            ->wherePivotNull('deleted_at')
            ->orderByPivot('sort_order');
    }
    public function packageSectors(): HasMany { return $this->hasMany(TransportPackageSector::class); }
    public function fares(): HasMany { return $this->hasMany(TransportFare::class); }
}
