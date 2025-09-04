<?php

// app/Http/Controllers/MeController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\CompanyMembershipRepository;

class MeController extends Controller
{
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
        $repo = app(CompanyMembershipRepository::class);
        $isMember = $repo->verifyMembership($user->id, $data['company_id']);

        if (!$isMember) {
            return response()->json(['message' => 'Not a member of that company'], 403);
        }

        // Web: stash in session; API: client should send header next requests
        $request->session()->put('current_company_id', $data['company_id']);

        return response()->json(['ok' => true]);
    }
}

