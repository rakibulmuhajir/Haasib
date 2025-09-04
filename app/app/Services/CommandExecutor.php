<?php

namespace App\Services;

use App\Models\User;
use App\Support\CommandBus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CommandExecutor
{
    public function __construct(private CommandBus $bus)
    {
    }

    public function execute(string $action, array $params, User $user, ?string $companyId, string $key): array
    {
        // Idempotency check (scoped by user/company/action/key)
        if ($this->checkIdempotency($action, $user, $companyId, $key)) {
            return [['message' => 'Duplicate request'], 409];
        }

        try {
            $result = $this->bus->dispatch($action, $params, $user);
        } catch (ValidationException $e) {
            return [[
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422];
        } catch (HttpExceptionInterface $e) {
            return [[
                'ok' => false,
                'message' => $e->getMessage() ?: 'Request not allowed',
            ], $e->getStatusCode()];
        } catch (\Throwable $e) {
            return [[
                'ok' => false,
                'message' => 'Command failed',
                'error' => $e->getMessage(),
            ], 500];
        }

        $this->logAudit($action, $params, $user, $companyId, $key);

        // Record idempotency key for future replays
        $this->storeIdempotencyKey($action, $params, $user, $companyId, $key, $result);

        return [$result, 200];
    }

    private function checkIdempotency(string $action, User $user, ?string $companyId, string $key): bool
    {
        try {
            if (Schema::hasTable('idempotency_keys')) {
                return DB::transaction(function () use ($action, $user, $companyId, $key) {
                    return DB::table('idempotency_keys')->where([
                        'user_id' => $user->id,
                        'company_id' => $companyId,
                        'action' => $action,
                        'key' => $key,
                    ])->exists();
                });
            }
        } catch (\Throwable $e) {
            // If table missing or any driver error, skip idempotency check rather than 500
        }

        return false;
    }

    private function logAudit(string $action, array $params, User $user, ?string $companyId, string $key): void
    {
        try {
            // Use a nested transaction (savepoint) so a failure here
            // does not poison the outer request transaction.
            DB::transaction(function () use ($action, $params, $user, $companyId, $key) {
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
            });
        } catch (\Throwable $e) {
            // Log write failure should not block the command response
        }
    }

    private function storeIdempotencyKey(string $action, array $params, User $user, ?string $companyId, string $key, $result): void
    {
        try {
            if (Schema::hasTable('idempotency_keys')) {
                DB::transaction(function () use ($action, $params, $user, $companyId, $key, $result) {
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
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
