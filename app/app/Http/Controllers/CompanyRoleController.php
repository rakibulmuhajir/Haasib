<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CompanyRoleController extends AuthenticatedController
{
    /**
     * Helper to execute callback with team context
     */
    private function withTeamContext($teamId, callable $callback)
    {
        $previousTeamId = getPermissionsTeamId();
        try {
            setPermissionsTeamId($teamId);

            return $callback();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Display role management page
     */
    public function index(Request $request, Company $company)
    {
        $this->requireCompanyPermission($request, $company, 'users.roles.assign');

        // Get all active company users once
        $activeUsers = $company->users()
            ->where('users.is_active', true)
            ->get();

        // Get all available company roles with correct user counts
        $availableRoles = $this->withTeamContext($company->id, function () use ($activeUsers) {
            return collect(['owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'])
                ->map(function ($roleName) use ($activeUsers) {
                    // Count users with this role using Spatie's hasRole() method
                    $userCount = $activeUsers->filter(function ($user) use ($roleName) {
                        return $user->hasRole($roleName);
                    })->count();

                    return [
                        'name' => $roleName,
                        'display_name' => $this->getRoleDisplayName($roleName),
                        'user_count' => $userCount,
                    ];
                });
        });

        // Get all company users with their roles (with proper team context)
        $users = $this->withTeamContext($company->id, function () use ($activeUsers) {
            return $activeUsers->map(function ($user) {
                $roleName = $user->getRoleNames()->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $roleName ?? 'No role',
                    'role_display' => $this->getRoleDisplayName($roleName),
                ];
            });
        });

        return Inertia::render('Settings/Roles/Index', [
            'company' => $company,
            'roles' => $availableRoles,
            'users' => $users,
        ]);
    }

    /**
     * Update user role
     */
    public function updateRole(Request $request, Company $company, User $user): JsonResponse
    {
        $this->requireCompanyPermission($request, $company, 'users.roles.assign');

        $validated = $request->validate([
            'role' => 'required|string|in:owner,admin,manager,accountant,employee,viewer',
        ]);

        return $this->withTeamContext($company->id, function () use ($request, $user, $validated) {
            // Prevent user from removing their own owner role
            if ($request->user()->id === $user->id && $validated['role'] !== 'owner') {
                $currentRole = $user->getRoleNames()->first();
                if ($currentRole === 'owner') {
                    return response()->json([
                        'message' => 'You cannot remove your own owner role',
                    ], 403);
                }
            }

            // Prevent user from assigning owner role to themselves (unless they're already an owner)
            if ($request->user()->id === $user->id && $validated['role'] === 'owner') {
                $currentRole = $user->getRoleNames()->first();
                if ($currentRole !== 'owner') {
                    return response()->json([
                        'message' => 'You cannot assign owner role to yourself',
                    ], 403);
                }
            }

            try {
                // Remove existing company roles
                $user->syncRoles([]);

                // Assign new role
                $user->assignRole($validated['role']);

                return response()->json([
                    'message' => 'Role updated successfully',
                    'role' => $validated['role'],
                    'role_display' => $this->getRoleDisplayName($validated['role']),
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to update role: '.$e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Remove user from company
     */
    public function removeUser(Request $request, Company $company, User $user): JsonResponse
    {
        $this->requireCompanyPermission($request, $company, 'users.deactivate');

        // Prevent self-removal
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot remove yourself from the company',
            ], 403);
        }

        // Check for last owner with proper team context
        return $this->withTeamContext($company->id, function () use ($company, $user, $request) {
            // Get all active users and count owners
            $activeUsers = $company->users()
                ->where('users.is_active', true)
                ->get();

            $ownerCount = $activeUsers->filter(function ($u) {
                return $u->hasRole('owner');
            })->count();

            if ($ownerCount <= 1 && $user->hasRole('owner')) {
                return response()->json([
                    'message' => 'Cannot remove the last owner from the company',
                ], 403);
            }

            // Deactivate user in company
            $user->companies()->updateExistingPivot($company->id, [
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $request->user()->id,
            ]);

            // Remove all company roles
            try {
                $user->syncRoles([]);
            } catch (\Exception $e) {
                // Log error but continue
                \Log::error('Failed to remove roles during user removal: '.$e->getMessage());
            }

            return response()->json([
                'message' => 'User removed from company successfully',
            ]);
        });
    }

    /**
     * Get role display name
     */
    private function getRoleDisplayName(?string $role): string
    {
        $displayNames = [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'accountant' => 'Accountant',
            'employee' => 'Employee',
            'viewer' => 'Viewer',
        ];

        return $displayNames[$role] ?? 'No Role';
    }
}
