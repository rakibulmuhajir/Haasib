<?php

namespace App\Http\Controllers;

use App\Services\CommandExecutor;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    public function execute(Request $request, CommandExecutor $executor)
    {
        \Log::info('🔥 COMMAND CONTROLLER HIT - CommandController.php', [
            'action' => $request->header('X-Action'),
            'key' => $request->header('X-Idempotency-Key'),
            'all_headers' => $request->headers->all(),
            'request_data' => $request->all(),
            'user' => $request->user() ? $request->user()->id : 'not authenticated',
        ]);

        $action = $request->header('X-Action');
        $key = $request->header('X-Idempotency-Key');
        abort_unless($action && $key, 400, 'Missing headers');

        $user = $request->user();
        $params = $request->all();

        // For superadmins, try to get company from request params if not in session
        $companyId = $request->session()->get('current_company_id');
        if (($companyId === '' || $companyId === false) && $user->isSuperAdmin()) {
            $companyId = $params['company'] ?? null;
        }

        if ($companyId === '' || $companyId === false) {
            $companyId = null;
        }

        \Log::info('📡 Calling CommandExecutor->execute()', [
            'action' => $action,
            'params' => $params,
            'user_id' => $user->id,
            'company_id' => $companyId,
            'key' => $key,
        ]);

        [$body, $status] = $executor->execute($action, $params, $user, $companyId, $key);

        \Log::info('✅ CommandExecutor completed', [
            'status' => $status,
            'response_body' => $body,
        ]);

        return response()->json($body, $status);
    }
}
