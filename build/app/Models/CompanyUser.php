<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CompanyUser extends Pivot
{
    use HasFactory;

    /**
     * UUID primary key – disable incrementing.
     */
    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'auth.company_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'role',
        'joined_at',
        'invited_by_user_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'is_active' => 'boolean',
            'invited_by_user_id' => 'string',
        ];
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the company that owns the pivot.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user that owns the pivot.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function activate(): void
    {
        if (! $this->is_active) {
            $this->is_active = true;
            $this->left_at = null;
            $this->save();
        }
    }

    public function deactivate(): void
    {
        if ($this->is_active) {
            $this->is_active = false;
            $this->left_at = now();
            $this->save();
        }
    }

    public function changeRole(string $role): void
    {
        $this->role = $role;
        $this->save();
    }

    /**
     * Check if the user has admin role in the company.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'super_admin']);
    }

    /**
     * Check if the user is the company owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if the user can perform a specific action based on their role.
     */
    public function can(string $action): bool
    {
        $rolePermissions = [
            'owner' => ['*'],
            'admin' => ['manage_users', 'manage_settings', 'view_reports', 'manage_modules', 'manage_data'],
            'manager' => ['view_reports', 'manage_team', 'manage_data'],
            'accountant' => ['manage_entries', 'view_reports', 'reconcile'],
            'clerk' => ['create_entries', 'view_reports'],
            'viewer' => ['view_reports'],
        ];

        $userPermissions = $rolePermissions[$this->role] ?? [];

        return in_array('*', $userPermissions) || in_array($action, $userPermissions);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\CompanyUserFactory::new();
    }

    protected function setKeysForSaveQuery($query)
    {
        return $query->where('company_id', $this->getAttribute('company_id'))
            ->where('user_id', $this->getAttribute('user_id'));
    }
}
