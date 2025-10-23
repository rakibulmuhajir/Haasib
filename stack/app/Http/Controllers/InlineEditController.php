<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InlineEditController extends Controller
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $modelMap;

    public function __construct()
    {
        $this->modelMap = [
            'customer' => [
                'model' => Customer::class,
                'validationRules' => [
                    'name' => ['sometimes', 'required', 'string', 'max:255'],
                    'email' => ['sometimes', 'nullable', 'email', 'max:255'],
                    'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
                    'website' => ['sometimes', 'nullable', 'url', 'max:255'],
                    'customer_type' => ['sometimes', 'required', 'string', 'max:50'],
                    'status' => ['sometimes', 'required', 'string', 'max:50'],
                    'tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
                    'tax_exempt' => ['sometimes', 'boolean'],
                    'payment_terms' => ['sometimes', 'nullable', 'string', 'max:50'],
                    'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                    'customer_number' => ['sometimes', 'nullable', 'string', 'max:50'],
                    'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
                    'country_id' => ['sometimes', 'nullable', 'uuid'],
                    'currency_id' => ['sometimes', 'nullable', 'uuid'],
                    'address.address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
                    'address.address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
                    'address.city' => ['sometimes', 'nullable', 'string', 'max:100'],
                    'address.state_province' => ['sometimes', 'nullable', 'string', 'max:100'],
                    'address.postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
                ],
                'fieldHandlers' => [
                    'address' => function ($value, $model) {
                        $existing = is_array($model->billing_address)
                            ? $model->billing_address
                            : json_decode($model->billing_address ?: '{}', true);

                        $merged = array_filter(array_merge($existing ?? [], $value ?? []), fn ($v) => $v !== null && $v !== '');

                        return ['billing_address' => ! empty($merged) ? json_encode($merged) : null];
                    },
                ],
            ],
            'invoice' => [
                'model' => Invoice::class,
                'validationRules' => [
                    'invoice_number' => ['sometimes', 'required', 'string', 'max:50'],
                    'invoice_date' => ['sometimes', 'required', 'date'],
                    'due_date' => ['sometimes', 'required', 'date', 'after_or_equal:invoice_date'],
                    'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
                    'status' => ['sometimes', 'required', 'string', 'max:50'],
                    'payment_terms' => ['sometimes', 'nullable', 'string', 'max:50'],
                ],
            ],
            'user' => [
                'model' => User::class,
                'validationRules' => [
                    'name' => ['sometimes', 'required', 'string', 'max:255'],
                    'email' => ['sometimes', 'required', 'email', 'max:255'],
                ],
            ],
            'company' => [
                'model' => Company::class,
                'validationRules' => function (array $fields, Company $company) {
                    return [
                        'name' => [
                            'sometimes',
                            'required',
                            'string',
                            'max:255',
                            'min:2',
                            'regex:/^[^<>&]*$/',
                            Rule::unique('auth.companies', 'name')->ignore($company->id),
                        ],
                        'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
                        'country' => ['sometimes', 'nullable', 'string', 'size:2'],
                        'base_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
                        'base.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
                        'timezone' => ['sometimes', 'nullable', 'string', 'max:50'],
                        'language' => ['sometimes', 'nullable', 'string', 'max:10'],
                        'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
                        'settings' => ['sometimes', 'array'],
                        'settings.limits.max_users' => ['sometimes', 'nullable', 'integer', 'min:1'],
                        'settings.limits.max_storage' => ['sometimes', 'nullable', 'integer', 'min:0'],
                    ];
                },
                'fieldHandlers' => [
                    'name' => function ($value, Company $company) {
                        $base = Str::slug((string) $value) ?: Str::slug((string) Str::uuid());
                        $slug = $base;
                        $i = 1;

                        while (
                            Company::where('slug', $slug)
                                ->where('id', '!=', $company->id)
                                ->exists()
                        ) {
                            $slug = $base.'-'.$i++;
                        }

                        return [
                            'name' => $value,
                            'slug' => $slug,
                        ];
                    },
                    'base' => function ($value, Company $company) {
                        // Handle nested fields like base.currency
                        // When frontend sends base.currency, $value will be the currency string
                        return ['base_currency' => strtoupper((string) $value)];
                    },
                    'country' => function ($value) {
                        return ['country' => strtoupper((string) $value)];
                    },
                    'base_currency' => function ($value) {
                        return ['base_currency' => strtoupper((string) $value)];
                    },
                    'language' => function ($value) {
                        return ['language' => strtolower((string) $value)];
                    },
                    'locale' => function ($value) {
                        if (! $value) {
                            return ['locale' => null];
                        }

                        $normalized = str_replace('-', '_', (string) $value);

                        return ['locale' => $normalized];
                    },
                    'timezone' => function ($value) {
                        return ['timezone' => $value ? trim((string) $value) : null];
                    },
                    'settings' => function ($value, Company $company) {
                        $settings = $company->settings ?? [];
                        $merged = array_replace_recursive($settings, $value ?? []);

                        return ['settings' => $merged];
                    },
                ],
            ],
        ];
    }

    public function patch(Request $request)
    {
        Log::info('🔍 [InlineEditController DEBUG] Request received', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toISOString(),
        ]);

        $payload = $request->validate([
            'model' => 'required|string',
            'id' => 'required',
            'fields' => 'required|array',
        ]);

        Log::info('📋 [InlineEditController DEBUG] Request validated', [
            'payload' => $payload,
        ]);

        $modelKey = strtolower($payload['model']);

        if (! isset($this->modelMap[$modelKey])) {
            Log::warning('❌ [InlineEditController DEBUG] Unsupported model type', [
                'modelKey' => $modelKey,
                'availableModels' => array_keys($this->modelMap),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unsupported model type',
            ], 422);
        }

        $modelConfig = $this->modelMap[$modelKey];
        $modelClass = $modelConfig['model'];

        Log::info('🔍 [InlineEditController DEBUG] Looking up resource', [
            'modelClass' => $modelClass,
            'id' => $payload['id'],
        ]);

        /** @var \Illuminate\Database\Eloquent\Model|null $resource */
        $resource = $modelClass::find($payload['id']);

        if (! $resource) {
            Log::warning('❌ [InlineEditController DEBUG] Resource not found', [
                'modelClass' => $modelClass,
                'id' => $payload['id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        Log::info('🔐 [InlineEditController DEBUG] Checking authorization', [
            'user_id' => $request->user()?->id,
            'resource_type' => get_class($resource),
            'resource_id' => $resource->id,
        ]);

        $this->authorize('update', $resource);

        Log::info('✅ [InlineEditController DEBUG] Authorization passed');

        Log::info('🔄 [InlineEditController DEBUG] Starting database transaction');

        DB::beginTransaction();

        try {
            Log::info('📦 [InlineEditController DEBUG] Processing fields', [
                'fields' => $payload['fields'],
                'field_count' => count($payload['fields']),
            ]);

            $fields = $payload['fields'];
            $updateData = [];

            foreach ($fields as $field => $value) {
                Log::info('⚙️ [InlineEditController DEBUG] Processing field', [
                    'field' => $field,
                    'value' => $value,
                    'value_type' => gettype($value),
                ]);
                $fieldParts = explode('.', $field);
                $rootField = $fieldParts[0];

                if (isset($modelConfig['fieldHandlers'][$rootField]) && is_callable($modelConfig['fieldHandlers'][$rootField])) {
                    Log::info('🔧 [InlineEditController DEBUG] Applying field handler', [
                        'rootField' => $rootField,
                        'handler_exists' => true,
                    ]);
                    $handlerResult = $modelConfig['fieldHandlers'][$rootField]($value, $resource);
                    Log::info('📝 [InlineEditController DEBUG] Field handler result', [
                        'handlerResult' => $handlerResult,
                        'updateData_before' => $updateData,
                    ]);
                    $updateData = array_merge($updateData, $handlerResult ?? []);
                    Log::info('📝 [InlineEditController DEBUG] Update data after handler', [
                        'updateData_after' => $updateData,
                    ]);
                } else {
                    Log::info('📝 [InlineEditController DEBUG] No field handler, using direct value', [
                        'field' => $field,
                        'value' => $value,
                    ]);
                    $updateData[$field] = $value;
                }
            }

            Log::info('✅ [InlineEditController DEBUG] Field processing complete', [
                'final_updateData' => $updateData,
                'updateData_count' => count($updateData),
            ]);

            Log::info('🔍 [InlineEditController DEBUG] Starting validation', [
                'updateData' => $updateData,
                'validationRules' => $this->getValidationRules($modelKey, array_keys($fields), $resource),
            ]);

            $validator = Validator::make(
                $updateData,
                $this->getValidationRules($modelKey, array_keys($fields), $resource)
            );

            if ($validator->fails()) {
                Log::warning('❌ [InlineEditController DEBUG] Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'failed_fields' => array_keys($validator->errors()->toArray()),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            Log::info('✅ [InlineEditController DEBUG] Validation passed, preparing to update model', [
                'resource_class' => get_class($resource),
                'resource_id' => $resource->id,
                'updateData' => $updateData,
            ]);

            $resource->update($updateData);

            Log::info('✅ [InlineEditController DEBUG] Model updated successfully', [
                'resource_class' => get_class($resource),
                'resource_id' => $resource->id,
                'updated_fields' => array_keys($updateData),
            ]);

            DB::commit();

            Log::info('✅ [InlineEditController DEBUG] Database transaction committed successfully');

            Log::info('📊 [InlineEditController DEBUG] Logging successful edit', [
                'user_id' => $request->user()?->id,
                'model' => $modelKey,
                'id' => $resource->id,
                'fields' => array_keys($updateData),
                'fresh_resource' => $resource->fresh()->toArray(),
            ]);

            Log::info('✅ [InlineEditController DEBUG] Preparing successful response', [
                'success' => true,
                'message' => 'Updated successfully',
                'resource_id' => $resource->id,
                'fields' => $updateData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Updated successfully',
                'resource' => $resource->fresh(),
                'fields' => $updateData,
            ]);
        } catch (\Throwable $e) {
            Log::error('💥 [InlineEditController DEBUG] Exception caught, rolling back transaction', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            DB::rollBack();

            Log::error('💥 [InlineEditController DEBUG] Preparing error response', [
                'http_status' => 500,
                'error_message' => $e->getMessage(),
                'payload' => $payload,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|null  $resource
     */
    protected function getValidationRules(string $model, array $fields, $resource = null): array
    {
        $rules = [];
        $modelRules = $this->modelMap[$model]['validationRules'] ?? [];

        if (is_callable($modelRules)) {
            $modelRules = $modelRules($fields, $resource);
        }

        foreach ($fields as $field) {
            if (isset($modelRules[$field])) {
                $rules[$field] = $modelRules[$field];
            }
        }

        return $rules;
    }
}
