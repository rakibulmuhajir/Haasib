<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasUuids, SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'umrah.hotels';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['company_id', 'hotel_vendor_id', 'name', 'city', 'notes', 'is_active'];
    protected $casts = ['company_id' => 'string', 'hotel_vendor_id' => 'string', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(HotelVendor::class, 'hotel_vendor_id')->withTrashed(); }
    public function roomRates(): HasMany { return $this->hasMany(HotelRoomRate::class); }
}
