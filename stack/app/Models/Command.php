<?php

namespace App\Models;

use App\Models\Scopes\CommandPerformanceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Command extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'category',
        'parameters',
        'required_permissions',
        'execution_handler',
        'is_active',
    ];

    protected $casts = [
        'parameters' => 'array',
        'required_permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CommandPerformanceScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function commandTemplates(): HasMany
    {
        return $this->hasMany(CommandTemplate::class);
    }

    public function commandExecutions(): HasMany
    {
        return $this->hasMany(CommandExecution::class);
    }

    public function commandHistory(): HasMany
    {
        return $this->hasMany(CommandHistory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function userHasPermission(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyPermission($this->required_permissions);
    }
}
