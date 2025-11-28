<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use App\Services\ServiceContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

abstract class BaseFormRequest extends FormRequest
{
    protected ?ServiceContext $serviceContext = null;

    protected function prepareForValidation(): void
    {
        $this->serviceContext = ServiceContext::fromRequest($this);

        if ($this->serviceContext->getCompanyId()) {
            $this->setCompanyContext($this->serviceContext->getCompanyId());
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->logValidationFailure($validator);

        if ($this->isInertiaRequest() && $this->isInertiaVisit()) {
            $response = redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();

            if (in_array($this->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $response->setStatusCode(303);
            }

            throw new HttpResponseException($response);
        }

        throw new HttpResponseException($this->formatValidationResponse($validator));
    }

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

    protected function isInertiaRequest(): bool
    {
        return $this->headers->has('X-Inertia');
    }

    protected function isInertiaVisit(): bool
    {
        return $this->headers->get('X-Inertia') === 'true';
    }

    protected function getServiceContext(): ServiceContext
    {
        return $this->serviceContext ?? ServiceContext::fromRequest($this);
    }

    protected function getCurrentCompanyId(): ?string
    {
        return $this->getServiceContext()->getCompanyId();
    }

    protected function hasCompanyPermission(string $permission): bool
    {
        $context = $this->getServiceContext();

        if (! $context->hasUser() || ! $context->hasCompany()) {
            return false;
        }

        return $context->hasPermission($permission) &&
               $context->canAccessCompany($context->getCompanyId());
    }

    protected function validateRlsContext(): bool
    {
        $context = $this->getServiceContext();

        return $context->hasPermission(Permissions::RLS_CONTEXT) &&
               $context->hasCompany() &&
               $context->getCompanyId() !== null;
    }

    protected function setCompanyContext(string $companyId): void
    {
        $this->attributes->set('company_id', $companyId);

        try {
            $escapedCompanyId = addslashes($companyId);
            \DB::statement("SET app.current_company_id = '{$escapedCompanyId}'");
        } catch (\Exception $e) {
            \Log::warning('Failed to set company context for RLS', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function uuidRule(): string
    {
        return 'required|uuid|exists:public.companies,id';
    }

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

    protected function getAuditContext(): array
    {
        return $this->getServiceContext()->getAuditContext();
    }

    protected function logValidationFailure(Validator $validator): void
    {
        $context = $this->getAuditContext();

        \Log::warning('Form validation failed', [
            ...$context,
            'validation_errors' => $validator->errors()->toArray(),
            'request_data' => $this->except(['password', 'password_confirmation', 'token']),
        ]);
    }

    protected function getCommonUuidRules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'company_id' => ['required', 'uuid', 'exists:public.companies,id'],
        ];
    }

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

    protected function validateUserCompanyAccess(string $companyId): bool
    {
        $context = $this->getServiceContext();

        return $context->hasUser() &&
               $context->canAccessCompany($companyId);
    }

    protected function getPaginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'sort' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

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

        unset($data['_token'], $data['_method'], $data['files']);

        return $data;
    }

    protected function authorizeCustomerOperation(string $action = 'view'): bool
    {
        $permission = match($action) {
            'view' => Permissions::ACCT_CUSTOMERS_VIEW,
            'create' => Permissions::ACCT_CUSTOMERS_CREATE,
            'update' => Permissions::ACCT_CUSTOMERS_UPDATE,
            'delete' => Permissions::ACCT_CUSTOMERS_DELETE,
            'manage_credit' => Permissions::ACCT_CUSTOMERS_MANAGE_CREDIT,
            default => Permissions::ACCT_CUSTOMERS_VIEW,
        };

        return $this->hasCompanyPermission($permission) && $this->validateRlsContext();
    }

    protected function authorizeInvoiceOperation(string $action = 'view'): bool
    {
        $permission = match($action) {
            'view' => Permissions::ACCT_INVOICES_VIEW,
            'create' => Permissions::ACCT_INVOICES_CREATE,
            'update' => Permissions::ACCT_INVOICES_UPDATE,
            'delete' => Permissions::ACCT_INVOICES_DELETE,
            'void' => Permissions::ACCT_INVOICES_VOID,
            'approve' => Permissions::ACCT_INVOICES_APPROVE,
            default => Permissions::ACCT_INVOICES_VIEW,
        };

        return $this->hasCompanyPermission($permission) && $this->validateRlsContext();
    }

    protected function authorizePaymentOperation(string $action = 'view'): bool
    {
        $permission = match($action) {
            'view' => Permissions::ACCT_PAYMENTS_VIEW,
            'create' => Permissions::ACCT_PAYMENTS_CREATE,
            'update' => Permissions::ACCT_PAYMENTS_UPDATE,
            'delete' => Permissions::ACCT_PAYMENTS_DELETE,
            'void' => Permissions::ACCT_PAYMENTS_VOID,
            'process_batch' => Permissions::ACCT_PAYMENTS_PROCESS_BATCH,
            default => Permissions::ACCT_PAYMENTS_VIEW,
        };

        return $this->hasCompanyPermission($permission) && $this->validateRlsContext();
    }

    protected function authorizeCompanyOperation(string $action = 'view'): bool
    {
        $permission = match($action) {
            'view' => Permissions::COMPANIES_VIEW,
            'create' => Permissions::COMPANIES_CREATE,
            'update' => Permissions::COMPANIES_UPDATE,
            'delete' => Permissions::COMPANIES_DELETE,
            'manage_users' => Permissions::COMPANIES_MANAGE_USERS,
            default => Permissions::COMPANIES_VIEW,
        };

        return $this->hasCompanyPermission($permission);
    }

    protected function isSuperAdmin(): bool
    {
        return $this->hasCompanyPermission(Permissions::SYSTEM_ADMIN);
    }

    protected function getAuthorizationFailureResponse(string $action, string $resource): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to {$action} {$resource}",
            'error_code' => 'PERMISSION_DENIED',
            'meta' => [
                'action' => $action,
                'resource' => $resource,
                'user_id' => $this->getServiceContext()->getUserId(),
                'company_id' => $this->getServiceContext()->getCompanyId(),
                'timestamp' => now()->toISOString(),
            ],
        ], 403);
    }
}
