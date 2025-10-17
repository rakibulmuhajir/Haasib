<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ImportBankStatementRequest;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Modules\Ledger\Actions\BankReconciliation\ImportBankStatement;

class BankStatementImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:bank_statements.view')->only(['index', 'show']);
        $this->middleware('permission:bank_statements.import')->only(['store']);
        $this->middleware('permission:bank_statements.delete')->only(['destroy']);
    }

    public function index(): \Inertia\Response
    {
        $user = Auth::user();
        $company = $user->currentCompany();

        if (! $company) {
            return redirect()->route('companies.select')
                ->with('error', 'Please select a company first.');
        }

        // Get bank accounts for the company
        $bankAccounts = ChartOfAccount::where('company_id', $company->id)
            ->where('account_type', 'asset')
            ->where('account_subtype', 'bank')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'account_number', 'currency']);

        // Get recent statements
        $recentStatements = BankStatement::where('company_id', $company->id)
            ->with('ledgerAccount')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($statement) {
                return [
                    'id' => $statement->id,
                    'statement_name' => $statement->statement_name,
                    'bank_account' => $statement->ledgerAccount?->name,
                    'status' => $statement->status,
                    'format' => $statement->format,
                    'period_start' => $statement->statement_start_date,
                    'period_end' => $statement->statement_end_date,
                    'opening_balance' => $statement->formatted_opening_balance,
                    'closing_balance' => $statement->formatted_closing_balance,
                    'lines_count' => $statement->total_lines,
                    'imported_at' => $statement->imported_at,
                    'processed_at' => $statement->processed_at,
                    'can_be_reconciled' => $statement->canBeReconciled(),
                ];
            });

        return Inertia::render('Ledger/BankReconciliation/Import', [
            'bankAccounts' => $bankAccounts,
            'recentStatements' => $recentStatements,
            'permissions' => [
                'can_import' => $user->can('bank_statements.import'),
                'can_delete' => $user->can('bank_statements.delete'),
                'can_view' => $user->can('bank_statements.view'),
            ],
        ]);
    }

    public function store(ImportBankStatementRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $action = ImportBankStatement::fromRequest($user, $request->validated());
            $statement = $action->execute();

            return response()->json([
                'message' => 'Bank statement uploaded successfully and is being processed.',
                'statement' => [
                    'id' => $statement->id,
                    'statement_name' => $statement->statement_name,
                    'status' => $statement->status,
                    'format' => $statement->format,
                    'period_start' => $statement->statement_start_date,
                    'period_end' => $statement->statement_end_date,
                    'lines_count' => 0, // Will be updated after processing
                    'imported_at' => $statement->imported_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 409 ? 409 : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function show(BankStatement $statement): \Inertia\Response
    {
        $this->authorize('view', $statement);

        $statement->load([
            'ledgerAccount',
            'importedBy',
            'bankStatementLines' => function ($query) {
                $query->orderBy('transaction_date')
                    ->orderBy('line_number');
            },
        ]);

        $lines = $statement->bankStatementLines->map(function ($line) {
            return [
                'id' => $line->id,
                'transaction_date' => $line->formatted_transaction_date,
                'description' => $line->description,
                'reference_number' => $line->reference_number,
                'amount' => $line->signed_amount,
                'amount_type' => $line->amount_type,
                'balance_after' => $line->formatted_balance_after,
                'external_id' => $line->external_id,
                'is_matched' => $line->isMatched(),
            ];
        });

        return Inertia::render('Ledger/BankReconciliation/StatementDetails', [
            'statement' => [
                'id' => $statement->id,
                'statement_name' => $statement->statement_name,
                'bank_account' => $statement->ledgerAccount?->name,
                'status' => $statement->status,
                'format' => $statement->format,
                'period_start' => $statement->statement_start_date,
                'period_end' => $statement->statement_end_date,
                'period' => $statement->statement_period,
                'opening_balance' => $statement->formatted_opening_balance,
                'closing_balance' => $statement->formatted_closing_balance,
                'currency' => $statement->currency,
                'lines_count' => $statement->total_lines,
                'sum_of_lines' => $statement->sum_of_lines,
                'imported_by' => $statement->importedBy?->name,
                'imported_at' => $statement->imported_at,
                'processed_at' => $statement->processed_at,
                'can_be_reconciled' => $statement->canBeReconciled(),
                'file_path' => $statement->file_path,
            ],
            'lines' => $lines,
            'permissions' => [
                'can_delete' => request()->user()->can('bank_statements.delete'),
                'can_reconcile' => request()->user()->can('bank_reconciliations.create'),
            ],
        ]);
    }

    public function destroy(BankStatement $statement): JsonResponse
    {
        $this->authorize('delete', $statement);

        try {
            // Only allow deletion of statements that haven't been reconciled
            if ($statement->isReconciled()) {
                return response()->json([
                    'message' => 'Cannot delete a statement that has been reconciled.',
                ], 422);
            }

            $statement->delete();

            return response()->json([
                'message' => 'Bank statement deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete bank statement: '.$e->getMessage(),
            ], 500);
        }
    }

    public function status(BankStatement $statement): JsonResponse
    {
        $this->authorize('view', $statement);

        $linesCount = $statement->bankStatementLines()->count();

        return response()->json([
            'status' => $statement->status,
            'processed_at' => $statement->processed_at,
            'lines_count' => $linesCount,
            'is_processed' => $statement->isProcessed(),
            'can_be_reconciled' => $statement->canBeReconciled(),
        ]);
    }

    public function download(BankStatement $statement): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $statement);

        if (! $statement->file_path) {
            abort(404, 'File not found');
        }

        $filePath = storage_path('app/'.$statement->file_path);

        if (! file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->download($filePath, $statement->statement_name);
    }
}
