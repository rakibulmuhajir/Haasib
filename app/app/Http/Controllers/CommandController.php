<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Support\CommandBus;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Idempotency check (scoped by user/company/action/key)
        try {
            if (Schema::hasTable('idempotency_keys')) {
                $exists = DB::table('idempotency_keys')->where([
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'key' => $key,
                ])->exists();
                if ($exists) {
                    return response()->json(['message' => 'Duplicate request'], 409);
                }
            }
        } catch (\Throwable $e) {
            // If table missing or any driver error, skip idempotency check rather than 500
        }

        try {
            $result = CommandBus::dispatch($action, $params, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (HttpExceptionInterface $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Request not allowed',
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Command failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        try {
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
        } catch (\Throwable $e) {
            // Log write failure should not block the command response
        }

        // Record idempotency key for future replays
        try {
            if (Schema::hasTable('idempotency_keys')) {
                DB::table('idempotency_keys')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'key' => $key,
                    'request' => json_encode($params),
                    'response' => json_encode($result),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json($result);
    }
}
