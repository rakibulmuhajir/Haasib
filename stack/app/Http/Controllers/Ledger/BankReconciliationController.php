<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\CreateBankReconciliationMatchRequest;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Actions\BankReconciliation\RunAutoMatch;
use Modules\Ledger\Services\BankReconciliationMatchingService;

class BankReconciliationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:bank_reconciliations.view')->only(['show']);
        $this->middleware('permission:bank_reconciliation_matches.create')->only(['createMatch']);
        $this->middleware('permission:bank_reconciliation_matches.delete')->only(['deleteMatch']);
        $this->middleware('permission:bank_reconciliation_matches.auto_match')->only(['autoMatch']);
    }

    public function show(BankReconciliation $reconciliation): \Inertia\Response
    {
        $this->authorize('view', $reconciliation);

        $reconciliation->load([
            'statement.bankStatementLines' => function ($query) {
                $query->orderBy('transaction_date')->orderBy('line_number');
            },
            'matches.statementLine',
            'matches.source',
            'adjustments',
            'ledgerAccount',
            'startedBy',
            'completedBy',
        ]);

        // Get unmatched internal transactions
        $matchingService = new BankReconciliationMatchingService;
        $unmatchedTransactions = $matchingService->findUnmatchedTransactions($reconciliation);

        return Inertia::render('Ledger/BankReconciliation/Workspace', [
            'reconciliation' => [
                'id' => $reconciliation->id,
                'status' => $reconciliation->status,
                'variance' => $reconciliation->formatted_variance,
                'variance_status' => $reconciliation->variance_status,
                'unmatched_statement_total' => $reconciliation->formatted_unmatched_statement_total,
                'unmatched_internal_total' => $reconciliation->formatted_unmatched_internal_total,
                'percent_complete' => $reconciliation->percent_complete,
                'started_at' => $reconciliation->started_at?->toISOString(),
                'completed_at' => $reconciliation->completed_at?->toISOString(),
                'active_duration' => $reconciliation->active_duration,
                'notes' => $reconciliation->notes,
                'can_be_edited' => $reconciliation->canBeEdited(),
                'can_be_completed' => $reconciliation->canBeCompleted(),
                'can_be_locked' => $reconciliation->canBeLocked(),
                'can_be_reopened' => $reconciliation->canBeReopened(),
                'statement' => [
                    'id' => $reconciliation->statement->id,
                    'name' => $reconciliation->statement->statement_name,
                    'period' => $reconciliation->statement->statement_period,
                    'opening_balance' => $reconciliation->statement->formatted_opening_balance,
                    'closing_balance' => $reconciliation->statement->formatted_closing_balance,
                    'currency' => $reconciliation->statement->currency,
                    'lines_count' => $reconciliation->statement->total_lines,
                ],
                'bank_account' => [
                    'id' => $reconciliation->ledgerAccount->id,
                    'name' => $reconciliation->ledgerAccount->name,
                    'account_number' => $reconciliation->ledgerAccount->account_number,
                ],
            ],
            'statement_lines' => $reconciliation->statement->bankStatementLines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'transaction_date' => $line->formatted_transaction_date,
                    'description' => $line->description,
                    'reference_number' => $line->reference_number,
                    'amount' => $line->signed_amount,
                    'amount_type' => $line->amount_type,
                    'balance_after' => $line->formatted_balance_after,
                    'is_matched' => $line->isMatched(),
                    'match' => $line->reconciliationMatch?->load(['source']),
                ];
            }),
            'matches' => $reconciliation->matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'statement_line_id' => $match->statement_line_id,
                    'source_type' => $match->source_type,
                    'source_id' => $match->source_id,
                    'amount' => $match->formatted_amount,
                    'auto_matched' => $match->auto_matched,
                    'confidence_score' => $match->formatted_confidence_score,
                    'confidence_level' => $match->confidence_level,
                    'matched_at' => $match->matched_at->toISOString(),
                    'matched_by' => $match->matchedBy?->name,
                    'source_display_name' => $match->source_display_name,
                    'source_url' => $match->source_url,
                ];
            }),
            'adjustments' => $reconciliation->adjustments->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'amount' => $adjustment->signed_amount,
                    'description' => $adjustment->description,
                    'created_at' => $adjustment->created_at->toISOString(),
                    'type_display_name' => $adjustment->type_display_name,
                    'type_icon' => $adjustment->type_icon,
                    'type_color' => $adjustment->type_color,
                ];
            }),
            'unmatched_transactions' => $unmatchedTransactions,
            'permissions' => [
                'can_create_matches' => request()->user()->can('bank_reconciliation_matches.create'),
                'can_delete_matches' => request()->user()->can('bank_reconciliation_matches.delete'),
                'can_auto_match' => request()->user()->can('bank_reconciliation_matches.auto_match'),
                'can_create_adjustments' => request()->user()->can('bank_reconciliation_adjustments.create'),
                'can_complete' => request()->user()->can('bank_reconciliations.complete'),
                'can_lock' => request()->user()->can('bank_reconciliations.lock'),
                'can_reopen' => request()->user()->can('bank_reconciliations.reopen'),
            ],
        ]);
    }

    public function autoMatch(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('autoMatch', $reconciliation);

            $action = RunAutoMatch::forReconciliation($reconciliation, Auth::user());
            $jobId = $action->executeAsync();

            return response()->json([
                'message' => 'Auto-matching job queued successfully',
                'job_id' => $jobId,
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    public function createMatch(CreateBankReconciliationMatchRequest $request, BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('createMatch', $reconciliation);

            $service = new BankReconciliationMatchingService;
            $match = $service->createManualMatch(
                $request->getStatementLine(),
                $request->get('source_type'),
                $request->get('source_id'),
                (float) $request->get('amount'),
                Auth::user()
            );

            return response()->json([
                'message' => 'Match created successfully',
                'match' => [
                    'id' => $match->id,
                    'statement_line_id' => $match->statement_line_id,
                    'source_type' => $match->source_type,
                    'source_id' => $match->source_id,
                    'amount' => $match->formatted_amount,
                    'auto_matched' => $match->auto_matched,
                    'matched_at' => $match->matched_at->toISOString(),
                    'matched_by' => $match->matchedBy->name,
                ],
                'reconciliation_variance' => $reconciliation->fresh()->formatted_variance,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    public function deleteMatch(BankReconciliation $reconciliation, BankReconciliationMatch $match): JsonResponse
    {
        try {
            $this->authorize('deleteMatch', $reconciliation);

            if ($match->reconciliation_id !== $reconciliation->id) {
                return response()->json(['message' => 'Match not found for this reconciliation'], 404);
            }

            $service = new BankReconciliationMatchingService;
            $deleted = $service->removeMatch($match, Auth::user());

            if (! $deleted) {
                return response()->json(['message' => 'Failed to delete match'], 500);
            }

            return response()->json([
                'message' => 'Match removed successfully',
                'reconciliation_variance' => $reconciliation->fresh()->formatted_variance,
            ], 204);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMatchCandidates(BankReconciliation $reconciliation, BankStatementLine $statementLine): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            if ($statementLine->statement_id !== $reconciliation->statement_id) {
                return response()->json(['message' => 'Statement line not found for this reconciliation'], 404);
            }

            $service = new BankReconciliationMatchingService;
            $candidates = $service->findMatchingCandidates($statementLine, $reconciliation, []);

            return response()->json([
                'candidates' => $candidates->map(function ($candidate) {
                    return [
                        'source_type' => $candidate['source_type'],
                        'source_id' => $candidate['source_id'],
                        'confidence' => $candidate['confidence'],
                        'confidence_level' => $candidate['confidence'] >= 0.9 ? 'high' :
                                         ($candidate['confidence'] >= 0.7 ? 'medium' : 'low'),
                        'source_data' => $this->formatSourceData($candidate['source'], $candidate['source_type']),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatSourceData($source, string $sourceType): array
    {
        switch ($sourceType) {
            case 'acct.payment':
                return [
                    'id' => $source->id,
                    'payment_date' => $source->payment_date->toISOString(),
                    'amount' => $source->formatted_amount,
                    'reference' => $source->reference,
                    'customer' => $source->customer?->name,
                ];
            case 'acct.invoice':
                return [
                    'id' => $source->id,
                    'invoice_date' => $source->invoice_date->toISOString(),
                    'amount' => $source->formatted_total,
                    'invoice_number' => $source->invoice_number,
                    'customer' => $source->customer?->name,
                ];
            case 'ledger.journal_entry':
                return [
                    'id' => $source->id,
                    'journal_date' => $source->journal_date->toISOString(),
                    'description' => $source->description,
                    'reference' => $source->reference,
                    'amount' => $source->transactions->sum('debit_amount') + $source->transactions->sum('credit_amount'),
                ];
            case 'acct.credit_note':
                return [
                    'id' => $source->id,
                    'credit_note_date' => $source->credit_note_date->toISOString(),
                    'amount' => $source->formatted_total,
                    'credit_note_number' => $source->credit_note_number,
                    'customer' => $source->customer?->name,
                ];
            default:
                return [];
        }
    }
}
