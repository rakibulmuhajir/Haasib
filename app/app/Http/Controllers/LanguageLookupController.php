<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LanguageLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $request->user();

        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);
        $rtl = $request->query('rtl');

        $query = DB::table('languages');

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('native_name', 'ilike', $like)
                  ->orWhere('code', 'ilike', $like)
                  ->orWhere('iso_639_1', 'ilike', $like)
                  ->orWhere('iso_639_2', 'ilike', $like);
            });
        }
        if ($rtl !== null) {
            $query->where('rtl', (bool) $rtl);
        }

        $rows = $query->orderBy('name')->limit($limit)
            ->get(['code','name','native_name','iso_639_1','iso_639_2','rtl','script']);

        return response()->json(['data' => $rows]);
    }
}

