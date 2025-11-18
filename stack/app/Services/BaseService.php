<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Traits\AuditLogging;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for all services requiring constitutional compliance.
 *
 * This class provides common functionality for ServiceContext integration,
 * company access validation, RLS context setting, and audit trail support.
 * All services should extend this base class to ensure consistent
 * constitutional patterns across the application.
 */
abstract class BaseService
{
    use AuditLogging;

    protected ServiceContext $context;

    public function __construct(ServiceContext $context)
    {
        $this->context = $context;
    }

    /**
     * Get the service context.
     */
    protected function getContext(): ServiceContext
    {
        return $this->context;
    }

    /**
     * Get the current user from context.
     */
    protected function getUser(): ?User
    {
        return $this->context->getUser();
    }

    /**
     * Get the current user ID from context.
     */
    protected function getUserId(): ?string
    {
        return $this->context->getUserId();
    }

    /**
     * Get the current company from context.
     */
    protected function getCompany(): ?Company
    {
        return $this->context->getCompany();
    }

    /**
     * Get the current company ID from context.
     */
    protected function getCompanyId(): ?string
    {
        return $this->context->getCompanyId();
    }

    /**
     * Get the IP address from context.
     */
    protected function getIpAddress(): ?string
    {
        return $this->context->getIpAddress();
    }

    /**
     * Get the user agent from context.
     */
    protected function getUserAgent(): ?string
    {
        return $this->context->getUserAgent();
    }

    /**
     * Get the request ID from context.
     */
    protected function getRequestId(): string
    {
        return $this->context->getRequestId();
    }

    /**
     * Validate user can access the specified company.
     */
    protected function validateCompanyAccess(string $companyId): void
    {
        if (! $this->context->canAccessCompany($companyId)) {
            throw new \InvalidArgumentException('User does not have access to the specified company');
        }
    }

    /**
     * Validate user can access the current company.
     */
    protected function validateCurrentCompanyAccess(): void
    {
        if (! $this->context->hasCompany()) {
            throw new \InvalidArgumentException('Company context is required for this operation');
        }

        $this->validateCompanyAccess($this->getCompanyId());
    }

    /**
     * Set RLS context for database operations.
     */
    protected function setRlsContext(?string $companyId = null): void
    {
        $targetCompanyId = $companyId ?? $this->getCompanyId();

        if (! $targetCompanyId) {
            throw new \InvalidArgumentException('Company ID is required for RLS context');
        }

        $escapedCompanyId = addslashes($targetCompanyId);
        DB::statement("SET app.current_company_id = '{$escapedCompanyId}'");
        DB::statement('SET app.current_user_id = ?', [$this->getUserId()]);
    }

    /**
     * Execute a callback within a database transaction with proper context.
     */
    protected function executeInTransaction(callable $callback, ?string $companyId = null): mixed
    {
        $targetCompanyId = $companyId ?? $this->getCompanyId();

        return DB::transaction(function () use ($callback, $targetCompanyId) {
            // Set RLS context at the start of transaction
            $this->setRlsContext($targetCompanyId);

            return $callback();
        });
    }

    /**
     * Execute a callback with proper company validation and RLS context.
     */
    protected function executeWithCompanyContext(string $companyId, callable $callback): mixed
    {
        // Validate company access
        $this->validateCompanyAccess($companyId);

        return $this->executeInTransaction($callback, $companyId);
    }

    /**
     * Create an audit entry with context information.
     */
    protected function createAuditEntry(string $action, array $data = []): void
    {
        $auditData = array_merge($this->context->getAuditContext(), $data);

        $this->audit($action, $auditData);
    }

    /**
     * Log an informational message with context.
     */
    protected function logInfo(string $message, array $data = []): void
    {
        Log::info($message, array_merge([
            'service' => static::class,
            'request_id' => $this->getRequestId(),
            'company_id' => $this->getCompanyId(),
            'user_id' => $this->getUserId(),
        ], $data));
    }

    /**
     * Log a warning message with context.
     */
    protected function logWarning(string $message, array $data = []): void
    {
        Log::warning($message, array_merge([
            'service' => static::class,
            'request_id' => $this->getRequestId(),
            'company_id' => $this->getCompanyId(),
            'user_id' => $this->getUserId(),
        ], $data));
    }

    /**
     * Log an error message with context.
     */
    protected function logError(string $message, array $data = [], ?\Throwable $exception = null): void
    {
        $errorData = array_merge([
            'service' => static::class,
            'request_id' => $this->getRequestId(),
            'company_id' => $this->getCompanyId(),
            'user_id' => $this->getUserId(),
        ], $data);

        if ($exception) {
            $errorData['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        Log::error($message, $errorData);
    }

    /**
     * Check if the current user has a specific permission.
     */
    protected function can(string $permission): bool
    {
        return $this->context->hasPermission($permission);
    }

    /**
     * Check if the current user is a super admin.
     */
    protected function isSuperAdmin(): bool
    {
        return $this->context->isSuperAdmin();
    }

    /**
     * Validate required context is present.
     */
    protected function validateContext(bool $requireUser = true, bool $requireCompany = true): void
    {
        if ($requireUser && ! $this->context->hasUser()) {
            throw new \InvalidArgumentException('User context is required for this operation');
        }

        if ($requireCompany && ! $this->context->hasCompany()) {
            throw new \InvalidArgumentException('Company context is required for this operation');
        }

        if ($this->context->hasUser() && $this->context->hasCompany()) {
            $this->validateCurrentCompanyAccess();
        }
    }

    /**
     * Create a new instance with different context.
     */
    protected function withContext(ServiceContext $context): static
    {
        return new static($context);
    }

    /**
     * Create a new instance with different user.
     */
    protected function withUser(?User $user): static
    {
        return new static($this->context->withUser($user));
    }

    /**
     * Create a new instance with different company.
     */
    protected function withCompany(?Company $company): static
    {
        return new static($this->context->withCompany($company));
    }

    /**
     * Get service name for logging and debugging.
     */
    protected function getServiceName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Handle exceptions with proper context logging.
     */
    protected function handleException(\Throwable $exception, string $operation = 'operation', array $context = []): never
    {
        $this->logError("Exception in {$this->getServiceName()} during {$operation}", $context, $exception);

        throw $exception;
    }

    /**
     * Create a standardized service response.
     */
    protected function createResponse(bool $success, mixed $data = null, ?string $message = null, array $errors = []): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'errors' => $errors,
            'context' => [
                'service' => $this->getServiceName(),
                'request_id' => $this->getRequestId(),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get current user's permissions for current company.
     */
    protected function getUserPermissions(): array
    {
        if (! $this->context->hasUser() || ! $this->context->hasCompany()) {
            return [];
        }

        return $this->context->getPermissions();
    }
}
