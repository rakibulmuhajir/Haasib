<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasCompany
{
    /**
     * Handle an incoming request.
     * Redirects users without any company to company creation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin can access without company
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $hasCompanies = $user->companies()
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasCompanies) {
            // For API requests, return JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You need to create or join a company first.',
                    'redirect' => route('companies.create'),
                ], 403);
            }

            return redirect()->route('companies.create')
                ->with('message', 'Please create or join a company to continue.');
        }

        return $next($request);
    }
}
