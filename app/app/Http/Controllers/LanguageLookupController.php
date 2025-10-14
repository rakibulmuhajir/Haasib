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
            'select' => ['code', 'name', 'native_name'],
            'search' => ['name', 'native_name', 'code'],
            'order' => 'name',
        ]);

        return response()->json(['data' => $rows]);
    }
}
