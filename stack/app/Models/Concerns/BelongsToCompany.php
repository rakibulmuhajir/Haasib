<?php

namespace App\Models\Concerns;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    /**
     * Boot the trait and register the global company scope.
     */
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('company_id')) {
                if ($companyId = static::resolveAuthenticatedCompanyId()) {
                    $model->setAttribute('company_id', $companyId);
                }
            }
        });
    }

    /**
     * Scope helper to query records for a specific company, bypassing the global scope.
     */
    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query
            ->withoutGlobalScope(CompanyScope::class)
            ->where($this->getTable().'.company_id', $companyId);
    }

    /**
     * Allow overriding the global scope for the current request.
     */
    public static function withoutCompanyScope(): Builder
    {
        return static::withoutGlobalScope(CompanyScope::class);
    }

    /**
     * Attempt to resolve the current company id from the authenticated user.
     */
    protected static function resolveAuthenticatedCompanyId(): ?string
    {
        $user = Auth::user();

        if ($user && $user->current_company_id) {
            return (string) $user->current_company_id;
        }

        if (app()->bound('company.id')) {
            return (string) app('company.id');
        }

        return null;
    }
}
