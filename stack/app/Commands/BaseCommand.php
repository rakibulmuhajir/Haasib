<?php

namespace App\Commands;

use App\Services\ServiceContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class BaseCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        protected readonly ServiceContext $context,
        protected readonly array $data = []
    ) {}

    /**
     * Handle the command execution
     */
    abstract public function handle(): mixed;

    /**
     * Failed command handling
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Command execution failed', [
            'command' => static::class,
            'data' => $this->data,
            'context' => $this->context->toArray(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Execute command within database transaction
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Log audit trail for command
     */
    protected function audit(string $action, array $details = []): void
    {
        Log::info('Command audit trail', [
            'action' => $action,
            'command' => static::class,
            'data' => $this->data,
            'context' => $this->context->toArray(),
            'details' => $details,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get command context
     */
    protected function getContext(): ServiceContext
    {
        return $this->context;
    }

    /**
     * Get command data
     */
    protected function getData(): array
    {
        return $this->data;
    }

    /**
     * Get specific data value with default
     */
    protected function getValue(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if command has specific data key
     */
    protected function hasValue(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}