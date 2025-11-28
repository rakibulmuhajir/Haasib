<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'auth.users';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'auth.company_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function newUniqueId(): string
    {
        if (isset($this->attributes['id'])) {
            return $this->attributes['id'];
        }
        
        return (string) \Illuminate\Support\Str::uuid();
    }

    public function isGodMode(): bool
    {
        return str_starts_with($this->id, '00000000-0000-0000-0000-');
    }

    public function isSuperAdmin(): bool
    {
        return $this->id === '00000000-0000-0000-0000-000000000000';
    }

    public function isSystemAdmin(): bool
    {
        return $this->isGodMode() && !$this->isSuperAdmin();
    }

    public function getSystemAdminNumber(): ?int
    {
        if (!$this->isSystemAdmin()) {
            return null;
        }
        return (int) substr($this->id, -18);
    }

    public static function createSystemAdmin(string $name, string $email, ?string $password = null): self
    {
        $lastSystemAdmin = self::where('id', 'LIKE', '00000000-0000-0000-0000-%')
            ->where('id', '!=', '00000000-0000-0000-0000-000000000000')
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = $lastSystemAdmin 
            ? ((int) substr($lastSystemAdmin->id, -18)) + 1 
            : 1;
        
        $userId = sprintf('00000000-0000-0000-0000-%018d', $nextNumber);
        
        $user = new self();
        $user->id = $userId;
        $user->name = $name;
        $user->username = strtolower(str_replace(' ', '_', $name));
        $user->email = $email;
        $user->password = $password ?? \Illuminate\Support\Str::random(32);
        $user->save();
        
        return $user;
    }
}
