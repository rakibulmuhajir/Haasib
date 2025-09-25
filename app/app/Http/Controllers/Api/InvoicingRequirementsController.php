<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoicingRequirementsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoicingRequirementsController extends Controller
{
    public function __construct(
        private InvoicingRequirementsService $requirementsService
    ) {}

    /**
     * Get requirements for a specific invoicing action
     *
     * @return Response
     */
    public function getRequirements(Request $request)
    {
        $validated = $request->validate([
            'action_type' => ['required', 'string', 'in:status-change,payment-allocation,invoice-creation,credit-memo,void-adjustment'],
            'entity_type' => ['required', 'string'],
            'entity_id' => ['nullable', 'string'],
            'current_status' => ['nullable', 'string'],
            'new_status' => ['nullable', 'string'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            $context = array_merge($validated, $request->input('context', []));

            $requirements = $this->requirementsService->getRequirements(
                $validated['action_type'],
                $context,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $requirements,
                'cached' => true, // Indicates if response was cached
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve requirements',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Validate additional info fields
     *
     * @return Response
     */
    public function validateAdditionalInfo(Request $request)
    {
        $validated = $request->validate([
            'action_type' => ['required', 'string'],
            'fields' => ['required', 'array'],
            'values' => ['required', 'array'],
        ]);

        try {
            // Get requirements to know validation rules
            $requirements = $this->requirementsService->getRequirements(
                $validated['action_type'],
                $request->input('context', []),
                $request->user()
            );

            $errors = [];

            foreach ($requirements['fields'] as $field) {
                $fieldName = $field['name'];
                $value = $validated['values'][$fieldName] ?? null;

                // Apply validation rules
                if ($field['required'] && empty($value)) {
                    $errors[$fieldName] = "The {$field['label']} field is required.";
                }

                // Add more validation based on field type
                if ($field['type'] === 'number' && $value && ! is_numeric($value)) {
                    $errors[$fieldName] = "The {$field['label']} must be a number.";
                }

                if (isset($field['validation']) && is_array($field['validation'])) {
                    // Apply Laravel validation rules if needed
                    // This is a simplified version - you might want to use Validator
                }
            }

            return response()->json([
                'success' => empty($errors),
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
