<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'auth.companies';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'country',
        'currency',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'auth.company_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}