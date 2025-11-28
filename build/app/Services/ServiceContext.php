<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Immutable service context object for tenant isolation and audit trails.
 *
 * This class encapsulates all context information needed for service operations,
 * ensuring consistent company scoping, user attribution, and audit trail support.
 */
class ServiceContext
{
    private readonly string $requestId;

    public function __construct(
        private readonly ?User $user,
        private readonly ?Company $company,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        ?string $requestId = null
    ) {
        $this->requestId = $requestId ?? $this->generateRequestId();
    }

    public function getUserId(): ?string
    {
        return $this->user?->id;
    }

    public function getCompanyId(): ?string
    {
        return $this->company?->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function isSuperAdmin(): bool
    {
        return $this->user?->hasRole('super_admin') ?? false;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->user?->hasPermissionTo($permission) ?? false;
    }

    /**
     * Check if the context has a valid user.
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * Check if the context has a valid company.
     */
    public function hasCompany(): bool
    {
        return $this->company !== null;
    }

    /**
     * Check if user can access the specified company.
     */
    public function canAccessCompany(string $companyId): bool
    {
        if (! $this->user) {
            \Log::warning('ServiceContext::canAccessCompany - No user found');
            return false;
        }

        $hasAccess = $this->user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();

        return $hasAccess;
    }

    /**
     * Get context information for audit logging.
     */
    public function getAuditContext(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'company_id' => $this->getCompanyId(),
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'company_id' => $this->getCompanyId(),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'request_id' => $this->requestId,
            'is_super_admin' => $this->isSuperAdmin(),
            'has_user' => $this->hasUser(),
            'has_company' => $this->hasCompany(),
        ];
    }

    public static function fromRequest(Request $request): self
    {
        // Use the company set by IdentifyCompany middleware if available,
        // otherwise try to get from CurrentCompany singleton
        $company = $request->attributes->get('company') ?? app(CurrentCompany::class)->get();

        return new self(
            user: $request->user(),
            company: $company,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: $request->header('X-Request-ID')
        );
    }

    /**
     * Create service context from user and company.
     */
    public static function create(?User $user = null, ?Company $company = null, ?string $ipAddress = null, ?string $userAgent = null): self
    {
        return new self(
            user: $user,
            company: $company ?? app(CurrentCompany::class)->get(),
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Create service context for system operations (no user).
     */
    public static function system(?Company $company = null): self
    {
        return new self(
            user: null,
            company: $company,
            ipAddress: null,
            userAgent: 'System'
        );
    }

    public static function forTesting(?User $user = null, ?Company $company = null): self
    {
        return new self(
            user: $user,
            company: $company,
            ipAddress: '127.0.0.1',
            userAgent: 'Test-Client'
        );
    }

    /**
     * Generate a unique request ID.
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Create a new context with a different company.
     */
    public function withCompany(?Company $company): self
    {
        return new self(
            user: $this->user,
            company: $company,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            requestId: $this->requestId
        );
    }

    /**
     * Create a new context with a different user.
     */
    public function withUser(?User $user): self
    {
        return new self(
            user: $user,
            company: $this->company,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            requestId: $this->requestId
        );
    }
}