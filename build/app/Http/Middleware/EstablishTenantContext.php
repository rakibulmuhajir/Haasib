<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Start every request with a clean, honest tenant context.
 *
 * The GUCs this sets are session-scoped, not transaction-scoped: set_config(..., false)
 * survives on a pooled or persistent connection after the request that set it has ended.
 * So a request that touches a company-scoped table before anything establishes context
 * does not read "no tenant" — it reads *the previous request's* tenant, whoever that was.
 *
 * That is fail-open, and it is the dangerous direction. Two things followed from it:
 *
 *  - HandleInertiaRequests resolved the company list eagerly, and Inertia runs share()
 *    before $next($request). On a fresh connection the GUCs were unset, row level security
 *    matched nothing, and the switcher and whole sidebar came back empty. On a reused
 *    connection it could instead have listed whichever companies the PREVIOUS user could
 *    see. Deferring that resolution fixed the symptom; it did not remove the window.
 *
 *  - TenantContextGuard cannot catch this. Its own documentation is explicit: it answers
 *    "is there a tenant context", not "is it the correct one". A stale context is present
 *    and wrong, which is exactly what it cannot see.
 *
 * Running first closes the window for everything at once, rather than auditing every query
 * that might run early and re-auditing on every future change:
 *
 *  - the reset means a too-early read now returns nothing instead of someone else's rows,
 *    turning a silent leak into a visible blank;
 *  - setting the user id straight away means nothing downstream is starved of context the
 *    way share() was, so the blank does not happen in the first place.
 *
 * Company resolution stays in IdentifyCompany, which still proves membership before
 * setting app.current_company_id. This middleware deliberately never sets a company.
 */
class EstablishTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never inherit. The previous request on this connection may have been another
        // user entirely, and its GUCs are still sitting there.
        DB::statement('RESET app.current_company_id');
        DB::statement('RESET app.company_base_currency');
        DB::statement('RESET app.current_user_id');
        DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

        $user = $request->user();

        if ($user) {
            DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

            if (method_exists($user, 'isGodMode') && $user->isGodMode()) {
                DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
            }
        }

        return $next($request);
    }
}
