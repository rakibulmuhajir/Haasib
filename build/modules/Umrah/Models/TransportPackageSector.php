<?php

namespace App\Modules\Umrah\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportPackageSector extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.transport_package_sectors';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['company_id', 'transport_package_id', 'transport_sector_id', 'sort_order'];

    protected $casts = [
        'company_id' => 'string',
        'transport_package_id' => 'string',
        'transport_sector_id' => 'string',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function package(): BelongsTo { return $this->belongsTo(TransportPackage::class, 'transport_package_id'); }
    public function sector(): BelongsTo { return $this->belongsTo(TransportSector::class, 'transport_sector_id'); }
}
