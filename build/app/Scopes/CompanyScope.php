<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope to automatically restrict queries to the authenticated user's company.
 */
class CompanyScope implements Scope
{
    /**
     * Apply the scope to the given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            return;
        }

        $builder->where($model->getTable().'.company_id', $companyId);
    }

    /**
     * Resolve the current company id from the auth context (or queued override).
     */
    protected function resolveCompanyId(): ?string
    {
        $user = Auth::user();

        if ($user && $user->current_company_id) {
            return (string) $user->current_company_id;
        }

        if (app()->bound('company.id')) {
            return (string) app('company.id');
        }

        // Check RLS session variable as fallback
        try {
            $result = \Illuminate\Support\Facades\DB::selectOne("SELECT current_setting('app.current_company_id', true) as company_id");
            if ($result && $result->company_id) {
                return (string) $result->company_id;
            }
        } catch (\Exception $e) {
            // Ignore if setting doesn't exist or can't be accessed
        }

        return null;
    }
}
