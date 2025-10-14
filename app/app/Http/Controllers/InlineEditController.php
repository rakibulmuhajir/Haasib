<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InlineEditController extends Controller
{
    protected $modelMap;

    public function __construct()
    {
        $this->modelMap = [
            'customer' => [
                'model' => Customer::class,
                'service' => CustomerService::class,
                'validationRules' => [
                    'name' => 'sometimes|required|string|max:255',
                    'email' => 'sometimes|nullable|email|max:255',
                    'phone' => 'sometimes|nullable|string|max:50',
                    'website' => 'sometimes|nullable|url|max:255',
                    'customer_type' => 'sometimes|required|string|in:individual,small_business,medium_business,large_business,non_profit,government',
                    'status' => 'sometimes|required|string|in:active,inactive,suspended',
                    'tax_number' => 'sometimes|nullable|string|max:100',
                    'tax_exempt' => 'sometimes|boolean',
                    'payment_terms' => 'sometimes|nullable|string|max:50',
                    'credit_limit' => 'sometimes|nullable|numeric|min:0',
                    'customer_number' => 'sometimes|nullable|string|max:50',
                    'notes' => 'sometimes|nullable|string|max:2000',
                    'country_id' => 'sometimes|nullable|uuid|exists:countries,id',
                    'currency_id' => 'sometimes|nullable|uuid|exists:currencies,id',
                    'address.address_line_1' => 'sometimes|nullable|string|max:255',
                    'address.address_line_2' => 'sometimes|nullable|string|max:255',
                    'address.city' => 'sometimes|nullable|string|max:100',
                    'address.state_province' => 'sometimes|nullable|string|max:100',
                    'address.postal_code' => 'sometimes|nullable|string|max:20',
                ],
                'fieldHandlers' => [
                    'status' => function ($value) {
                        return ['is_active' => $value === 'active'];
                    },
                    'address' => function ($value, $model) {
                        $existing = is_array($model->billing_address)
                            ? $model->billing_address
                            : json_decode($model->billing_address ?: '{}', true);

                        $merged = array_filter(array_merge($existing, $value), function ($v) {
                            return $v !== null && $v !== '';
                        });

                        return ['billing_address' => ! empty($merged) ? json_encode($merged) : null];
                    },
                ],
            ],
            // Add more models as needed
            'invoice' => [
                'model' => Invoice::class,
                'validationRules' => [
                    'invoice_number' => 'sometimes|required|string|max:50',
                    'invoice_date' => 'sometimes|required|date',
                    'due_date' => 'sometimes|required|date|after_or_equal:invoice_date',
                    'notes' => 'sometimes|nullable|string|max:2000',
                    'status' => 'sometimes|required|string|in:draft,sent,posted,paid,overdue,cancelled,void',
                    'payment_terms' => 'sometimes|nullable|string|max:50',
                ],
                'fieldHandlers' => [
                    'status' => function ($value, $model) {
                        // Handle status transitions if needed
                        return ['status' => $value];
                    },
                ],
            ],
            'user' => [
                'model' => User::class,
                'validationRules' => [
                    'name' => 'sometimes|required|string|max:255',
                    'email' => 'sometimes|required|email|max:255|unique:users,email',
                ],
            ],
        ];
    }

    public function patch(Request $request)
    {
        $payload = $request->validate([
            'model' => 'required|string',
            'id' => 'required',
            'fields' => 'required|array',
        ]);

        $modelKey = strtolower($payload['model']);

        if (! isset($this->modelMap[$modelKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported model type',
            ], 422);
        }

        $modelConfig = $this->modelMap[$modelKey];
        $modelClass = $modelConfig['model'];

        // Find the model instance
        $resource = $modelClass::find($payload['id']);

        if (! $resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        // Authorize the update
        $this->authorize('update', $resource);

        // Begin transaction
        DB::beginTransaction();

        try {
            $updateData = [];
            $fields = $payload['fields'];

            // Process each field
            foreach ($fields as $field => $value) {
                // Check for field handlers (for nested fields like address)
                $fieldParts = explode('.', $field);
                $rootField = $fieldParts[0];

                if (isset($modelConfig['fieldHandlers'][$rootField])) {
                    // Use custom field handler
                    $handlerResult = $modelConfig['fieldHandlers'][$rootField]($value, $resource);
                    $updateData = array_merge($updateData, $handlerResult);
                } else {
                    // Direct field assignment
                    $updateData[$field] = $value;
                }
            }

            // Validate the update data
            $validator = Validator::make($updateData, $this->getValidationRules($modelKey, array_keys($fields)));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            // Apply the update
            if (isset($modelConfig['service']) && method_exists($modelConfig['service'], 'updateFromInline')) {
                // Use service method if available
                $service = app($modelConfig['service']);
                $resource = $service->updateFromInline($resource, $updateData);
            } else {
                // Direct model update
                $resource->update($updateData);
            }

            DB::commit();

            // Log the update
            Log::info('inline-edit', [
                'user_id' => $request->user()->id ?? null,
                'model' => $modelKey,
                'id' => $resource->id,
                'fields' => array_keys($updateData),
            ]);

            // Return the updated resource
            return response()->json([
                'success' => true,
                'message' => 'Updated successfully',
                'resource' => $resource->fresh(),
                'fields' => $updateData,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('inline-edit-error', [
                'error' => $e->getMessage(),
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
     * Get validation rules for specific fields
     */
    protected function getValidationRules(string $model, array $fields): array
    {
        $rules = [];
        $modelRules = $this->modelMap[$model]['validationRules'] ?? [];

        foreach ($fields as $field) {
            if (isset($modelRules[$field])) {
                $rules[$field] = $modelRules[$field];
            }
        }

        return $rules;
    }
}
