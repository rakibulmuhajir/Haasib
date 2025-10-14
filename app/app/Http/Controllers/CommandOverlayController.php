<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandOverlayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $request->session()->get('current_company_id');

        $rows = DB::table('command_overlays')
            ->where(function ($q) use ($companyId, $user) {
                $q->whereNull('company_id')->whereNull('user_id'); // global
                if ($companyId) {
                    $q->orWhere(function ($q2) use ($companyId) {
                        $q2->where('company_id', $companyId)->whereNull('user_id');
                    });
                }
                $q->orWhere(function ($q3) use ($user) {
                    $q3->whereNull('company_id')->where('user_id', $user->id);
                });
                if ($companyId) {
                    $q->orWhere(function ($q4) use ($companyId, $user) {
                        $q4->where('company_id', $companyId)->where('user_id', $user->id);
                    });
                }
            })
            ->orderByRaw('CASE WHEN user_id IS NOT NULL THEN 1 ELSE 2 END') // user-specific first
            ->orderByRaw('CASE WHEN company_id IS NOT NULL THEN 1 ELSE 2 END') // then company
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $rows]);
    }
}
