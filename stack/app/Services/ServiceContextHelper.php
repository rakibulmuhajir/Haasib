<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Utility class for ServiceContext creation and management.
 *
 * This helper provides convenient methods for creating ServiceContext
 * instances in different environments and scenarios.
 */
class ServiceContextHelper
{
    /**
     * Create ServiceContext from HTTP request.
     */
    public static function fromRequest(Request $request): ServiceContext
    {
        return ServiceContext::fromRequest($request);
    }

    /**
     * Create ServiceContext for console commands.
     */
    public static function forConsole(?string $userId = null, ?string $companyId = null): ServiceContext
    {
        $user = $userId ? User::find($userId) : null;
        $company = $companyId ? Company::find($companyId) : null;

        return new ServiceContext(
            user: $user,
            company: $company,
            ipAddress: '127.0.0.1',
            userAgent: 'Console-Command'
        );
    }

    /**
     * Create ServiceContext for testing environments.
     */
    public static function forTesting(?User $user = null, ?Company $company = null): ServiceContext
    {
        return ServiceContext::forTesting($user, $company);
    }

    /**
     * Create ServiceContext for system operations.
     */
    public static function system(?Company $company = null): ServiceContext
    {
        return ServiceContext::system($company);
    }

    /**
     * Create ServiceContext from job payload.
     */
    public static function fromJobPayload(array $payload): ServiceContext
    {
        $user = isset($payload['user_id']) ? User::find($payload['user_id']) : null;
        $company = isset($payload['company_id']) ? Company::find($payload['company_id']) : null;

        return new ServiceContext(
            user: $user,
            company: $company,
            ipAddress: $payload['ip_address'] ?? null,
            userAgent: $payload['user_agent'] ?? 'Background-Job'
        );
    }

    /**
     * Create ServiceContext for scheduled tasks.
     */
    public static function forScheduledTask(?Company $company = null): ServiceContext
    {
        return new ServiceContext(
            user: null,
            company: $company,
            ipAddress: null,
            userAgent: 'Scheduled-Task'
        );
    }

    /**
     * Create ServiceContext for API requests with enhanced validation.
     */
    public static function forApi(Request $request): ServiceContext
    {
        // Additional validation for API requests
        if (! $request->user()) {
            throw new \InvalidArgumentException('API requests require authenticated user');
        }

        $context = ServiceContext::fromRequest($request);

        // Log API request context for security
        Log::info('API ServiceContext created', [
            'user_id' => $context->getUserId(),
            'company_id' => $context->getCompanyId(),
            'request_id' => $context->getRequestId(),
            'ip' => $request->ip(),
        ]);

        return $context;
    }

    /**
     * Validate and extract context from middleware parameters.
     */
    public static function validateAndCreate(?User $user = null, ?Company $company = null, ?string $ipAddress = null): ServiceContext
    {
        if (! $user) {
            throw new \InvalidArgumentException('User is required for service context');
        }

        if (! $company) {
            // Try to get company from user
            $company = $user->currentCompany();
        }

        if (! $company) {
            throw new \InvalidArgumentException('Company is required for service context');
        }

        // Validate user has access to company
        if (! $user->companies()->where('company_id', $company->id)->wherePivot('is_active', true)->exists()) {
            throw new \InvalidArgumentException('User does not have active access to the specified company');
        }

        return new ServiceContext(
            user: $user,
            company: $company,
            ipAddress: $ipAddress,
            userAgent: 'Direct-Context-Creation'
        );
    }

    /**
     * Create ServiceContext with company switch validation.
     */
    public static function forCompanySwitch(User $user, Company $targetCompany, ?string $ipAddress = null): ServiceContext
    {
        // Validate user can access target company
        if (! $user->companies()->where('company_id', $targetCompany->id)->wherePivot('is_active', true)->exists()) {
            throw new \InvalidArgumentException('User does not have access to target company');
        }

        $context = new ServiceContext(
            user: $user,
            company: $targetCompany,
            ipAddress: $ipAddress,
            userAgent: 'Company-Switch'
        );

        // Log company switch for security
        Log::info('Company switch ServiceContext created', [
            'user_id' => $user->id,
            'previous_company_id' => $user->current_company_id,
            'target_company_id' => $targetCompany->id,
            'request_id' => $context->getRequestId(),
            'ip' => $ipAddress,
        ]);

        return $context;
    }

    /**
     * Create context from command bus dispatch parameters.
     */
    public static function fromCommandDispatch(User $user, Company $company, array $metadata = []): ServiceContext
    {
        return new ServiceContext(
            user: $user,
            company: $company,
            ipAddress: $metadata['ip_address'] ?? null,
            userAgent: $metadata['user_agent'] ?? 'Command-Bus',
            requestId: $metadata['request_id'] ?? null
        );
    }

    /**
     * Get context summary for logging (without sensitive data).
     */
    public static function getContextSummary(ServiceContext $context): array
    {
        return [
            'has_user' => $context->hasUser(),
            'has_company' => $context->hasCompany(),
            'request_id' => $context->getRequestId(),
            'is_super_admin' => $context->isSuperAdmin(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Validate context is properly configured for operations.
     */
    public static function validateContext(ServiceContext $context, bool $requireUser = true, bool $requireCompany = true): void
    {
        if ($requireUser && ! $context->hasUser()) {
            throw new \InvalidArgumentException('User context is required for this operation');
        }

        if ($requireCompany && ! $context->hasCompany()) {
            throw new \InvalidArgumentException('Company context is required for this operation');
        }

        if ($context->hasUser() && $context->hasCompany() && ! $context->canAccessCompany($context->getCompanyId())) {
            throw new \InvalidArgumentException('User does not have access to the company context');
        }
    }
}
