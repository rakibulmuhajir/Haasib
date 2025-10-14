<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExecuteCommandRequest;
use App\Http\Requests\Api\GetSuggestionsRequest;
use App\Models\CommandHistory;
use App\Models\CommandTemplate;
use App\Models\Company;
use App\Models\User;
use App\Services\CommandExecutionService;
use App\Services\CommandRegistryService;
use App\Services\CommandSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommandController extends Controller
{
    private CommandRegistryService $commandRegistry;

    private CommandSuggestionService $suggestionService;

    private CommandExecutionService $executionService;

    public function __construct(
        CommandRegistryService $commandRegistry,
        CommandSuggestionService $suggestionService,
        CommandExecutionService $executionService
    ) {
        $this->commandRegistry = $commandRegistry;
        $this->suggestionService = $suggestionService;
        $this->executionService = $executionService;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $category = $request->query('category');
        $search = $request->query('search');

        $commands = $this->commandRegistry->getAvailableCommands($company)
            ->filter(fn ($command) => $command->userHasPermission($user));

        if ($category) {
            $commands = $commands->filter(function ($command) use ($category) {
                return str_starts_with($command->name, $category.'.');
            });
        }

        if ($search) {
            $commands = $commands->filter(function ($command) use ($search) {
                $search = strtolower($search);

                return str_contains(strtolower($command->name), $search) ||
                       str_contains(strtolower($command->description), $search);
            });
        }

        return response()->json([
            'data' => $commands->values()->map(function ($command) {
                return [
                    'id' => $command->id,
                    'name' => $command->name,
                    'description' => $command->description,
                    'parameters' => $command->parameters,
                    'required_permissions' => $command->required_permissions,
                    'category' => $this->extractCategory($command->name),
                ];
            }),
            'meta' => [
                'total' => $commands->count(),
                'categories' => $this->getAvailableCategories($commands),
            ],
        ]);
    }

    public function suggestions(GetSuggestionsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $input = $request->get('input');
        $context = $request->get('context', []);

        $suggestions = $this->suggestionService->getSuggestions($company, $user, $input, $context);

        return response()->json([
            'data' => $suggestions,
            'meta' => [
                'input' => $input,
                'count' => count($suggestions),
            ],
        ]);
    }

    public function execute(ExecuteCommandRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $commandName = $request->get('command_name');
        $parameters = $request->get('parameters', []);
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $result = $this->executionService->executeCommand(
                $company,
                $user,
                $commandName,
                $parameters,
                $idempotencyKey
            );

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function batchExecute(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $request->validate([
            'commands' => 'required|array|min:1|max:10',
            'commands.*.name' => 'required|string',
            'commands.*.parameters' => 'array',
            'commands.*.idempotency_key' => 'string',
        ]);

        $commands = $request->get('commands');

        try {
            $result = $this->executionService->executeBatchCommands($company, $user, $commands);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $request->validate([
            'command_id' => 'uuid',
            'status' => 'in:success,failed,partial',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
        ]);

        $query = CommandHistory::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with(['command', 'user'])
            ->orderBy('executed_at', 'desc');

        if ($commandId = $request->get('command_id')) {
            $query->where('command_id', $commandId);
        }

        if ($status = $request->get('status')) {
            $query->where('execution_status', $status);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->where('executed_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->where('executed_at', '<=', $dateTo);
        }

        $perPage = $request->get('per_page', 20);
        $history = $query->paginate($perPage);

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $templates = CommandTemplate::with(['command', 'user'])
            ->where('company_id', $company->id)
            ->accessibleBy($user)
            ->orderBy('is_shared', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'command_id' => $template->command_id,
                    'command_name' => $template->command->name,
                    'parameter_values' => $template->parameter_values,
                    'is_shared' => $template->is_shared,
                    'created_by' => $template->user->name,
                    'created_at' => $template->created_at->toISOString(),
                ];
            }),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $request->validate([
            'command_id' => 'required|uuid|exists:commands,id',
            'name' => 'required|string|max:255',
            'parameter_values' => 'required|array',
            'is_shared' => 'boolean',
        ]);

        $template = CommandTemplate::create([
            'command_id' => $request->get('command_id'),
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => $request->get('name'),
            'parameter_values' => $request->get('parameter_values'),
            'is_shared' => $request->get('is_shared', false),
        ]);

        return response()->json([
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'command_id' => $template->command_id,
                'parameter_values' => $template->parameter_values,
                'is_shared' => $template->is_shared,
                'created_at' => $template->created_at->toISOString(),
            ],
        ], 201);
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $this->getCompanyFromRequest($request);

        $template = CommandTemplate::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $request->validate([
            'name' => 'string|max:255',
            'parameter_values' => 'array',
            'is_shared' => 'boolean',
        ]);

        $template->update($request->only(['name', 'parameter_values', 'is_shared']));

        return response()->json([
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'command_id' => $template->command_id,
                'parameter_values' => $template->parameter_values,
                'is_shared' => $template->is_shared,
                'updated_at' => $template->updated_at->toISOString(),
            ],
        ]);
    }

    public function destroyTemplate(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $company = request()->attributes->get('company');

        $template = CommandTemplate::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $template->delete();

        return response()->json(null, 204);
    }

    private function getCompanyFromRequest(Request $request): Company
    {
        return $request->attributes->get('company');
    }

    private function extractCategory(string $commandName): string
    {
        $parts = explode('.', $commandName);

        return $parts[0] ?? 'general';
    }

    private function getAvailableCategories($commands): array
    {
        return $commands
            ->map(fn ($command) => $this->extractCategory($command->name))
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
