<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckFirstTimeUser
{
    /**
     * Handle an incoming request.
     *
     * Check if user has any companies. If not, redirect to welcome page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not authenticated
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip for god-mode users
        if (str_starts_with($user->id, '00000000-0000-0000-0000-')) {
            return $next($request);
        }

        // Skip if already on welcome, company creation, or onboarding pages
        if ($request->is('welcome') ||
            $request->is('companies') ||
            $request->is('companies/create') ||
            $request->is('*/onboarding') ||
            $request->is('*/onboarding/*')) {
            return $next($request);
        }

        // Check if user has any company memberships
        $hasCompanies = DB::table('auth.company_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        // If no companies, redirect to welcome page
        if (! $hasCompanies && ! $request->is('welcome')) {
            return redirect('/welcome');
        }

        return $next($request);
    }
}
