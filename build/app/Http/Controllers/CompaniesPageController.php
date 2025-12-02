<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

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

        return Inertia::render('companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $result = Bus::dispatch('company.switch', $data);

        return redirect()->back()->with('success', $result['message'] ?? 'Switched company.');
    }
}
