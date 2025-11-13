<?php

namespace App\Http\Requests;

use App\Services\ServiceContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

abstract class BaseFormRequest extends FormRequest
{
    protected ?ServiceContext $serviceContext = null;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Initialize ServiceContext for audit trails
        $this->serviceContext = ServiceContext::fromRequest($this);

        // Set company context for RLS
        if ($this->serviceContext->getCompanyId()) {
            $this->setCompanyContext($this->serviceContext->getCompanyId());
        }
    }

    /**
     * Get the proper failed validation response for the request.
     */
    protected function failedValidation(Validator $validator): void
    {
        // Log validation failure for audit trail
        $this->logValidationFailure($validator);

        throw new HttpResponseException(
            $this->formatValidationResponse($validator)
        );
    }

    /**
     * Format validation errors in standard JSON response format.
     */
    protected function formatValidationResponse(Validator $validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'meta' => [
                'request_id' => $this->serviceContext?->getRequestId(),
                'timestamp' => now()->toISOString(),
            ],
        ], 422);
    }

    /**
     * Get the service context for this request.
     */
    protected function getServiceContext(): ServiceContext
    {
        return $this->serviceContext ?? ServiceContext::fromRequest($this);
    }

    /**
     * Get the current company ID from authenticated user.
     */
    protected function getCurrentCompanyId(): ?string
    {
        return $this->getServiceContext()->getCompanyId();
    }

    /**
     * Check if user has permission for the current company context.
     */
    protected function hasCompanyPermission(string $permission): bool
    {
        $context = $this->getServiceContext();

        if (! $context->hasUser() || ! $context->hasCompany()) {
            return false;
        }

        return $context->hasPermission($permission) &&
               $context->canAccessCompany($context->getCompanyId());
    }

    /**
     * Validate RLS context for financial operations.
     */
    protected function validateRlsContext(): bool
    {
        $context = $this->getServiceContext();

        return $context->hasPermission('rls.context') &&
               $context->hasCompany() &&
               $context->getCompanyId() !== null;
    }

    /**
     * Set the company context for database operations.
     */
    protected function setCompanyContext(string $companyId): void
    {
        // Set PostgreSQL session variable for RLS policies
        $this->attributes->set('company_id', $companyId);

        // Set database session variable for RLS
        try {
            \DB::statement('SET app.current_company_id = ?', [$companyId]);
        } catch (\Exception $e) {
            \Log::warning('Failed to set company context for RLS', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get UUID validation rule for primary keys.
     */
    protected function uuidRule(): string
    {
        return 'required|uuid|exists:public.companies,id';
    }

    /**
     * Get company-scoped UUID validation rule.
     */
    protected function companyUuidRule(string $table, string $column = 'id'): string
    {
        $companyId = $this->getCurrentCompanyId();

        return [
            'required',
            'uuid',
            Rule::exists($table, $column)->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }),
        ];
    }

    /**
     * Validate company access for resource operations.
     */
    protected function validateCompanyAccess(string $resourceId, string $table): bool
    {
        $companyId = $this->getCurrentCompanyId();

        if (! $companyId) {
            return false;
        }

        return \DB::table($table)
            ->where('id', $resourceId)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * Get audit context for logging.
     */
    protected function getAuditContext(): array
    {
        return $this->getServiceContext()->getAuditContext();
    }

    /**
     * Log validation failure for audit trail.
     */
    protected function logValidationFailure(Validator $validator): void
    {
        $context = $this->getAuditContext();

        \Log::warning('Form validation failed', [
            ...$context,
            'validation_errors' => $validator->errors()->toArray(),
            'request_data' => $this->except(['password', 'password_confirmation', 'token']),
        ]);
    }

    /**
     * Common validation rules for UUID primary keys.
     */
    protected function getCommonUuidRules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'company_id' => ['required', 'uuid', 'exists:public.companies,id'],
        ];
    }

    /**
     * Common validation rules for financial operations.
     */
    protected function getFinancialValidationRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Validate that user can perform operations on the specified company.
     */
    protected function validateUserCompanyAccess(string $companyId): bool
    {
        $context = $this->getServiceContext();

        return $context->hasUser() &&
               $context->canAccessCompany($companyId);
    }

    /**
     * Get pagination rules for list requests.
     */
    protected function getPaginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'sort' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Sanitize request data for logging.
     */
    protected function sanitizeForLogging(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'ssn',
            'bank_account',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }

        // Remove large data that isn't useful for auditing
        unset($data['_token'], $data['_method'], $data['files']);

        return $data;
    }
}
