<?php

// app/Http/Controllers/MeController.php
namespace App\Http\Controllers;

use App\Services\CompanyLookupService;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(protected CompanyLookupService $lookup) {}
    public function companies(Request $request)
    {
        // Use schema-qualified table to match Company::$table = 'auth.companies'
        $companies = $request->user()->companies()->select('auth.companies.id','auth.companies.name')->get();
        return response()->json(['data' => $companies]);
    }

    public function switch(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required','uuid'],
        ]);

        $user = $request->user();
        $isMember = $this->lookup->isMember($data['company_id'], $user->id);

        if (!$isMember) {
            return response()->json(['message' => 'Not a member of that company'], 403);
        }

        // Web: stash in session; API: client should send header next requests
        $request->session()->put('current_company_id', $data['company_id']);

        return response()->json(['ok' => true]);
    }
}
