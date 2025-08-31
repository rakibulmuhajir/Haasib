<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $request->user(); // ensure auth

        $q = (string) $request->query('q', '');
        $region = (string) $request->query('region', '');
        $limit = (int) $request->query('limit', 10);

        $query = DB::table('countries');

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('code', 'ilike', $like)
                  ->orWhere('alpha3', 'ilike', $like);
            });
        }
        if ($region !== '') {
            $query->where('region', $region);
        }

        $rows = $query
            ->orderBy('name')
            ->limit($limit)
            ->get(['code','alpha3','name','emoji','region','subregion','calling_code']);

        return response()->json(['data' => $rows]);
    }
}

