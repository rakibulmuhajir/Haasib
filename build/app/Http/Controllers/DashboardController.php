<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();

        // Get user's companies
        $companies = $user->isGodMode()
            ? DB::table('auth.companies as c')
                ->where('c.is_active', true)
                ->select(
                    'c.id',
                    'c.name',
                    'c.slug',
                    'c.base_currency',
                    'c.industry',
                    'c.country',
                    'c.is_active',
                    DB::raw("'super_admin' as role"),
                    'c.created_at'
                )
                ->orderBy('c.name')
                ->get()
            : DB::table('auth.companies as c')
                ->join('auth.company_user as cu', 'c.id', '=', 'cu.company_id')
                ->where('cu.user_id', $user->id)
                ->where('cu.is_active', true)
                ->select(
                    'c.id',
                    'c.name',
                    'c.slug',
                    'c.base_currency',
                    'c.industry',
                    'c.country',
                    'c.is_active',
                    'cu.role',
                    'c.created_at'
                )
                ->orderBy('c.name')
                ->get();

        $lastCompanySlug = session('last_company_slug');
        $defaultCompany = $companies->firstWhere('slug', $lastCompanySlug) ?: $companies->first();

        if ($defaultCompany) {
            session(['last_company_slug' => $defaultCompany->slug]);

            return redirect("/{$defaultCompany->slug}");
        }

        return Inertia::render('Dashboard', [
            'companies' => $companies,
        ]);
    }
}
