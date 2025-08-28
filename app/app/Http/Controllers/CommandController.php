<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\CommandBus;

class CommandController extends Controller
{
    public function execute(Request $request)
    {
        $action = $request->header('X-Action');
        $key    = $request->header('X-Idempotency-Key');
        abort_unless($action && $key, 400, 'Missing headers');

        $user   = $request->user();
        $params = $request->all();
        $companyId = $request->session()->get('current_company_id');

        if (DB::table('audit.audit_logs')->where('idempotency_key', $key)->exists()) {
            return response()->json(['message' => 'Duplicate request'], 409);
        }

        $result = CommandBus::dispatch($action, $params, $user);

        DB::table('audit.audit_logs')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'company_id' => $companyId,
            'action' => $action,
            'params' => json_encode($params),
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json($result);
    }
}
