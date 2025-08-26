<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'auth.companies';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name','slug','base_currency','language','locale','settings'];
    protected $casts = ['settings' => 'array'];

    public function users()
{
    return $this->belongsToMany(\App\Models\User::class, 'auth.company_user')
        ->withPivot('role')
        ->withTimestamps();
}

}
