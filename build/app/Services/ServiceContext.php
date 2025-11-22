<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ServiceContext provides authenticated user and company context for service operations.
 * This ensures RLS compliance and audit trail consistency across all services.
 */
class ServiceContext
{
    private ?User $user = null;
    private ?Company $company = null;
    private ?string $requestId = null;
    private array $metadata = [];

    public function __construct(
        ?User $user = null,
        ?Company $company = null,
        ?string $requestId = null,
        array $metadata = []
    ) {
        $this->user = $user;
        $this->company = $company;
        $this->requestId = $requestId ?? (string) Str::uuid();
        $this->metadata = $metadata;
    }

    /**
     * Create ServiceContext from HTTP request.
     */
    public static function fromRequest(Request $request): self
    {
        $user = $request->user();
        $company = null;

        if ($user) {
            // Use CompanyContextManager for unified company resolution
            $companyContextManager = app(CompanyContextManager::class);
            $activeCompanyData = $companyContextManager->getActiveCompany($user, $request);
            
            if ($activeCompanyData) {
                $company = Company::find($activeCompanyData['id']);
                
                // Verify access (CompanyContextManager already does this, but double-check)
                if ($company && !$user->canAccessCompany($company->id)) {
                    $company = null;
                }
            }
        }

        return new self(
            user: $user,
            company: $company,
            requestId: $request->header('X-Request-ID') ?? (string) Str::uuid(),
            metadata: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'url' => $request->url(),
            ]
        );
    }


    /**
     * Create ServiceContext for CLI operations.
     */
    public static function forCli(
        ?User $user = null,
        ?Company $company = null,
        array $metadata = []
    ): self {
        return new self(
            user: $user,
            company: $company,
            requestId: (string) Str::uuid(),
            metadata: array_merge([
                'source' => 'cli',
                'command' => $_SERVER['argv'][0] ?? 'unknown',
            ], $metadata)
        );
    }

    /**
     * Create ServiceContext for system operations.
     */
    public static function forSystem(array $metadata = []): self
    {
        return new self(
            user: null,
            company: null,
            requestId: (string) Str::uuid(),
            metadata: array_merge([
                'source' => 'system',
            ], $metadata)
        );
    }

    // Getters
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function getUserId(): ?string
    {
        return $this->user?->id;
    }

    public function getCompanyId(): ?string
    {
        return $this->company?->id;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    // State checks
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function hasCompany(): bool
    {
        return $this->company !== null;
    }

    public function isSystemContext(): bool
    {
        return $this->user === null && $this->company === null;
    }

    // Permission checks
    public function hasPermission(string $permission): bool
    {
        if (!$this->hasUser()) {
            return false;
        }

        // For company-scoped permissions, check with team context
        if ($this->hasCompany()) {
            return $this->user->hasPermissionTo($permission, $this->getCompanyId());
        }

        // For system permissions
        return $this->user->hasPermissionTo($permission);
    }

    public function canAccessCompany(?string $companyId = null): bool
    {
        if (!$this->hasUser()) {
            return false;
        }

        $targetCompanyId = $companyId ?? $this->getCompanyId();
        
        if (!$targetCompanyId) {
            return false;
        }

        // Check if user is assigned to this company
        return $this->user->companies()
            ->where('company_id', $targetCompanyId)
            ->where('is_active', true)
            ->exists();
    }

    // Audit context
    public function getAuditContext(): array
    {
        return [
            'request_id' => $this->getRequestId(),
            'user_id' => $this->getUserId(),
            'company_id' => $this->getCompanyId(),
            'metadata' => $this->getMetadata(),
            'timestamp' => now()->toISOString(),
        ];
    }

    // Context switching
    public function withUser(User $user): self
    {
        return new self(
            user: $user,
            company: $this->company,
            requestId: $this->requestId,
            metadata: $this->metadata
        );
    }

    public function withCompany(?Company $company): self
    {
        return new self(
            user: $this->user,
            company: $company,
            requestId: $this->requestId,
            metadata: $this->metadata
        );
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            user: $this->user,
            company: $this->company,
            requestId: $this->requestId,
            metadata: array_merge($this->metadata, $metadata)
        );
    }

    // RLS context
    public function setDatabaseContext(): void
    {
        if ($this->hasCompany()) {
            $escapedCompanyId = addslashes($this->getCompanyId());
            \DB::statement("SET app.current_company_id = '{$escapedCompanyId}'");
        } else {
            \DB::statement("SET app.current_company_id = ''");
        }
        
        if ($this->hasUser()) {
            $escapedUserId = addslashes($this->getUserId());
            \DB::statement("SET app.current_user_id = '{$escapedUserId}'");
        } else {
            \DB::statement("SET app.current_user_id = ''");
        }
    }

    // Validation
    public function validate(): bool
    {
        // Basic validation - ensure we have required context for operations
        if ($this->isSystemContext()) {
            return true; // System operations don't require user/company
        }

        if (!$this->hasUser()) {
            return false; // Non-system operations require a user
        }

        return true;
    }

    public function validateForCompanyOperation(): bool
    {
        return $this->validate() && $this->hasCompany() && $this->canAccessCompany();
    }

    // Debugging
    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'company_id' => $this->getCompanyId(),
            'request_id' => $this->getRequestId(),
            'has_user' => $this->hasUser(),
            'has_company' => $this->hasCompany(),
            'is_system' => $this->isSystemContext(),
            'metadata' => $this->getMetadata(),
        ];
    }
}
