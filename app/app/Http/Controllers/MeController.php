<?php

// app/Http/Controllers/MeController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    public function companies(Request $request)
    {
        $companies = $request->user()->companies()->select('companies.id','companies.name')->get();
        return response()->json(['data' => $companies]);
    }

    public function switch(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required','uuid'],
        ]);

        $user = $request->user();
        $isMember = DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $data['company_id'])
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Not a member of that company'], 403);
        }

        // Web: stash in session; API: client should send header next requests
        $request->session()->put('current_company_id', $data['company_id']);

        return response()->json(['ok' => true]);
    }
}
