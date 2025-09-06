<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CapabilitiesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $request->session()->get('current_company_id');
        $actions = array_keys(config('command-bus') ?? []);

        $allowed = [];
        foreach ($actions as $action) {
            if (str_starts_with($action, 'ui.')) continue; // UI-only
            if (Gate::allows('command.execute', [$action, $companyId])) {
                $allowed[] = $action;
            }
        }

        return response()->json(['allowed_actions' => $allowed]);
    }
}

