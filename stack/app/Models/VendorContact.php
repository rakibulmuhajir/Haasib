<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorContact extends Model
{
    use HasFactory;

    protected $table = 'acct.vendor_contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'vendor_id',
        'contact_type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'is_primary',
    ];

    protected $casts = [
        'id' => 'string',
        'vendor_id' => 'string',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
