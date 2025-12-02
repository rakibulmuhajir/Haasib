<?php

namespace App\Http\Controllers;

use App\Constants\Tables;
use App\Facades\CompanyContext;
use App\Services\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $action = $request->header('X-Action');
        $commandBus = app(CommandBus::class);
        $debugContext = $this->debugContext($request, $action, null);

        if (! $action || ! preg_match('/^[a-z]+\.[a-z-]+$/', $action)) {
            return $this->error('BAD_REQUEST', 'Invalid or missing X-Action header', 400, $debugContext);
        }

        if (! $commandBus->has($action)) {
            $similar = $this->findSimilarCommands($action, $commandBus->registered());
            $message = "Unknown command: {$action}";
            if (! empty($similar)) {
                $message .= '. Did you mean: '.implode(', ', $similar).'?';
            }

            return $this->error('NOT_FOUND', $message, 404, [
                ...$debugContext,
                'registered_actions' => $commandBus->registered(),
            ]);
        }

        $company = CompanyContext::getCompany();
        $debugContext['company_id'] = $company?->id;

        $companyOptionalCommands = [
            'company.list',
            'company.create',
            'company.switch',
            'company.view',
            'company.delete',
        ];

        if (! $company && ! in_array($action, $companyOptionalCommands)) {
            return $this->error('BAD_REQUEST', 'Company context required. Set X-Company-Slug header.', 400, $debugContext);
        }

        $idemKey = $request->header('X-Idempotency-Key');
        $isQuery = str_ends_with($action, '.list') ||
                   str_ends_with($action, '.view') ||
                   str_starts_with($action, 'report.');

        if ($idemKey && ! $isQuery) {
            $previous = $this->checkIdempotency($idemKey);
            if ($previous) {
                return response()->json([
                    'ok' => true,
                    'replayed' => true,
                    ...$previous,
                    ...($this->includeDebug() ? ['debug' => $debugContext] : []),
                ], 200);
            }
        }

        try {
            $params = $request->input('params', []);
            $skipPermission = $company === null && in_array($action, $companyOptionalCommands);
            $result = $commandBus->dispatch($action, $params, $request->user(), $skipPermission);

            if ($idemKey && ! $isQuery) {
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
            Log::error("Command execution failed: {$action}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->input('params'),
                'user' => $request->user()?->id,
                'company' => $company?->id,
            ]);

            return $this->error(
                'SERVER_ERROR',
                app()->environment('production') ? 'An error occurred' : $e->getMessage(),
                500,
                $debugContext + ['exception' => $e->getMessage()]
            );
        }
    }

    private function formatResponse(array $result, string $action): JsonResponse
    {
        $isQuery = str_ends_with($action, '.list') ||
                   str_ends_with($action, '.view') ||
                   str_starts_with($action, 'report.');

        if ($isQuery) {
            $response = response()->json([
                'ok' => true,
                'data' => $result['data'] ?? $result,
                'meta' => $result['meta'] ?? null,
            ], 200);

            return $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Success',
            'data' => $result['data'] ?? null,
            'redirect' => $result['redirect'] ?? null,
            'undo' => $result['undo'] ?? null,
        ], 201);
    }

    private function checkIdempotency(string $key): ?array
    {
        $record = DB::table(Tables::COMMAND_IDEMPOTENCY)
            ->where('key', hash('sha256', $key))
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        return $record ? json_decode($record->result, true) : null;
    }

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

    private function error(string $code, string $message, int $status, array $debug = []): JsonResponse
    {
        $payload = [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];

        if ($this->includeDebug()) {
            $payload['debug'] = $debug;
            $payload['status'] = $status;
        }

        return response()->json($payload, $status);
    }

    private function debugContext(Request $request, ?string $action, $company): array
    {
        return [
            'user_id' => $request->user()?->id,
            'company_id' => $company?->id,
            'company_slug_header' => $request->header('X-Company-Slug'),
            'action' => $action,
            'params' => $request->input('params', []),
            'headers' => collect($request->headers->all())
                ->only(['x-action', 'x-company-slug', 'x-idempotency-key'])
                ->toArray(),
        ];
    }

    private function includeDebug(): bool
    {
        return config('app.debug', false) && ! app()->environment('production');
    }

    private function findSimilarCommands(string $action, array $registered): array
    {
        return collect($registered)
            ->filter(fn ($cmd) => levenshtein($action, $cmd) <= 3)
            ->take(3)
            ->values()
            ->all();
    }
}
