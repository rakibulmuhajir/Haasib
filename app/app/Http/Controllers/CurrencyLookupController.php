<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LookupService;

class CurrencyLookupController extends Controller
{
    public function suggest(Request $request, LookupService $lookup)
    {
        $request->user();

        $rows = $lookup->suggest('currencies', [
            'select' => ['code','numeric_code','name','symbol','minor_unit','cash_minor_unit','rounding','fund'],
            'search' => ['name','code','numeric_code','symbol'],
            'order' => 'code',
        ]);

        return response()->json(['data' => $rows]);
    }
}

