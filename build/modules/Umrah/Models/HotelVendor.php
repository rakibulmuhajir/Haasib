<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelVendor extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.hotel_vendors';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['company_id', 'vendor_number', 'name', 'phone', 'email', 'city', 'logo_url', 'notes', 'total_cost', 'total_paid', 'balance', 'is_active'];

    protected $casts = ['company_id' => 'string', 'total_cost' => 'decimal:2', 'total_paid' => 'decimal:2', 'balance' => 'decimal:2', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }
}
