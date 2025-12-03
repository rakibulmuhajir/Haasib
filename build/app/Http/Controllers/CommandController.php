<?php

namespace App\Http\Controllers;

use App\Constants\Tables;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Services\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandController extends Controller
{
    /**
     * Commands that work without company context (platform-level actions)
     */
    private const GLOBAL_COMMANDS = [
        'company.list',
        'company.create',
        'company.switch',
    ];

    /**
     * Commands that reference a specific company but don't require being "in" it
     */
    private const COMPANY_TARGET_COMMANDS = [
        'company.view',
        'company.delete',
    ];

    /**
     * Maximum companies a user can create (abuse prevention)
     */
    private const MAX_COMPANIES_PER_USER = 10;

    public function __invoke(Request $request): JsonResponse
    {
        $action = $request->header('X-Action');
        $user = $request->user();
        $commandBus = app(CommandBus::class);

        // Validate action format
        if (!$action || !preg_match('/^[a-z]+\.[a-z-]+$/', $action)) {
            return $this->error('BAD_REQUEST', 'Invalid or missing X-Action header', 400);
        }

        // Check command exists
        if (!$commandBus->has($action)) {
            return $this->unknownCommandError($action, $commandBus->registered());
        }

        // Authentication required for all commands
        if (!$user) {
            return $this->error('UNAUTHORIZED', 'Authentication required', 401);
        }

        // Get company context
        $company = CompanyContext::getCompany();

        // Validate company context requirements
        $contextCheck = $this->validateCompanyContext($action, $company);
        if ($contextCheck !== true) {
            return $contextCheck;
        }

        // Authorization
        $authCheck = $this->authorize($action, $user, $company, $request->input('params', []));
        if ($authCheck !== true) {
            return $authCheck;
        }

        // Idempotency check (mutations only)
        $idemKey = $request->header('X-Idempotency-Key');
        $isQuery = $this->isQueryAction($action);

        if ($idemKey && !$isQuery) {
            $previous = $this->checkIdempotency($idemKey);
            if ($previous) {
                return response()->json([
                    'ok' => true,
                    'replayed' => true,
                    ...$previous,
                ], 200);
            }
        }

        // Execute command
        try {
            $params = $request->input('params', []);
            $skipPermission = $this->shouldSkipPermissionCheck($action);

            $result = $commandBus->dispatch($action, $params, $user, $skipPermission);

            // Store idempotency record for mutations
            if ($idemKey && !$isQuery) {
                $this->storeIdempotency($idemKey, $result);
            }

            return $this->formatResponse($result, $action);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'VALIDATION',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'FORBIDDEN',
                'message' => $e->getMessage(),
            ], 403);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Record not found',
            ], 404);

        } catch (\Exception $e) {
            return $this->handleException($e, $action, $request, $user, $company);
        }
    }

    /**
     * Validate that company context is present when required
     */
    private function validateCompanyContext(string $action, ?Company $company): true|JsonResponse
    {
        $requiresContext = !in_array($action, self::GLOBAL_COMMANDS, true)
                        && !in_array($action, self::COMPANY_TARGET_COMMANDS, true);

        if ($requiresContext && !$company) {
            return $this->error(
                'BAD_REQUEST',
                'Company context required. Set X-Company-Slug header or switch to a company.',
                400
            );
        }

        return true;
    }

    /**
     * Authorize the action
     */
    private function authorize(
        string $action,
        mixed $user,
        ?Company $company,
        array $params
    ): true|JsonResponse {
        // God-mode bypasses all checks
        if (method_exists($user, 'isGodMode') && $user->isGodMode()) {
            return true;
        }

        return match ($action) {
            'company.create' => $this->authorizeCompanyCreate($user),
            'company.delete' => $this->authorizeCompanyDelete($user, $params),
            'company.view' => $this->authorizeCompanyAccess($user, $params),
            'company.switch' => $this->authorizeCompanyAccess($user, $params),
            default => true, // Let CommandBus handle via Spatie permissions
        };
    }

    /**
     * Any authenticated user can create a company (with limit)
     */
    private function authorizeCompanyCreate(mixed $user): true|JsonResponse
    {
        $companyCount = $user->companies()->count();

        if ($companyCount >= self::MAX_COMPANIES_PER_USER) {
            return $this->error(
                'LIMIT_REACHED',
                'Maximum of ' . self::MAX_COMPANIES_PER_USER . ' companies per user',
                403
            );
        }

        return true;
    }

    /**
     * Only owner can delete a company
     */
    private function authorizeCompanyDelete(mixed $user, array $params): true|JsonResponse
    {
        $slug = $params['slug'] ?? null;

        if (!$slug) {
            return $this->error('BAD_REQUEST', 'Company slug required', 400);
        }

        $company = Company::where('slug', $slug)->first();

        if (!$company) {
            return $this->error('NOT_FOUND', 'Company not found', 404);
        }

        if (!$this->userHasRoleInCompany($user, $company, 'owner')) {
            return $this->error('FORBIDDEN', 'Owner role required to delete company', 403);
        }

        return true;
    }

    /**
     * User must have access to the target company
     */
    private function authorizeCompanyAccess(mixed $user, array $params): true|JsonResponse
    {
        $slug = $params['slug'] ?? null;

        if (!$slug) {
            return $this->error('BAD_REQUEST', 'Company slug required', 400);
        }

        $company = Company::where('slug', $slug)->first();

        if (!$company) {
            return $this->error('NOT_FOUND', 'Company not found', 404);
        }

        $hasAccess = $user->companies()->where('companies.id', $company->id)->exists();

        if (!$hasAccess) {
            return $this->error('FORBIDDEN', 'You do not have access to this company', 403);
        }

        return true;
    }

    /**
     * Check if user has a specific role in a company
     */
    private function userHasRoleInCompany(mixed $user, Company $company, string $role): bool
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($company->id);
            return $user->hasRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }

    /**
     * Determine if action is a read-only query
     */
    private function isQueryAction(string $action): bool
    {
        return str_ends_with($action, '.list')
            || str_ends_with($action, '.view')
            || str_starts_with($action, 'report.');
    }

    /**
     * Determine if CommandBus should skip Spatie permission check
     */
    private function shouldSkipPermissionCheck(string $action): bool
    {
        return in_array($action, self::GLOBAL_COMMANDS, true)
            || in_array($action, self::COMPANY_TARGET_COMMANDS, true);
    }

    /**
     * Format successful response
     */
    private function formatResponse(array $result, string $action): JsonResponse
    {
        if ($this->isQueryAction($action)) {
            return response()
                ->json([
                    'ok' => true,
                    'data' => $result['data'] ?? $result,
                    'meta' => $result['meta'] ?? null,
                ], 200)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Success',
            'data' => $result['data'] ?? null,
            'redirect' => $result['redirect'] ?? null,
            'undo' => $result['undo'] ?? null,
        ], 201);
    }

    /**
     * Check for previous idempotent request
     */
    private function checkIdempotency(string $key): ?array
    {
        $record = DB::table(Tables::COMMAND_IDEMPOTENCY)
            ->where('key', hash('sha256', $key))
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        return $record ? json_decode($record->result, true) : null;
    }

    /**
     * Store idempotency record
     */
    private function storeIdempotency(string $key, array $result): void
    {
        DB::table(Tables::COMMAND_IDEMPOTENCY)->updateOrInsert(
            ['key' => hash('sha256', $key)],
            [
                'result' => json_encode($result),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Handle unknown command with suggestions
     */
    private function unknownCommandError(string $action, array $registered): JsonResponse
    {
        $similar = collect($registered)
            ->filter(fn($cmd) => levenshtein($action, $cmd) <= 3)
            ->take(3)
            ->values()
            ->all();

        $message = "Unknown command: {$action}";

        if (!empty($similar)) {
            $message .= '. Did you mean: ' . implode(', ', $similar) . '?';
        }

        return $this->error('NOT_FOUND', $message, 404);
    }

    /**
     * Handle unexpected exceptions
     */
    private function handleException(
        \Exception $e,
        string $action,
        Request $request,
        mixed $user,
        ?Company $company
    ): JsonResponse {
        Log::error("Command execution failed: {$action}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'params' => $request->input('params'),
            'user_id' => $user?->id,
            'company_id' => $company?->id,
        ]);

        $message = app()->environment('production')
            ? 'An error occurred'
            : $e->getMessage();

        return $this->error('SERVER_ERROR', $message, 500);
    }

    /**
     * Build error response
     */
    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
