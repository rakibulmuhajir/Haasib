<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Haasib Command Palette API',
                'description' => 'Keyboard-first command interface for Haasib with natural language processing and contextual suggestions',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Haasib API Support',
                    'email' => 'api@haasib.com',
                ],
                'license' => [
                    'name' => 'MIT',
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url').'/api',
                    'description' => 'Production API Server',
                ],
            ],
            'paths' => $this->getApiPaths(),
            'components' => $this->getComponents(),
            'security' => [
                [
                    'sessionAuth' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => 'haasib_session',
                    ],
                ],
            ],
            'tags' => [
                [
                    'name' => 'Commands',
                    'description' => 'Command palette endpoints',
                ],
                [
                    'name' => 'Templates',
                    'description' => 'Command template management',
                ],
                [
                    'name' => 'History',
                    'description' => 'Command execution history',
                ],
            ],
        ]);
    }

    private function getApiPaths(): array
    {
        return [
            '/commands' => [
                'get' => [
                    'tags' => ['Commands'],
                    'summary' => 'Get available commands',
                    'description' => 'Retrieve a list of available commands for the authenticated user in their current company context',
                    'parameters' => [
                        [
                            'name' => 'category',
                            'in' => 'query',
                            'description' => 'Filter commands by category',
                            'schema' => [
                                'type' => 'string',
                                'example' => 'invoice',
                            ],
                        ],
                        [
                            'name' => 'search',
                            'in' => 'query',
                            'description' => 'Search commands by name or description',
                            'schema' => [
                                'type' => 'string',
                                'example' => 'create invoice',
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CommandsResponse',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            '$ref' => '#/components/responses/Unauthorized',
                        ],
                        '403' => [
                            '$ref' => '#/components/responses/Forbidden',
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
            '/commands/suggestions' => [
                'post' => [
                    'tags' => ['Commands'],
                    'summary' => 'Get command suggestions',
                    'description' => 'Get contextual command suggestions based on user input and current context',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/SuggestionsRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/SuggestionsResponse',
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            '$ref' => '#/components/responses/ValidationError',
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
            '/commands/execute' => [
                'post' => [
                    'tags' => ['Commands'],
                    'summary' => 'Execute a command',
                    'description' => 'Execute a command with specified parameters. Supports idempotency and audit logging',
                    'parameters' => [
                        [
                            'name' => 'Idempotency-Key',
                            'in' => 'header',
                            'description' => 'Unique key to prevent duplicate executions',
                            'schema' => [
                                'type' => 'string',
                                'example' => 'uuid-v4-string',
                            ],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/ExecuteRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Command executed successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ExecuteResponse',
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            '$ref' => '#/components/responses/ValidationError',
                        ],
                        '409' => [
                            'description' => 'Command execution already in progress',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ConflictResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
            '/commands/batch-execute' => [
                'post' => [
                    'tags' => ['Commands'],
                    'summary' => 'Execute multiple commands',
                    'description' => 'Execute multiple commands in a single request with batch processing',
                    'parameters' => [
                        [
                            'name' => 'Idempotency-Key',
                            'in' => 'header',
                            'description' => 'Unique key to prevent duplicate batch executions',
                            'schema' => [
                                'type' => 'string',
                                'example' => 'batch-uuid-v4-string',
                            ],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/BatchExecuteRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Batch execution completed',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/BatchExecuteResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
            '/commands/history' => [
                'get' => [
                    'tags' => ['History'],
                    'summary' => 'Get command execution history',
                    'description' => 'Retrieve paginated command execution history for the current user',
                    'parameters' => [
                        [
                            'name' => 'command_id',
                            'in' => 'query',
                            'description' => 'Filter by specific command',
                            'schema' => [
                                'type' => 'string',
                                'format' => 'uuid',
                            ],
                        ],
                        [
                            'name' => 'status',
                            'in' => 'query',
                            'description' => 'Filter by execution status',
                            'schema' => [
                                'type' => 'string',
                                'enum' => ['success', 'failed', 'partial'],
                            ],
                        ],
                        [
                            'name' => 'date_from',
                            'in' => 'query',
                            'description' => 'Filter by date from',
                            'schema' => [
                                'type' => 'string',
                                'format' => 'date',
                            ],
                        ],
                        [
                            'name' => 'date_to',
                            'in' => 'query',
                            'description' => 'Filter by date to',
                            'schema' => [
                                'type' => 'string',
                                'format' => 'date',
                            ],
                        ],
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'description' => 'Number of items per page',
                            'schema' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 100,
                                'default' => 20,
                            ],
                        ],
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'Page number',
                            'schema' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'default' => 1,
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/HistoryResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
            '/commands/templates' => [
                'get' => [
                    'tags' => ['Templates'],
                    'summary' => 'Get command templates',
                    'description' => 'Retrieve command templates for the current user including shared templates',
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/TemplatesResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
                'post' => [
                    'tags' => ['Templates'],
                    'summary' => 'Create command template',
                    'description' => 'Create a new command template with saved parameter values',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/CreateTemplateRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Template created successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/TemplateResponse',
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            '$ref' => '#/components/responses/ValidationError',
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
            '/commands/templates/{id}' => [
                'put' => [
                    'tags' => ['Templates'],
                    'summary' => 'Update command template',
                    'description' => 'Update an existing command template',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'Template ID',
                            'schema' => [
                                'type' => 'string',
                                'format' => 'uuid',
                            ],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/UpdateTemplateRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Template updated successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/TemplateResponse',
                                    ],
                                ],
                            ],
                        ],
                        '404' => [
                            '$ref' => '#/components/responses/NotFound',
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
                'delete' => [
                    'tags' => ['Templates'],
                    'summary' => 'Delete command template',
                    'description' => 'Delete an existing command template',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'Template ID',
                            'schema' => [
                                'type' => 'string',
                                'format' => 'uuid',
                            ],
                        ],
                    ],
                    'responses' => [
                        '204' => [
                            'description' => 'Template deleted successfully',
                        ],
                        '404' => [
                            '$ref' => '#/components/responses/NotFound',
                        ],
                    ],
                    'security' => [
                        ['sessionAuth' => []],
                    ],
                ],
            ],
        ];
    }

    private function getComponents(): array
    {
        return [
            'schemas' => $this->getSchemas(),
            'responses' => $this->getResponses(),
        ];
    }

    private function getSchemas(): array
    {
        return [
            'Command' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                        'description' => 'Command ID',
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Command name (e.g., invoice.create)',
                        'example' => 'invoice.create',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Command description',
                        'example' => 'Create a new invoice',
                    ],
                    'parameters' => [
                        'type' => 'array',
                        'description' => 'Command parameters schema',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'type' => ['type' => 'string'],
                                'required' => ['type' => 'boolean'],
                                'description' => ['type' => 'string'],
                                'default' => [],
                            ],
                        ],
                    ],
                    'required_permissions' => [
                        'type' => 'array',
                        'description' => 'Required permissions',
                        'items' => ['type' => 'string'],
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Command category',
                        'example' => 'invoice',
                    ],
                ],
            ],
            'CommandsResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => [
                            '$ref' => '#/components/schemas/Command',
                        ],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer'],
                            'categories' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'SuggestionsRequest' => [
                'type' => 'object',
                'required' => ['input'],
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'User input for suggestions',
                        'minLength' => 2,
                        'maxLength' => 255,
                        'example' => 'create invoice',
                    ],
                    'context' => [
                        'type' => 'object',
                        'description' => 'Current context for suggestions',
                        'properties' => [
                            'page' => [
                                'type' => 'string',
                                'description' => 'Current page',
                                'example' => 'dashboard',
                            ],
                            'recent_actions' => [
                                'type' => 'array',
                                'description' => 'Recent command executions',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'SuggestionsResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string', 'format' => 'uuid'],
                                'name' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'confidence' => ['type' => 'number', 'format' => 'float'],
                                'match_type' => ['type' => 'string'],
                                'estimated_duration' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'input' => ['type' => 'string'],
                            'count' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'ExecuteRequest' => [
                'type' => 'object',
                'required' => ['command_name'],
                'properties' => [
                    'command_name' => [
                        'type' => 'string',
                        'description' => 'Name of command to execute',
                        'example' => 'invoice.create',
                    ],
                    'parameters' => [
                        'type' => 'object',
                        'description' => 'Command parameters',
                        'example' => [
                            'customer_id' => 'uuid-value',
                            'amount' => 100.50,
                        ],
                    ],
                ],
            ],
            'ExecuteResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'execution_id' => ['type' => 'string', 'format' => 'uuid'],
                    'result' => ['type' => 'object'],
                    'audit_reference' => ['type' => 'string', 'format' => 'uuid'],
                ],
            ],
            'HistoryResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string', 'format' => 'uuid'],
                                'command_id' => ['type' => 'string', 'format' => 'uuid'],
                                'command_name' => ['type' => 'string'],
                                'executed_at' => ['type' => 'string', 'format' => 'date-time'],
                                'input_text' => ['type' => 'string'],
                                'parameters_used' => ['type' => 'object'],
                                'execution_status' => ['type' => 'string'],
                                'result_summary' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'TemplateResponse' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'command_id' => ['type' => 'string', 'format' => 'uuid'],
                    'command_name' => ['type' => 'string'],
                    'parameter_values' => ['type' => 'object'],
                    'is_shared' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
        ];
    }

    private function getResponses(): array
    {
        return [
            'Unauthorized' => [
                'description' => 'Unauthorized access',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'Forbidden' => [
                'description' => 'Access forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'NotFound' => [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'ValidationError' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string'],
                                'errors' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ],
            'ConflictResponse' => [
                'description' => 'Conflict - Idempotency key already used',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'idempotency_collision' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
