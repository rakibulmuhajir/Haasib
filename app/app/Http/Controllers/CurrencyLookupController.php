<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $request->user();

        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);

        $query = DB::table('currencies');

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('code', 'ilike', $like)
                  ->orWhere('numeric_code', 'ilike', $like)
                  ->orWhere('symbol', 'ilike', $like);
            });
        }

        $rows = $query->orderBy('code')->limit($limit)
            ->get(['code','numeric_code','name','symbol','minor_unit','cash_minor_unit','rounding','fund']);

        return response()->json(['data' => $rows]);
    }
}

