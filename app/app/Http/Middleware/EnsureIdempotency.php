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
        // Store only a hash of the payload to avoid storing sensitive data
        $payloadHash = hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'input' => $request->except(['password', 'password_confirmation', 'current_password', 'token', '_token']),
        ]));

        $existing = DB::table('idempotency_keys')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('action', $action)
            ->where('key', $key)
            ->first();

        if ($existing) {
            // Check if payload hash matches to prevent collisions
            if ($existing->payload_hash !== $payloadHash) {
                // Different payload for same key - this might be a key reuse
                Log::warning('Idempotency key reused with different payload', [
                    'user_id' => $userId,
                    'key' => $key,
                    'action' => $action,
                ]);

                return response()->json(['error' => 'Idempotency key already used with different payload'], 409);
            }

            // Return stored response if present
            $storedResponse = json_decode($existing->response ?? 'null', true);
            if (is_array($storedResponse)) {
                $status = $storedResponse['status'] ?? 200;

                // For successful responses with resource info, reconstruct minimal response
                if (($storedResponse['success'] ?? false) && isset($storedResponse['resource_id'])) {
                    $body = [
                        'id' => $storedResponse['resource_id'],
                        'message' => ucfirst($storedResponse['resource_type']).' created successfully',
                    ];
                } else {
                    $body = ['message' => 'Request processed successfully'];
                }

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
                    'payload_hash' => $payloadHash,
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

        // Attempt to store minimal response data
        try {
            // Store only essential response data to avoid bloat
            $responseData = [
                'status' => $response->getStatusCode(),
                'success' => $response->isSuccessful(),
            ];

            // For successful responses, store only a reference to the created resource
            if ($response->isSuccessful()) {
                $body = json_decode($response->getContent(), true);
                if (is_array($body)) {
                    // Extract only IDs from common response patterns
                    foreach (['id', 'invoice_id', 'payment_id', 'customer_id'] as $idField) {
                        if (isset($body[$idField])) {
                            $responseData['resource_id'] = $body[$idField];
                            $responseData['resource_type'] = rtrim($idField, '_id');
                            break;
                        }
                    }
                }
            }

            DB::table('idempotency_keys')->where([
                'user_id' => $userId,
                'company_id' => $companyId,
                'action' => $action,
                'key' => $key,
            ])->update([
                'response' => json_encode($responseData),
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
