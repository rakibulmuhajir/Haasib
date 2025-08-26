<?php
// app/Http/Middleware/EnsureDevConsoleEnabled.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDevConsoleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.dev_console_enabled')) {
            abort(403, 'Dev console disabled');
        }
        return $next($request);
    }
}
