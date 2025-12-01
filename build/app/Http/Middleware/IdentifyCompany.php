<?php

namespace App\Http\Middleware;

use App\Facades\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        CompanyContext::clearContext();

        $user = $request->user();

        if ($user) {
            DB::select("SELECT set_config('app.current_user_id', ?, true)", [$user->id]);
            DB::select("SELECT set_config('app.is_super_admin', ?, true)", [
                str_starts_with($user->id, '00000000-0000-0000-0000-') ? 'true' : 'false',
            ]);
        } else {
            DB::select("SELECT set_config('app.current_user_id', NULL, true)");
            DB::select("SELECT set_config('app.is_super_admin', 'false', true)");
        }

        $slug = $request->route('company') ?? $request->header('X-Company-Slug');

        if ($slug) {
            try {
                CompanyContext::setContextBySlug($slug);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'code' => 'INVALID_COMPANY',
                        'message' => "Company not found: {$slug}",
                    ], 404);
                }

                abort(404, "Company not found: {$slug}");
            }
        }

        return $next($request);
    }
}
