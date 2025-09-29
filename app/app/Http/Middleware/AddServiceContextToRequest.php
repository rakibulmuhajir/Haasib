<?php

namespace App\Http\Middleware;

use App\Support\ServiceContext;
use Closure;
use Illuminate\Http\Request;

class AddServiceContextToRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Add ServiceContext to request attributes for easy access in controllers
        $context = ServiceContext::fromRequest($request);
        $request->attributes->set('service_context', $context);

        return $next($request);
    }
}
