<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\CreateBankReconciliationAdjustmentRequest;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Actions\BankReconciliation\CreateAdjustment;
use Modules\Ledger\Services\BankReconciliationAdjustmentService;

class BankReconciliationAdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:bank_reconciliation_adjustments.view')->only(['index', 'show']);
        $this->middleware('permission:bank_reconciliation_adjustments.create')->only(['store']);
        $this->middleware('permission:bank_reconciliation_adjustments.update')->only(['update']);
        $this->middleware('permission:bank_reconciliation_adjustments.delete')->only(['destroy']);
    }

    public function index(BankReconciliation $reconciliation): JsonResponse
    {
        $this->authorize('view', $reconciliation);

        $adjustments = $reconciliation->adjustments()
            ->with(['createdBy', 'journalEntry'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'amount' => $adjustment->signed_amount,
                    'formatted_amount' => $adjustment->formatted_amount,
                    'description' => $adjustment->description,
                    'statement_line_id' => $adjustment->statement_line_id,
                    'journal_entry_id' => $adjustment->journal_entry_id,
                    'created_by' => $adjustment->createdBy?->name,
                    'created_at' => $adjustment->created_at->toISOString(),
                    'type_display_name' => $adjustment->type_display_name,
                    'type_icon' => $adjustment->type_icon,
                    'type_color' => $adjustment->type_color,
                    'has_journal_entry' => $adjustment->journal_entry_id !== null,
                ];
            });

        return response()->json([
            'adjustments' => $adjustments,
            'total_amount' => $adjustments->sum('amount'),
        ]);
    }

    public function store(CreateBankReconciliationAdjustmentRequest $request, BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('create', $reconciliation);

            $action = CreateAdjustment::fromRequest($reconciliation, Auth::user(), $request->validated());
            $adjustment = $action->execute();

            return response()->json([
                'message' => 'Adjustment created successfully',
                'adjustment' => [
                    'id' => $adjustment->id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'amount' => $adjustment->signed_amount,
                    'formatted_amount' => $adjustment->formatted_amount,
                    'description' => $adjustment->description,
                    'statement_line_id' => $adjustment->statement_line_id,
                    'journal_entry_id' => $adjustment->journal_entry_id,
                    'created_at' => $adjustment->created_at->toISOString(),
                    'type_display_name' => $adjustment->type_display_name,
                    'type_icon' => $adjustment->type_icon,
                    'type_color' => $adjustment->type_color,
                    'has_journal_entry' => $adjustment->journal_entry_id !== null,
                ],
                'reconciliation_variance' => $reconciliation->fresh()->formatted_variance,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    public function show(BankReconciliation $reconciliation, BankReconciliationAdjustment $adjustment): JsonResponse
    {
        $this->authorize('view', $reconciliation);

        if ($adjustment->reconciliation_id !== $reconciliation->id) {
            return response()->json(['message' => 'Adjustment not found for this reconciliation'], 404);
        }

        $adjustment->load(['createdBy', 'journalEntry.transactions.account']);

        $data = [
            'id' => $adjustment->id,
            'adjustment_type' => $adjustment->adjustment_type,
            'amount' => $adjustment->signed_amount,
            'formatted_amount' => $adjustment->formatted_amount,
            'description' => $adjustment->description,
            'statement_line_id' => $adjustment->statement_line_id,
            'journal_entry_id' => $adjustment->journal_entry_id,
            'created_by' => $adjustment->createdBy?->name,
            'created_at' => $adjustment->created_at->toISOString(),
            'type_display_name' => $adjustment->type_display_name,
            'type_icon' => $adjustment->type_icon,
            'type_color' => $adjustment->type_color,
        ];

        if ($adjustment->journalEntry) {
            $data['journal_entry'] = [
                'id' => $adjustment->journalEntry->id,
                'journal_date' => $adjustment->journalEntry->journal_date->toISOString(),
                'description' => $adjustment->journalEntry->description,
                'reference' => $adjustment->journalEntry->reference,
                'transactions' => $adjustment->journalEntry->transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'account_name' => $transaction->account->name,
                        'account_number' => $transaction->account->account_number,
                        'debit_amount' => $transaction->debit_amount,
                        'credit_amount' => $transaction->credit_amount,
                        'formatted_debit' => number_format($transaction->debit_amount, 2),
                        'formatted_credit' => number_format($transaction->credit_amount, 2),
                        'description' => $transaction->description,
                    ];
                }),
            ];
        }

        return response()->json($data);
    }

    public function update(
        CreateBankReconciliationAdjustmentRequest $request,
        BankReconciliation $reconciliation,
        BankReconciliationAdjustment $adjustment
    ): JsonResponse {
        try {
            $this->authorize('update', $reconciliation);

            if ($adjustment->reconciliation_id !== $reconciliation->id) {
                return response()->json(['message' => 'Adjustment not found for this reconciliation'], 404);
            }

            $service = new BankReconciliationAdjustmentService;
            $updatedAdjustment = $service->updateAdjustment(
                $adjustment,
                (float) $request->validated()['amount'],
                $request->validated()['description'],
                Auth::user()
            );

            return response()->json([
                'message' => 'Adjustment updated successfully',
                'adjustment' => [
                    'id' => $updatedAdjustment->id,
                    'adjustment_type' => $updatedAdjustment->adjustment_type,
                    'amount' => $updatedAdjustment->signed_amount,
                    'formatted_amount' => $updatedAdjustment->formatted_amount,
                    'description' => $updatedAdjustment->description,
                    'updated_at' => $updatedAdjustment->updated_at->toISOString(),
                    'type_display_name' => $updatedAdjustment->type_display_name,
                    'type_icon' => $updatedAdjustment->type_icon,
                    'type_color' => $updatedAdjustment->type_color,
                ],
                'reconciliation_variance' => $reconciliation->fresh()->formatted_variance,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    public function destroy(BankReconciliation $reconciliation, BankReconciliationAdjustment $adjustment): JsonResponse
    {
        try {
            $this->authorize('delete', $reconciliation);

            if ($adjustment->reconciliation_id !== $reconciliation->id) {
                return response()->json(['message' => 'Adjustment not found for this reconciliation'], 404);
            }

            $service = new BankReconciliationAdjustmentService;
            $deleted = $service->deleteAdjustment($adjustment, Auth::user());

            if (! $deleted) {
                return response()->json(['message' => 'Failed to delete adjustment'], 500);
            }

            return response()->json([
                'message' => 'Adjustment deleted successfully',
                'reconciliation_variance' => $reconciliation->fresh()->formatted_variance,
            ], 204);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAdjustmentTypes(BankReconciliation $reconciliation): JsonResponse
    {
        $this->authorize('view', $reconciliation);

        $types = [
            [
                'value' => 'bank_fee',
                'label' => 'Bank Fee',
                'description' => 'Charges imposed by the bank for services',
                'icon' => 'currency-dollar',
                'color' => 'red',
                'default_negative' => true,
            ],
            [
                'value' => 'interest',
                'label' => 'Interest Income',
                'description' => 'Interest earned on bank accounts',
                'icon' => 'chart-line',
                'color' => 'green',
                'default_negative' => false,
            ],
            [
                'value' => 'write_off',
                'label' => 'Write Off',
                'description' => 'Uncollectible amounts or errors',
                'icon' => 'trash',
                'color' => 'orange',
                'default_negative' => true,
            ],
            [
                'value' => 'timing',
                'label' => 'Timing Adjustment',
                'description' => 'Temporary timing differences',
                'icon' => 'clock',
                'color' => 'blue',
                'default_negative' => false,
            ],
        ];

        return response()->json(['types' => $types]);
    }

    public function getAdjustmentPreview(Request $request, BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $validated = $request->validate([
                'adjustment_type' => 'required|in:bank_fee,interest,write_off,timing',
                'amount' => 'required|numeric|between:-999999999.99,999999999.99',
                'description' => 'required|string|max:500',
                'post_journal_entry' => 'boolean',
            ]);

            $currentVariance = $reconciliation->variance;
            $adjustmentAmount = $this->applyAmountSign(
                $validated['adjustment_type'],
                (float) $validated['amount']
            );

            $newVariance = $currentVariance + $adjustmentAmount;

            return response()->json([
                'current_variance' => [
                    'amount' => $currentVariance,
                    'formatted' => number_format($currentVariance, 2),
                    'status' => $currentVariance == 0 ? 'balanced' : ($currentVariance > 0 ? 'positive' : 'negative'),
                ],
                'adjustment_impact' => [
                    'amount' => $adjustmentAmount,
                    'formatted' => number_format($adjustmentAmount, 2),
                ],
                'new_variance' => [
                    'amount' => $newVariance,
                    'formatted' => number_format($newVariance, 2),
                    'status' => $newVariance == 0 ? 'balanced' : ($newVariance > 0 ? 'positive' : 'negative'),
                    'is_balanced' => $newVariance == 0,
                ],
                'can_complete' => $newVariance == 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function applyAmountSign(string $adjustmentType, float $amount): float
    {
        switch ($adjustmentType) {
            case 'bank_fee':
            case 'write_off':
                return -abs($amount);
            case 'interest':
                return abs($amount);
            case 'timing':
                return $amount;
            default:
                return $amount;
        }
    }
}
