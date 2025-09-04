<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LookupService;

class CountryLookupController extends Controller
{
    public function suggest(Request $request, LookupService $lookup)
    {
        $request->user(); // ensure auth

        $rows = $lookup->suggest('countries', [
            'select' => ['code','alpha3','name','emoji','region','subregion','calling_code'],
            'search' => ['name','code','alpha3'],
            'order' => 'name',
        ], [
            'region' => 'region',
        ]);

        return response()->json(['data' => $rows]);
    }
}

