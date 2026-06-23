<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequireModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $company = app(CurrentCompany::class)->get();

        if (! $company) {
            abort(500, 'Company context required but not set.');
        }

        $user = $request->user();
        $isGodMode = $user && str_starts_with($user->id, '00000000-0000-0000-0000-');

        if (! $isGodMode) {
            $isActiveMember = $user && DB::table('auth.company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();

            if (! $isActiveMember) {
                $message = 'You do not have active access to this company.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'code' => 'COMPANY_ACCESS_DENIED',
                        'message' => $message,
                    ], 403);
                }

                return redirect('/companies')->with('error', $message);
            }
        }

        if ($company->isModuleEnabled($moduleKey)) {
            return $next($request);
        }

        $message = 'This module is not enabled for the selected company.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'code' => 'MODULE_NOT_ENABLED',
                'message' => $message,
            ], 403);
        }

        return redirect("/{$company->slug}")
            ->with('error', $message);
    }
}
