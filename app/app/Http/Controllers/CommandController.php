<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CommandExecutor;

class CommandController extends Controller
{
    public function execute(Request $request, CommandExecutor $executor)
    {
        $action = $request->header('X-Action');
        $key    = $request->header('X-Idempotency-Key');
        abort_unless($action && $key, 400, 'Missing headers');

        $user   = $request->user();
        $params = $request->all();
        // Preserve UUID type; do not cast to int
        $companyId = $request->session()->get('current_company_id');
        if ($companyId === '' || $companyId === false) {
            $companyId = null;
        }

        [$body, $status] = $executor->execute($action, $params, $user, $companyId, $key);

        return response()->json($body, $status);
    }
}
