<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocaleLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $request->user();

        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);
        $lang = (string) $request->query('language');
        $country = (string) $request->query('country');

        $query = DB::table('locales');

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('tag', 'ilike', $like)
                  ->orWhere('name', 'ilike', $like)
                  ->orWhere('native_name', 'ilike', $like);
            });
        }
        if ($lang !== '') {
            $query->where('language_code', $lang);
        }
        if ($country !== '') {
            $query->where('country_code', $country);
        }

        $rows = $query->orderBy('tag')->limit($limit)
            ->get(['tag','name','native_name','language_code','country_code','script','variant']);

        return response()->json(['data' => $rows]);
    }
}

