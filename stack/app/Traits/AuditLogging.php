<?php

namespace App\Traits;

use App\Support\ServiceContext;
use Illuminate\Support\Facades\Log;

trait AuditLogging
{
    /**
     * Log an audit event
     */
    protected function logAudit(string $event, array $data = [], ?ServiceContext $context = null): void
    {
        $auditData = array_merge([
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ], $data);

        if ($context) {
            $auditData['context'] = [
                'company_id' => $context->getCompanyId(),
                'user_id' => $context->getUser()?->id,
                'session_id' => session()->getId(),
            ];
        }

        Log::info('audit', $auditData);
    }
}