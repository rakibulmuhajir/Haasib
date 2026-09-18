<?php

namespace App\Http\Middleware;

use App\Facades\CompanyContext;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        CompanyContext::clearContext();
        app(CurrentCompany::class)->clear();

        $user = $request->user();

        if ($user) {
            DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
            DB::select("SELECT set_config('app.is_super_admin', ?, false)", [
                $user->isGodMode() ? 'true' : 'false',
            ]);
        } else {
            DB::statement("RESET app.current_user_id");
            DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
        }

        $slug = $request->route('company') ?? $request->header('X-Company-Slug');

        if ($slug) {
            try {
                CompanyContext::setContextBySlug($slug);
                if ($company = CompanyContext::getCompany()) {
                    app(CurrentCompany::class)->set($company);

                    // A company whose bootstrap failed partway (see CompanyBootstrapService)
                    // has no chart of accounts -- do not let it be used as though it were
                    // ready. Allow only the repair action itself, the accounts page it
                    // lives on, and the escape hatches every gate needs (logout, settings).
                    if ($company->bootstrap_incomplete_at !== null
                        && ! $request->routeIs(
                            'accounts.restore-missing',
                            'accounts.index',
                            'logout',
                            'company.settings',
                        )
                        && ! $request->is('*/accounts')
                        && ! $request->is('*/accounts/restore-missing')
                    ) {
                        return redirect("/{$slug}/accounts")
                            ->with('error', 'Company setup is incomplete: restore the missing standard accounts before continuing.');
                    }
                }
                // Remember this as the last accessed company
                if ($user) {
                    session(['last_company_slug' => $slug]);
                }
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
