<?php

// app/Http/Middleware/TransactionPerRequest.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class TransactionPerRequest
{
    public function handle($request, Closure $next)
    {
        // If a transaction is already open (e.g. tests) don't nest.
        if (DB::transactionLevel() > 0 || app()->runningUnitTests()) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next) {
            return $next($request);
        });
    }
}

