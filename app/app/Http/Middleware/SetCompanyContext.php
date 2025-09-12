<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->currentCompany) {
            $companyId = $user->currentCompany->id;

            // Set the company context for RLS policies
            DB::statement("SET SESSION app.current_company_id = '{$companyId}'");

            // Store in config for easy access throughout the application
            config(['app.current_company_id' => $companyId]);
        } else {
            // Clear company context if no company is selected
            try {
                DB::statement('RESET app.current_company_id');
            } catch (\Exception $e) {
                // Fallback if RESET fails
                DB::statement("SET SESSION app.current_company_id = ''");
            }
            config(['app.current_company_id' => null]);
        }

        return $next($request);
    }
}
