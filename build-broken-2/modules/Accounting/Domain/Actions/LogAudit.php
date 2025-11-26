<?php

namespace Modules\Accounting\Domain\Actions;

use Illuminate\Http\Request;
use Modules\Accounting\Models\AuditEntry;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;

class LogAudit
{
    /**
     * Log an audit entry.
     */
    public static function execute(array $data): AuditEntry
    {
        // Set defaults
        $data['ip_address'] = $data['ip_address'] ?? request()?->ip();
        $data['user_agent'] = $data['user_agent'] ?? request()?->userAgent();
        $data['is_system_action'] = $data['is_system_action'] ?? false;

        // Sanitize sensitive data
        if (isset($data['old_values'])) {
            $data['old_values'] = self::sanitizeSensitiveData($data['old_values']);
        }

        if (isset($data['new_values'])) {
            $data['new_values'] = self::sanitizeSensitiveData($data['new_values']);
        }

        return AuditEntry::create($data);
    }

    /**
     * Log an audit entry with automatic context detection.
     */
    public static function log(
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?Company $company = null,
        array $metadata = []
    ): AuditEntry {
        // Auto-detect user and company if not provided
        if (! $user && function_exists('auth') && auth()->check()) {
            $user = auth()->user();
        }

        if (! $company && $user) {
            $company = $user->currentCompany();
        }

        return self::execute([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $user?->id,
            'company_id' => $company?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a model event (created, updated, deleted).
     */
    public static function logModelEvent(
        string $event,
        \Illuminate\Database\Eloquent\Model $model,
        ?User $user = null,
        ?Company $company = null
    ): ?AuditEntry {
        // Skip if model doesn't want auditing
        if (method_exists($model, 'shouldAudit') && ! $model->shouldAudit()) {
            return null;
        }

        $action = match ($event) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'restored' => 'restored',
            'forceDeleted' => 'force_deleted',
            default => $event,
        };

        $oldValues = null;
        $newValues = null;

        switch ($event) {
            case 'created':
                $newValues = self::getModelData($model);
                break;
            case 'updated':
                $oldValues = $model->getOriginal();
                $newValues = $model->getDirty();
                // Only include changed fields
                $newValues = array_intersect_key($model->toArray(), $newValues);
                break;
            case 'deleted':
            case 'forceDeleted':
                $oldValues = self::getModelData($model);
                break;
            case 'restored':
                $newValues = self::getModelData($model);
                break;
        }

        return self::log(
            $action,
            $model->getTable(),
            $model->getKey(),
            $oldValues,
            $newValues,
            $user,
            $company
        );
    }

    /**
     * Log a user login event.
     */
    public static function logLogin(User $user, ?Request $request = null): AuditEntry
    {
        return self::execute([
            'action' => 'login',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'user_id' => $user->id,
            'company_id' => $user->getCurrentCompanyIdAttribute(),
            'ip_address' => $request?->ip() ?? request()?->ip(),
            'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
            'new_values' => [
                'email' => $user->email,
                'login_time' => now()->toISOString(),
            ],
            'metadata' => [
                'device_type' => self::detectDeviceType($request?->userAgent() ?? request()?->userAgent()),
            ],
        ]);
    }

    /**
     * Log a user logout event.
     */
    public static function logLogout(User $user, ?Request $request = null): AuditEntry
    {
        return self::execute([
            'action' => 'logout',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'user_id' => $user->id,
            'company_id' => $user->getCurrentCompanyIdAttribute(),
            'ip_address' => $request?->ip() ?? request()?->ip(),
            'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
            'new_values' => [
                'email' => $user->email,
                'logout_time' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailedLogin(string $email, string $reason, ?Request $request = null): AuditEntry
    {
        return self::execute([
            'action' => 'login_failed',
            'entity_type' => 'user',
            'entity_id' => null,
            'user_id' => null,
            'company_id' => null,
            'ip_address' => $request?->ip() ?? request()?->ip(),
            'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
            'new_values' => [
                'email' => $email,
                'reason' => $reason,
                'attempt_time' => now()->toISOString(),
            ],
            'is_system_action' => true,
        ]);
    }

    /**
     * Log a permission denied event.
     */
    public static function logPermissionDenied(User $user, string $permission, ?string $resource = null): AuditEntry
    {
        return self::execute([
            'action' => 'permission_denied',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'user_id' => $user->id,
            'company_id' => $user->getCurrentCompanyIdAttribute(),
            'new_values' => [
                'permission' => $permission,
                'resource' => $resource,
            ],
            'metadata' => [
                'user_role' => $user->currentCompany()?->companyUsers()
                    ->where('user_id', $user->id)
                    ->first()?->role,
            ],
        ]);
    }

    /**
     * Log a data export event.
     */
    public static function logDataExport(User $user, string $exportType, int $recordCount, array $filters = []): AuditEntry
    {
        return self::execute([
            'action' => 'data_exported',
            'entity_type' => 'export',
            'entity_id' => null,
            'user_id' => $user->id,
            'company_id' => $user->getCurrentCompanyIdAttribute(),
            'new_values' => [
                'export_type' => $exportType,
                'record_count' => $recordCount,
                'filters' => $filters,
                'export_time' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get model data for auditing.
     */
    protected static function getModelData(\Illuminate\Database\Eloquent\Model $model): array
    {
        $data = $model->toArray();

        // Remove hidden attributes
        $hidden = $model->getHidden();
        foreach ($hidden as $attribute) {
            unset($data[$attribute]);
        }

        // Remove sensitive relationships
        if (method_exists($model, 'getAuditableRelations')) {
            $auditableRelations = $model->getAuditableRelations();
            $relations = $model->getRelations();
            foreach ($relations as $key => $relation) {
                if (! in_array($key, $auditableRelations)) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    /**
     * Sanitize sensitive data from audit values.
     */
    protected static function sanitizeSensitiveData(array $data): array
    {
        $sensitiveFields = [
            'password', 'password_confirmation',
            'api_key', 'secret_key', 'token',
            'credit_card', 'card_number', 'cvv',
            'ssn', 'social_security',
            'bank_account', 'routing_number',
            'secret', 'private_key',
        ];

        foreach ($data as $key => $value) {
            // Check if key contains sensitive terms
            $keyLower = strtolower($key);
            foreach ($sensitiveFields as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $data[$key] = self::sanitizeSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Detect device type from user agent.
     */
    protected static function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') || str_contains($userAgent, 'iphone')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Create audit summary for a period.
     */
    public static function createSummary(
        ?Company $company = null,
        ?User $user = null,
        ?\Carbon\Carbon $startDate = null,
        ?\Carbon\Carbon $endDate = null
    ): array {
        $query = AuditEntry::query();

        if ($company) {
            $query->byCompany($company);
        }

        if ($user) {
            $query->byUser($user);
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();

        $actions = (clone $query)->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->pluck('count', 'action')
            ->toArray();

        $topUsers = (clone $query)->selectRaw('user_id, COUNT(*) as count')
            ->with('user')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => $item->user?->name ?? 'System',
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return [
            'total_actions' => $total,
            'by_action' => $actions,
            'top_users' => $topUsers,
            'period' => [
                'start' => $startDate?->toIso8601String(),
                'end' => $endDate?->toIso8601String(),
            ],
        ];
    }
}
