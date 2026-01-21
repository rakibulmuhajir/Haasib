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
        $companies = DB::table('auth.companies as c')
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
