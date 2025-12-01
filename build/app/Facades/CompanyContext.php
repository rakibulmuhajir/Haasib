<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void setContext(\App\Models\Company $company)
 * @method static void setContextBySlug(string $slug)
 * @method static void setContextById(string $id)
 * @method static void clearContext()
 * @method static \App\Models\Company|null getCompany()
 * @method static string|null getCompanyId()
 * @method static \App\Models\Company requireCompany()
 * @method static void assignRole(\App\Models\User $user, string|\App\Models\Role $role)
 * @method static void removeRole(\App\Models\User $user, string|\App\Models\Role $role)
 * @method static void syncRoles(\App\Models\User $user, array $roles)
 * @method static bool userHasPermission(\App\Models\User $user, string $permission)
 * @method static mixed withContext(\App\Models\Company $company, callable $callback)
 *
 * @see \App\Services\CompanyContextService
 */
class CompanyContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\CompanyContextService::class;
    }
}
