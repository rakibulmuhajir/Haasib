<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurisdiction extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'tax.jurisdictions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'parent_id',
        'country_code',
        'code',
        'name',
        'level',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'string',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function taxRates()
    {
        return $this->hasMany(TaxRate::class, 'jurisdiction_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getSaudiArabia(): self
    {
        return static::where('code', 'SA')->firstOrFail();
    }
}
