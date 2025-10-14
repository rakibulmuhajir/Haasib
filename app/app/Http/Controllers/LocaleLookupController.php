<?php

namespace App\Http\Controllers;

use App\Services\LookupService;
use Illuminate\Http\Request;

class LocaleLookupController extends Controller
{
    public function suggest(Request $request, LookupService $lookup)
    {
        $request->user();

        $rows = $lookup->suggest('locales', [
            'select' => ['code', 'name', 'native_name', 'language_code', 'country_code'],
            'search' => ['code', 'name', 'native_name'],
            'order' => 'code',
        ], [
            'language' => 'language_code',
            'country' => 'country_code',
        ]);

        return response()->json(['data' => $rows]);
    }
}
