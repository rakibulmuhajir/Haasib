<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCategory extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.customer_categories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['company_id', 'name', 'description'];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'category_id');
    }
}
