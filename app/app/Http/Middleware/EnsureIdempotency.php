<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        // Enforce for mutating methods only
        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            // No key provided; proceed without idempotency
            return $next($request);
        }

        $user = $request->user();
        $userId = $user?->id;
        // Best-effort company context (optional)
        $companyId = $user?->current_company_id ?? null;

        $action = $request->route()?->getName() ?: ($request->method().' '.$request->path());
        $payload = [
            'method' => $request->method(),
            'path' => $request->path(),
            'input' => $request->all(),
        ];

        $existing = DB::table('idempotency_keys')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('action', $action)
            ->where('key', $key)
            ->first();

        if ($existing) {
            // Return stored response if present, regardless of minor payload differences
            $storedResponse = json_decode($existing->response ?? 'null', true);
            if (is_array($storedResponse)) {
                $status = $storedResponse['status'] ?? 200;
                $body = $storedResponse['body'] ?? $storedResponse;

                return response()->json($body, $status);
            }
            // If no response stored, fall through to allow processing (rare)
        }

        // Create a record (upsert to avoid races)
        try {
            DB::table('idempotency_keys')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'action' => $action,
                    'key' => $key,
                ],
                [
                    'request' => json_encode($payload),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Idempotency insert failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }

        $response = $next($request);

        // Attempt to store response
        try {
            $body = null;
            $contentType = (string) $response->headers->get('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $body = json_decode($response->getContent(), true);
            }

            DB::table('idempotency_keys')->where([
                'user_id' => $userId,
                'company_id' => $companyId,
                'action' => $action,
                'key' => $key,
            ])->update([
                'response' => json_encode([
                    'status' => $response->getStatusCode(),
                    'body' => $body,
                ]),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Idempotency response store failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
