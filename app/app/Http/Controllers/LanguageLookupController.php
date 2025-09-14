<?php

namespace App\Http\Controllers;

use App\Services\LookupService;
use Illuminate\Http\Request;

class LanguageLookupController extends Controller
{
    public function suggest(Request $request, LookupService $lookup)
    {
        $request->user();

        $rows = $lookup->suggest('languages', [
            'select' => ['code', 'name', 'native_name', 'iso_639_1', 'iso_639_2', 'rtl', 'script'],
            'search' => ['name', 'native_name', 'code', 'iso_639_1', 'iso_639_2'],
            'order' => 'name',
        ], [
            'rtl' => ['column' => 'rtl', 'type' => 'bool'],
        ]);

        return response()->json(['data' => $rows]);
    }
}
