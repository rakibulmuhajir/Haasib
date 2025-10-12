<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyPermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyRole
{
    public function __construct(
        private CompanyPermissionService $permissionService
    ) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $this->unauthorizedResponse($request, 'Authentication required');
        }

        $company = $request->attributes->get('company');

        if (! $company) {
            return $this->unauthorizedResponse($request, 'Company context required');
        }

        // Get user's role in this company
        $userRole = $this->permissionService->getUserRoleInCompany($user, $company);

        if (! $userRole) {
            return $this->unauthorizedResponse($request, 'No access to this company');
        }

        // Check if user has required role or higher
        if (! $this->hasRequiredRole($userRole, $role)) {
            return $this->unauthorizedResponse($request, "Role '{$role}' required in company '{$company->name}'");
        }

        return $next($request);
    }

    private function hasRequiredRole(string $userRole, string $requiredRole): bool
    {
        // Role hierarchy: owner > admin > accountant > manager > employee > viewer
        $roleHierarchy = [
            'viewer' => 0,
            'employee' => 1,
            'manager' => 2,
            'accountant' => 3,
            'admin' => 4,
            'owner' => 5,
        ];

        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;

        return $userLevel >= $requiredLevel;
    }

    private function unauthorizedResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'forbidden' => true,
                'code' => 'INSUFFICIENT_ROLE',
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
