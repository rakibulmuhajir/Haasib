<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

abstract class AuthenticatedController extends Controller
{
    /**
     * Ensure user has permission for a specific company
     */
    protected function requireCompanyPermission(Request $request, Company $company, string $permission): void
    {
        $user = $request->user();

        // Set team context for permission checking
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($company->id);

        try {
            if (! $user->hasPermissionTo($permission) && ! $user->isSuperAdmin()) {
                abort(403, "You don't have permission to {$permission} for this company");
            }
        } finally {
            // Always restore original team context
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Ensure user has system-level permission
     */
    protected function requireSystemPermission(Request $request, string $permission): void
    {
        $user = $request->user();

        // Clear team context to check system permissions
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            if (! $user->hasPermissionTo($permission)) {
                abort(403, "You don't have system permission: {$permission}");
            }
        } finally {
            // Always restore original team context
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Check if user can access company (view permission)
     */
    protected function canAccessCompany(Request $request, Company $company): bool
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($company->id);

        try {
            return $user->hasPermissionTo('companies.view');
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
