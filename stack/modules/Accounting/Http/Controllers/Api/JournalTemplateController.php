<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\CreateRecurringTemplateAction;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\DeactivateRecurringTemplateAction;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\GenerateJournalEntriesFromTemplateAction;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\UpdateRecurringTemplateAction;

class JournalTemplateController extends Controller
{
    /**
     * Display a listing of recurring journal templates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\RecurringJournalTemplate::with(['lines.account'])
            ->where('company_id', $request->user()->company_id);

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by frequency
        if ($request->has('frequency')) {
            $query->where('frequency', $request->get('frequency'));
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Filter by next generation date range
        if ($request->has('next_from')) {
            $query->where('next_generation_date', '>=', $request->get('next_from'));
        }

        if ($request->has('next_to')) {
            $query->where('next_generation_date', '<=', $request->get('next_to'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['name', 'frequency', 'next_generation_date', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $templates = $query->paginate($perPage);

        return response()->json($templates);
    }

    /**
     * Store a newly created recurring journal template.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = array_merge($request->all(), [
                'company_id' => $request->user()->company_id,
            ]);

            $action = new CreateRecurringTemplateAction;
            $template = $action->execute($validated);

            return response()->json([
                'message' => 'Recurring template created successfully',
                'data' => $template->load(['lines.account']),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create recurring template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified recurring journal template.
     */
    public function show(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::with(['lines.account'])
            ->where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->firstOrFail();

        return response()->json($template);
    }

    /**
     * Update the specified recurring journal template.
     */
    public function update(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->firstOrFail();

        try {
            $action = new UpdateRecurringTemplateAction;
            $updatedTemplate = $action->execute($template, $request->all());

            return response()->json([
                'message' => 'Recurring template updated successfully',
                'data' => $updatedTemplate->load(['lines.account']),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update recurring template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified recurring journal template.
     */
    public function destroy(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->firstOrFail();

        try {
            // Check if template has generated entries
            $hasGeneratedEntries = \App\Models\JournalEntry::where('template_id', $template->id)->exists();

            if ($hasGeneratedEntries) {
                return response()->json([
                    'message' => 'Cannot delete template that has generated journal entries. Deactivate it instead.',
                ], 422);
            }

            $template->lines()->delete();
            $template->delete();

            return response()->json([
                'message' => 'Recurring template deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete recurring template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate a recurring template.
     */
    public function deactivate(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->firstOrFail();

        try {
            $action = new DeactivateRecurringTemplateAction;
            $deactivatedTemplate = $action->execute(
                $template,
                $request->get('reason', 'Manually deactivated via API')
            );

            return response()->json([
                'message' => 'Recurring template deactivated successfully',
                'data' => $deactivatedTemplate,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate recurring template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reactivate a deactivated recurring template.
     */
    public function reactivate(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->where('is_active', false)
            ->firstOrFail();

        try {
            $action = new DeactivateRecurringTemplateAction;
            $reactivatedTemplate = $action->reactivate($template);

            return response()->json([
                'message' => 'Recurring template reactivated successfully',
                'data' => $reactivatedTemplate,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reactivate recurring template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a journal entry from the template immediately.
     */
    public function generate(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $action = new GenerateJournalEntriesFromTemplateAction;
            $journalEntry = $action->execute($template);

            if (! $journalEntry) {
                return response()->json([
                    'message' => 'Template is not due for generation',
                    'template' => $template,
                ], 422);
            }

            return response()->json([
                'message' => 'Journal entry generated successfully',
                'data' => $journalEntry->load(['transactions.account']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate journal entry from template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview the next journal entry that would be generated from the template.
     */
    public function preview(Request $request, string $templateId): JsonResponse
    {
        $template = \App\Models\RecurringJournalTemplate::with(['lines.account'])
            ->where('company_id', $request->user()->company_id)
            ->where('id', $templateId)
            ->firstOrFail();

        $preview = [
            'template' => $template,
            'next_generation_date' => $template->next_generation_date,
            'would_generate_today' => $template->next_generation_date <= now()->toDateString() && $template->is_active,
            'projected_description' => sprintf(
                'Auto-generated: %s (%s)',
                $template->name,
                now()->format('Y-m-d')
            ),
            'projected_reference' => sprintf(
                'REC-%s-%s',
                str_pad($template->id, 6, '0', STR_PAD_LEFT),
                now()->format('Ymd')
            ),
            'projected_transactions' => $template->lines->map(function ($line) {
                return [
                    'account' => $line->account,
                    'debit_credit' => $line->debit_credit,
                    'amount' => $line->amount,
                    'description' => $line->description,
                ];
            }),
        ];

        return response()->json($preview);
    }

    /**
     * Get statistics about recurring templates.
     */
    public function statistics(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $stats = [
            'total_templates' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)->count(),
            'active_templates' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)->where('is_active', true)->count(),
            'inactive_templates' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)->where('is_active', false)->count(),
            'due_today' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('next_generation_date', '<=', now()->toDateString())
                ->count(),
            'due_this_week' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('next_generation_date', '<=', now()->addWeek()->toDateString())
                ->count(),
            'due_this_month' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('next_generation_date', '<=', now()->addMonth()->toDateString())
                ->count(),
            'frequency_breakdown' => \App\Models\RecurringJournalTemplate::where('company_id', $companyId)
                ->where('is_active', true)
                ->selectRaw('frequency, COUNT(*) as count')
                ->groupBy('frequency')
                ->pluck('count', 'frequency')
                ->toArray(),
        ];

        return response()->json($stats);
    }
}
