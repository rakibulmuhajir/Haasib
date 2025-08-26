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


   protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (!$company->slug) {
                $base = Str::slug((string) $company->name) ?: Str::slug(Str::uuid());
                $slug = $base;
                $i = 1;
                while (self::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $company->slug = $slug;
            }
        });
    }

    public function users()
{
    return $this->belongsToMany(\App\Models\User::class, 'auth.company_user')
        ->withPivot('role')
        ->withTimestamps();
}

}
