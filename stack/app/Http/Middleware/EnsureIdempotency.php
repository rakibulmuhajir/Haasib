<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to write operations that need idempotency
        if (! $this->requiresIdempotency($request)) {
            return $next($request);
        }

        $idempotencyKey = $this->getIdempotencyKey($request);
        
        if (! $idempotencyKey) {
            return response()->json([
                'message' => 'Idempotency-Key header is required for this operation',
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                'suggestion' => 'Include a unique Idempotency-Key header to prevent duplicate operations',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if this request was already processed
        $existingResult = Cache::get("idempotency:{$idempotencyKey}");
        
        if ($existingResult) {
            return response()->json([
                'message' => 'This request was already processed',
                'code' => 'DUPLICATE_REQUEST',
                'original_response' => $existingResult['data'],
                'processed_at' => $existingResult['cached_at'],
            ], $existingResult['status']);
        }

        // Check for conflicting in-progress requests
        $inProgressKey = "idempotency:{$idempotencyKey}:in_progress";
        if (Cache::has($inProgressKey)) {
            return response()->json([
                'message' => 'Request with this idempotency key is already in progress',
                'code' => 'REQUEST_IN_PROGRESS',
                'retry_after' => 5, // seconds
            ], Response::HTTP_CONFLICT);
        }

        // Mark this request as in progress
        Cache::put($inProgressKey, true, now()->addMinutes(5));

        $response = $next($request);

        // Clear in-progress marker
        Cache::forget($inProgressKey);

        return $response;
    }

    private function requiresIdempotency(Request $request): bool
    {
        // Only apply to write operations
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }

        // Define which routes require idempotency
        $protectedRoutes = [
            'companies.store',
            'companies.update',
            'companies.destroy',
            'companies.invite',
            'company.invitations.store',
            'company.invitations.update',
            'company.context.switch',
        ];

        $routeName = $request->route()?->getName();
        
        return in_array($routeName, $protectedRoutes) ||
               str_starts_with($routeName ?? '', 'companies.') ||
               str_starts_with($routeName ?? '', 'company.invitations.');
    }

    private function getIdempotencyKey(Request $request): ?string
    {
        // Try header first (preferred method)
        $key = $request->header('Idempotency-Key');
        
        if ($key) {
            // Validate key format
            if (strlen($key) > 255 || ! preg_match('/^[a-zA-Z0-9\-_\.]+$/', $key)) {
                return null;
            }
            return $key;
        }

        // Fallback to request parameter (for CLI/form submissions)
        $key = $request->get('_idempotency_key');
        
        if ($key && strlen($key) <= 255 && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $key)) {
            return $key;
        }

        return null;
    }
}
