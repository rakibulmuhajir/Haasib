<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('active_company_id')) {
            $user = $request->user();
            
            if (!$user) {
                return redirect()->route('login');
            }

            $preferredCompany = $user->preferredCompany;
            
            if ($preferredCompany) {
                session(['active_company_id' => $preferredCompany->id]);
                return $next($request);
            }

            $firstCompany = $user->companies()->first();
            
            if ($firstCompany) {
                session(['active_company_id' => $firstCompany->id]);
                return $next($request);
            }

            return redirect()->route('companies.create')
                ->with('message', 'Please create or join a company to continue.');
        }

        return $next($request);
    }
}
