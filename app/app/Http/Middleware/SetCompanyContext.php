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

            // Set the company context for RLS policies (set both keys for compatibility)
            DB::statement("SET SESSION app.current_company_id = '{$companyId}'");
            DB::statement("SET SESSION app.current_company = '{$companyId}'");

            // Store in config for easy access throughout the application
            config(['app.current_company_id' => $companyId]);
            config(['app.current_company' => $companyId]);
        } else {
            // Clear company context if no company is selected
            try {
                DB::statement('RESET app.current_company_id');
                DB::statement('RESET app.current_company');
            } catch (\Exception $e) {
                // Fallback if RESET fails
                DB::statement("SET SESSION app.current_company_id = ''");
                DB::statement("SET SESSION app.current_company = ''");
            }
            config(['app.current_company_id' => null]);
            config(['app.current_company' => null]);
        }

        return $next($request);
    }
}
