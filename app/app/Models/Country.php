<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $fillable = [
        'code',
        'alpha3',
        'name',
        'native_name',
        'region',
        'subregion',
        'emoji',
        'capital',
        'calling_code',
        'eea_member',
    ];

    protected $casts = [
        'eea_member' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * Get the customers for the country.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
