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
            'select' => ['tag', 'name', 'native_name', 'language_code', 'country_code', 'script', 'variant'],
            'search' => ['tag', 'name', 'native_name'],
            'order' => 'tag',
        ], [
            'language' => 'language_code',
            'country' => 'country_code',
        ]);

        return response()->json(['data' => $rows]);
    }
}
