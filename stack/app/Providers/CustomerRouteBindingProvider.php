<?php

namespace App\Providers;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomerRouteBindingProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Custom route model binding for customers with company context
        Route::bind('customer', function ($value) {
            // Get current company from multiple possible sources
            $company = null;
            
            // 1. Try to get company from session (set by SetCompanyContext middleware)
            $sessionCompanyId = session('current_company_id');
            if ($sessionCompanyId) {
                $company = \App\Models\Company::find($sessionCompanyId);
            }
            
            // 2. Try to get from authenticated user's companies
            if (!$company && auth()->check()) {
                $company = auth()->user()->companies()->first();
            }
            
            // 3. Try to get from database RLS context
            if (!$company) {
                try {
                    $result = \Illuminate\Support\Facades\DB::selectOne("SELECT current_setting('app.current_company_id', true) as company_id");
                    if ($result && $result->company_id) {
                        $company = \App\Models\Company::find($result->company_id);
                    }
                } catch (\Exception $e) {
                    // Ignore if setting doesn't exist or can't be accessed
                }
            }
            
            // If we still don't have a company context, we can't resolve the customer
            if (!$company) {
                return null;
            }
            
            // Set RLS context for the query
            \Illuminate\Support\Facades\DB::statement("SET app.current_company_id = '{$company->id}'");
            \Illuminate\Support\Facades\DB::statement("SET app.is_super_admin = 'false'");
            
            // Find the customer within the company context
            return Customer::withoutGlobalScope(\App\Scopes\CompanyScope::class)
                ->where('company_id', $company->id)
                ->where('id', $value)
                ->first();
        });
    }
}
