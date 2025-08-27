<?php
// app/Http/Middleware/RequireSuperadmin.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperadmin {
    public function handle(Request $request, Closure $next): Response {
        abort_unless(optional($request->user())->isSuperAdmin(), 403);
        return $next($request);
    }
}

