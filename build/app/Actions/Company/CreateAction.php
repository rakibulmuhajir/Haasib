<?php

namespace App\Actions\Company;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
            'industry' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|size:2',
            'locale' => 'nullable|string|max:10',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_CREATE;
    }

    public function handle(array $params): array
    {
        $params['currency'] = strtoupper($params['currency']);
        $slug = Str::slug($params['name']);

        return DB::transaction(function () use ($params, $slug) {
            $company = Company::create([
                'name' => $params['name'],
                'industry' => $params['industry'] ?? null,
                'slug' => $slug,
                'country' => $params['country'] ?? null,
                'base_currency' => $params['currency'],
                'language' => $params['language'] ?? 'en',
                'locale' => $params['locale'] ?? 'en_US',
                'created_by_user_id' => Auth::id(),
                'is_active' => true,
            ]);

            CompanyCurrency::create([
                'company_id' => $company->id,
                'currency_code' => $params['currency'],
                'is_base' => true,
                'enabled_at' => now(),
            ]);

            DB::table('auth.company_user')->insert([
                'company_id' => $company->id,
                'user_id' => Auth::id(),
                'role' => 'owner',
                'invited_by_user_id' => Auth::id(),
                'joined_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Ensure company-scoped roles exist and carry permissions, then assign owner to creator
            CompanyContext::withContext($company, function () use ($company) {
                $this->syncRolesForCompany($company);
            });

            // Assign owner role to creator
            CompanyContext::withContext($company, function () {
                CompanyContext::assignRole(Auth::user(), 'owner');
            });

            // Set as active context
            CompanyContext::setContext($company);

            return [
                'message' => "Company created: {$company->name} ({$company->slug})",
                'data' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'currency' => $company->base_currency,
                ],
                'redirect' => "/{$company->slug}/dashboard",
            ];
        });
    }

    private function syncRolesForCompany(Company $company): void
    {
        $matrix = config('role-permissions', []);
        if (empty($matrix)) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);

        foreach ($matrix as $roleName => $permissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);

            $permissionIds = Permission::whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->pluck('id')
                ->filter()
                ->all();

            if (!empty($permissionIds)) {
                // Use direct sync on relation to avoid any stray null/zero IDs
                $role->permissions()->sync($permissionIds);
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
