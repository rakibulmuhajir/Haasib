<?php

namespace App\Traits;

use App\Models\User;
use App\Support\ServiceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait AuditLogging
{
    protected function logAudit(
        string $action,
        array $params,
        ?ServiceContext $context = null,
        ?User $user = null,
        ?string $companyId = null,
        ?string $idempotencyKey = null,
        ?array $result = null
    ): void {
        if ($context) {
            $user = $context->getActingUser();
            $companyId = $context->getCompanyId();
            $idempotencyKey = $context->getIdempotencyKey();
        }

        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('acct.audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
