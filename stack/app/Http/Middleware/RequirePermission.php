<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyPermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(
        private CompanyPermissionService $permissionService
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $this->unauthorizedResponse($request, 'Authentication required');
        }

        // Get the current company from request context
        $company = $request->attributes->get('company');

        // Check company-scoped permission if company context exists
        if ($company) {
            if (! $this->permissionService->userHasCompanyPermission($user, $company, $permission)) {
                return $this->unauthorizedResponse($request, "Insufficient permissions: {$permission} required for company '{$company->name}'");
            }
        } else {
            // Fall back to global permission check
            if (! $user->hasPermissionTo($permission)) {
                return $this->unauthorizedResponse($request, "Insufficient permissions: {$permission} required");
            }
        }

        return $next($request);
    }

    private function unauthorizedResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'forbidden' => true,
                'code' => 'INSUFFICIENT_PERMISSIONS',
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
