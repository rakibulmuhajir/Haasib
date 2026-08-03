<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use App\Facades\CompanyContext;
use App\Http\Requests\StoreCompanyUserRequest;
use App\Services\CompanyContextService;
use App\Services\UserPermissionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsersPageController extends Controller
{
    public function store(
        StoreCompanyUserRequest $request,
        CreateNewUser $createNewUser,
        CompanyContextService $companyContext,
    ) {
        $company = CompanyContext::getCompany();
        $data = $request->validated();

        DB::transaction(function () use ($company, $companyContext, $createNewUser, $data, $request): void {
            $user = $createNewUser->create($data);

            DB::table('auth.company_user')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => $data['role'],
                'invited_by_user_id' => $request->user()->id,
                'joined_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $companyContext->assignRole($user, $data['role']);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function index(Request $request, UserPermissionPresenter $permissionPresenter): Response
    {
        $company = CompanyContext::getCompany();

        // Get current user's role first
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        // If user is not a member, they shouldn't be here
        if (! $currentUserRole) {
            abort(403, 'You are not a member of this company.');
        }

        $permissionsByRole = DB::table('roles as r')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('r.company_id', $company->id)
            ->where('r.guard_name', 'web')
            ->orderBy('p.name')
            ->get(['r.name as role', 'p.name as permission'])
            ->groupBy('role')
            ->map(fn ($permissions) => $permissions->pluck('permission')->values()->all());

        $users = DB::table('auth.company_user as cu')
            ->join('auth.users as u', 'cu.user_id', '=', 'u.id')
            ->where('cu.company_id', $company->id)
            ->where('cu.is_active', true)
            ->select(
                'u.id',
                'u.name',
                'u.email',
                'cu.role',
                'cu.is_active',
                'cu.joined_at'
            )
            ->orderBy('u.name')
            ->get()
            ->map(function ($companyUser) use ($company, $permissionPresenter, $permissionsByRole) {
                $display = $permissionPresenter->forCompany(
                    $company,
                    $companyUser->role,
                    $permissionsByRole->get($companyUser->role, []),
                );
                $companyUser->permissions = $display['permissions'];
                $companyUser->capabilities = $display['capabilities'];

                return $companyUser;
            });

        return Inertia::render('users/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'users' => $users,
            'currentUserRole' => $currentUserRole,
        ]);
    }

    public function updateRole(Request $request, string $companySlug, string $userId)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:manager,accountant,operations'],
        ]);

        $company = CompanyContext::getCompany();

        // Get current user's role
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'manager'], true)) {
            abort(403, 'Only owners and managers can change user roles.');
        }

        // Get target user's current role
        $targetUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->value('role');

        if (! $targetUserRole) {
            abort(404, 'User not found in this company.');
        }

        // Owners cannot be changed by anyone
        if ($targetUserRole === 'owner') {
            abort(403, 'The owner role cannot be changed.');
        }

        // Managers may manage peers and subordinate roles, but never an owner.
        if ($currentUserRole === 'manager' && $targetUserRole === 'owner') {
            abort(403, 'Managers cannot change an owner.');
        }

        // Update the role
        DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->update([
                'role' => $data['role'],
                'updated_at' => now(),
            ]);

        return back()->with('success', 'User role updated successfully.');
    }

    public function remove(Request $request, string $companySlug, string $userId)
    {
        $company = CompanyContext::getCompany();

        // Check if current user is owner or manager
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'manager'], true)) {
            abort(403, 'Only owners and managers can remove users.');
        }

        // Don't allow removing yourself
        if ($userId === Auth::id()) {
            abort(403, 'You cannot remove yourself from the company.');
        }

        // Get target user's role
        $targetUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->value('role');

        // Ownership is protected. Transfer ownership explicitly before removal.
        if ($targetUserRole === 'owner') {
            abort(403, 'The owner cannot be removed. Transfer ownership first.');
        }

        DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('success', 'User removed from company successfully.');
    }
}
