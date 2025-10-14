<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait HandlesIdempotency
{
    /**
     * Generate a unique idempotency key for a model operation
     */
    protected function generateIdempotencyKey(string $operation, array $data = []): string
    {
        $userKey = auth()->id() ?? 'anonymous';
        $dataHash = hash('sha256', json_encode($data));
        
        return "{$operation}:{$userKey}:{$dataHash}";
    }

    /**
     * Check if an operation with given idempotency key was already performed
     */
    protected function isOperationAlreadyProcessed(string $idempotencyKey): bool
    {
        return Cache::has("idempotency:{$idempotencyKey}");
    }

    /**
     * Get the result of a previously processed operation
     */
    protected function getProcessedOperationResult(string $idempotencyKey): ?array
    {
        return Cache::get("idempotency:{$idempotencyKey}");
    }

    /**
     * Store the result of an operation for idempotency
     */
    protected function storeOperationResult(string $idempotencyKey, array $result, int $ttl = 86400): void
    {
        Cache::put("idempotency:{$idempotencyKey}", [
            'data' => $result,
            'cached_at' => now()->toISOString(),
            'user_id' => auth()->id(),
        ], $ttl);
    }

    /**
     * Lock an operation to prevent concurrent execution
     */
    protected function acquireOperationLock(string $operationKey, int $ttl = 300): bool
    {
        $lockKey = "operation_lock:{$operationKey}";
        
        return Cache::add($lockKey, true, $ttl);
    }

    /**
     * Release an operation lock
     */
    protected function releaseOperationLock(string $operationKey): void
    {
        $lockKey = "operation_lock:{$operationKey}";
        Cache::forget($lockKey);
    }

    /**
     * Execute an operation with idempotency protection
     */
    protected function executeWithIdempotency(
        string $operation,
        array $data,
        callable $callback,
        int $cacheTtl = 86400
    ) {
        $idempotencyKey = $this->generateIdempotencyKey($operation, $data);
        $lockKey = "operation:{$operation}";

        // Check if already processed
        if ($this->isOperationAlreadyProcessed($idempotencyKey)) {
            return $this->getProcessedOperationResult($idempotencyKey)['data'];
        }

        // Try to acquire lock
        if (! $this->acquireOperationLock($lockKey)) {
            // If lock can't be acquired, wait and check again
            sleep(1);
            
            if ($this->isOperationAlreadyProcessed($idempotencyKey)) {
                return $this->getProcessedOperationResult($idempotencyKey)['data'];
            }

            return [
                'success' => false,
                'message' => 'Operation is currently in progress. Please try again.',
                'code' => 'OPERATION_IN_PROGRESS',
            ];
        }

        try {
            // Execute the operation
            $result = $callback();

            // Store the result for idempotency
            $this->storeOperationResult($idempotencyKey, $result, $cacheTtl);

            return $result;
        } finally {
            // Always release the lock
            $this->releaseOperationLock($lockKey);
        }
    }

    /**
     * Extract idempotency key from request
     */
    protected function getIdempotencyKeyFromRequest(): ?string
    {
        $request = request();
        
        // Try header first
        $key = $request->header('Idempotency-Key');
        if ($key) {
            return $key;
        }

        // Fallback to parameter
        return $request->get('_idempotency_key');
    }

    /**
     * Validate idempotency key format
     */
    protected function validateIdempotencyKey(string $key): bool
    {
        return strlen($key) <= 255 && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $key);
    }
}