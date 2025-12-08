<?php

namespace App\Modules\Banking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'bank.banks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'swift_code',
        'country_code',
        'logo_url',
        'website',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function companyBankAccounts()
    {
        return $this->hasMany(CompanyBankAccount::class);
    }
}