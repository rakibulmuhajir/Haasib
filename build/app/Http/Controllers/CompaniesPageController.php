<?php

namespace App\Http\Controllers;

use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompaniesPageController extends Controller
{
    public function index(Request $request): Response
    {
        $isGodMode = $request->user()?->isGodMode() ?? false;
        $companies = $isGodMode
            ? DB::table('auth.companies as c')
                ->leftJoin('auth.company_user as owner', function ($join) {
                    $join->on('c.id', '=', 'owner.company_id')
                        ->where('owner.role', '=', 'owner')
                        ->where('owner.is_active', '=', true);
                })
                ->leftJoin('auth.users as owner_user', 'owner.user_id', '=', 'owner_user.id')
                ->leftJoin('auth.company_user as cu_count', function ($join) {
                    $join->on('c.id', '=', 'cu_count.company_id')
                        ->where('cu_count.is_active', '=', true);
                })
                ->where('c.is_active', true)
                ->select(
                    'c.id',
                    'c.name',
                    'c.slug',
                    'c.base_currency',
                    'c.is_active',
                    DB::raw("'super_admin' as role"),
                    'c.created_at',
                    'owner_user.name as owner_name',
                    'owner_user.email as owner_email',
                    DB::raw('COUNT(cu_count.user_id) as user_count')
                )
                ->groupBy('c.id', 'c.name', 'c.slug', 'c.base_currency', 'c.is_active', 'c.created_at', 'owner_user.name', 'owner_user.email')
                ->orderBy('c.name')
                ->get()
            : DB::table('auth.companies as c')
                ->join('auth.company_user as cu', 'c.id', '=', 'cu.company_id')
                ->where('cu.user_id', Auth::id())
                ->where('cu.is_active', true)
                ->select(
                    'c.id',
                    'c.name',
                    'c.slug',
                    'c.base_currency',
                    'c.is_active',
                    'cu.role',
                    'c.created_at'
                )
                ->orderBy('c.name')
                ->get();

        $industries = DB::table('acct.industry_coa_packs')
            ->where('is_active', true)
            ->where('code', '!=', 'umrah')
            ->orderBy('sort_order')
            ->get(['code', 'name', 'description']);

        $countries = collect(config('countries', []))
            ->map(fn ($country) => [
                'code' => $country['code'],
                'name' => $country['name'],
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return Inertia::render('companies/Index', [
            'companies' => $companies,
            'industries' => $industries,
            'countries' => $countries,
            'users' => $isGodMode
                ? DB::table('auth.users')->orderBy('name')->orderBy('email')->get(['id', 'name', 'email'])
                : [],
            'canCreateCompanies' => true,
            'canAssignOwner' => $isGodMode,
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $commandBus = app(CommandBus::class);
        $result = $commandBus->dispatch('company.switch', $data, $request->user(), true);

        // Update session to remember this as the last accessed company
        session(['last_company_slug' => $data['slug']]);

        return redirect()->back()->with('success', $result['message'] ?? 'Switched company.');
    }
}
