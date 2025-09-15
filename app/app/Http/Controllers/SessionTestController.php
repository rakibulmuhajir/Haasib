<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SessionTestController extends Controller
{
    public function store(Request $request)
    {
        Session::put('test_value', 'test_data_'.time());
        Session::save();

        return response()->json([
            'stored' => Session::get('test_value'),
            'session_id' => Session::getId(),
            'all_session' => Session::all(),
        ]);
    }

    public function retrieve(Request $request)
    {
        return response()->json([
            'retrieved' => Session::get('test_value'),
            'session_id' => Session::getId(),
            'all_session' => Session::all(),
        ]);
    }

    public function companySession(Request $request)
    {
        return response()->json([
            'current_company_id' => Session::get('current_company_id'),
            'session_id' => Session::getId(),
            'all_session' => Session::all(),
        ]);
    }
}
