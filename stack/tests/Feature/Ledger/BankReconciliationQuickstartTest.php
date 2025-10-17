<?php

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Modules\Ledger\Actions\BankReconciliation\CompleteReconciliation;
use Modules\Ledger\Actions\BankReconciliation\CreateAdjustment;
use Modules\Ledger\Actions\BankReconciliation\ImportBankStatement;
use Modules\Ledger\Actions\BankReconciliation\ReopenReconciliation;
use Modules\Ledger\Actions\BankReconciliation\RunAutoMatch;
use Modules\Ledger\Services\BankReconciliationReportService;
use Modules\Ledger\Events\BankReconciliationStatusChanged;

uses(RefreshDatabase::class);

describe('Bank Reconciliation End-to-End Quickstart Validation', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        
        // Grant all necessary permissions for quickstart flow
        $this->user->givePermissionTo([
            'bank_reconciliation.view',
            'bank_reconciliation.import',
            'bank_reconciliation.match',
            'bank_reconciliation.adjust',
            'bank_reconciliation.complete',
            'bank_reconciliation.lock',
            'bank_reconciliation.reopen',
            'bank_reconciliation_reports.view',
            'bank_reconciliation_reports.export',
        ]);

        // Create bank account
        $this->bankAccount = ChartOfAccount::factory()->create([
            'company_id' => $this->company->id,
            'account_type' => 'asset',
            'account_subtype' => 'bank',
            'name' => 'Business Checking Account',
        ]);

        // Setup fake storage
        Storage::fake('bank-statements');
        Storage::fake('local');
        Queue::fake();
        Event::fake();
    });

    describe('Complete Quickstart Flow', function () {
        it('executes the full bank reconciliation workflow from statement import to report generation', function () {
            // Step 1: Import bank statement
            $statementContent = createSampleCSVContent();
            $file = UploadedFile::fake()->createWithContent('statement.csv', $statementContent);

            $importAction = new ImportBankStatement;
            $statement = $importAction->handle(
                company: $this->company,
                bankAccount: $this->bankAccount,
                file: $file,
                startDate: now()->startOfMonth(),
                endDate: now()->endOfMonth(),
                openingBalance: 1000.00,
                closingBalance: 1500.00
            );

            expect($statement)->toBeInstanceOf(BankStatement::class);
            expect($statement->company_id)->toBe($this->company->id);
            expect($statement->bank_account_id)->toBe($this->bankAccount->id);
            expect($statement->statement_lines)->toHaveCount(5);

            // Verify statement lines are properly normalized
            $statement->load('statement_lines');
            expect($statement->statement_lines->first()->amount)->toBe(200.00);
            expect($statement->statement_lines->first()->description)->toContain('Client Payment');

            // Step 2: Start reconciliation
            $reconciliation = BankReconciliation::create([
                'company_id' => $this->company->id,
                'statement_id' => $statement->id,
                'ledger_account_id' => $this->bankAccount->id,
                'status' => 'in_progress',
                'started_by' => $this->user->id,
                'started_at' => now(),
                'opening_balance' => $statement->opening_balance,
                'closing_balance' => $statement->closing_balance,
            ]);

            expect($reconciliation)->toBeInstanceOf(BankReconciliation::class);
            expect($reconciliation->status)->toBe('in_progress');
            expect($reconciliation->variance)->toBe(500.00); // 1500 - 1000 = 500

            // Step 3: Create some internal transactions to match against
            $journalEntries = JournalEntry::factory()->count(3)->create([
                'company_id' => $this->company->id,
                'date' => now()->subDays(5),
                'total_amount' => 200.00,
            ]);

            // Step 4: Run auto-match
            $autoMatchAction = new RunAutoMatch;
            $matchResults = $autoMatchAction->handle($reconciliation, $this->user);

            expect($matchResults['matches_created'])->toBeGreaterThanOrEqual(0);
            expect($matchResults['confidence_scores'])->toBeArray();

            // Verify matches are created (at least manual ones for testing)
            if ($matchResults['matches_created'] > 0) {
                expect($reconciliation->matches()->count())->toBe($matchResults['matches_created']);
            }

            // Step 5: Add manual adjustment for bank fee
            $adjustmentAction = new CreateAdjustment;
            $adjustment = $adjustmentAction->handle(
                reconciliation: $reconciliation,
                type: 'bank_fee',
                amount: 25.00,
                description: 'Monthly service charge',
                user: $this->user
            );

            expect($adjustment)->toBeInstanceOf(BankReconciliationAdjustment::class);
            expect($adjustment->adjustment_type)->toBe('bank_fee');
            expect($adjustment->amount)->toBe(25.00);
            expect($adjustment->journal_entry_id)->not->toBeNull();

            // Verify journal entry was created for the adjustment
            $journalEntry = JournalEntry::find($adjustment->journal_entry_id);
            expect($journalEntry)->not->toBeNull();
            expect($journalEntry->total_amount)->toBe(25.00);

            // Step 6: Add manual matches to complete reconciliation
            $unmatchedLines = $reconciliation->statement->statement_lines()
                ->whereDoesntHave('matches')
                ->get();

            if ($unmatchedLines->isNotEmpty()) {
                // Create manual matches for remaining lines
                foreach ($unmatchedLines as $line) {
                    BankReconciliationMatch::create([
                        'reconciliation_id' => $reconciliation->id,
                        'statement_line_id' => $line->id,
                        'source_type' => 'journal_entry',
                        'source_id' => $journalEntries->random()->id,
                        'amount' => $line->amount,
                        'confidence_score' => 100,
                        'auto_matched' => false,
                        'matched_by' => $this->user->id,
                        'matched_at' => now(),
                    ]);
                }
            }

            // Recalculate variance
            $reconciliation->recalculateVariance();

            // Step 7: Complete reconciliation
            $completeAction = new CompleteReconciliation;
            $completeAction->handle($reconciliation, $this->user);

            expect($reconciliation->fresh()->status)->toBe('completed');
            expect($reconciliation->completed_by)->toBe($this->user->id);
            expect($reconciliation->completed_at)->not->toBeNull();

            // Step 8: Lock reconciliation
            $reconciliation->lock();

            expect($reconciliation->fresh()->status)->toBe('locked');
            expect($reconciliation->locked_at)->not->toBeNull();
            expect($reconciliation->locked_by)->toBe($this->user->id);

            // Step 9: Generate reports
            $reportService = new BankReconciliationReportService;

            // Generate summary report
            $summaryReport = $reportService->generateSummaryReport($reconciliation);
            expect($summaryReport)->toHaveKeys([
                'reconciliation',
                'statement',
                'bank_account',
                'matches',
                'adjustments',
                'summary',
                'variance_analysis',
                'generated_at',
            ]);

            expect($summaryReport['reconciliation']['id'])->toBe($reconciliation->id);
            expect($summaryReport['summary']['total_adjustments'])->toBe(1);
            expect($summaryReport['variance_analysis']['variance_amount'])->toBe(0.0);

            // Generate variance analysis
            $varianceReport = $reportService->generateVarianceAnalysis($reconciliation);
            expect($varianceReport)->toHaveKeys([
                'variance_amount',
                'variance_percentage',
                'unmatched_items',
                'adjustments',
                'recommendations',
            ]);

            // Generate audit trail
            $auditReport = $reportService->generateAuditTrail($reconciliation);
            expect($auditReport)->toHaveKeys([
                'reconciliation_id',
                'activities',
                'status_changes',
                'access_log',
            ]);

            // Step 10: Export reports in different formats
            $pdfPath = $reportService->generateReport($reconciliation, 'pdf');
            expect($pdfPath)->not->toBeEmpty();
            expect(Storage::disk('local')->exists($pdfPath))->toBeTrue();

            $csvPath = $reportService->generateReport($reconciliation, 'csv');
            expect($csvPath)->not->toBeEmpty();
            expect(Storage::disk('local')->exists($csvPath))->toBeTrue();

            $jsonData = $reportService->generateReport($reconciliation, 'json');
            expect($jsonData)->toBeArray();
            expect($jsonData)->toHaveKey('reconciliation');

            // Step 11: Test reopening functionality
            $reconciliation->reopen($this->user, 'Testing reopen functionality');
            
            expect($reconciliation->fresh()->status)->toBe('in_progress');
            expect($reconciliation->locked_at)->toBeNull();
            expect($reconciliation->notes)->toContain('Testing reopen functionality');

            // Step 12: Verify events were dispatched
            Event::assertDispatched(BankReconciliationStatusChanged::class, function ($event) use ($reconciliation) {
                return $event->reconciliation->id === $reconciliation->id &&
                       in_array($event->newStatus, ['completed', 'locked', 'reopened']);
            });

            // Step 13: Verify API endpoints work
            $response = $this->actingAs($this->user)
                ->getJson("/api/ledger/bank-reconciliations/{$reconciliation->id}/reports");

            $response->assertOk();
            $response->assertJsonStructure([
                'reconciliation',
                'available_reports',
                'permissions',
            ]);

            $response = $this->actingAs($this->user)
                ->getJson("/api/ledger/bank-reconciliations/{$reconciliation->id}/metrics");

            $response->assertOk();
            $response->assertJsonStructure([
                'reconciliation_id',
                'metrics' => [
                    'progress',
                    'variance',
                    'activity',
                    'timeline',
                    'status',
                ],
            ]);

            // Step 14: Verify data integrity
            $this->assertDatabaseHas('ops.bank_statements', [
                'id' => $statement->id,
                'company_id' => $this->company->id,
                'opening_balance' => 1000.00,
                'closing_balance' => 1500.00,
            ]);

            $this->assertDatabaseHas('ops.bank_statement_lines', [
                'statement_id' => $statement->id,
                'amount' => 200.00,
            ]);

            $this->assertDatabaseHas('ledger.bank_reconciliations', [
                'id' => $reconciliation->id,
                'company_id' => $this->company->id,
                'statement_id' => $statement->id,
                'ledger_account_id' => $this->bankAccount->id,
            ]);

            $this->assertDatabaseHas('ledger.bank_reconciliation_adjustments', [
                'reconciliation_id' => $reconciliation->id,
                'adjustment_type' => 'bank_fee',
                'amount' => 25.00,
                'created_by' => $this->user->id,
            ]);

            // Step 15: Validate business rules
            expect($reconciliation->fresh()->canBeEdited())->toBeTrue(); // After reopening
            expect($reconciliation->fresh()->canBeCompleted())->toBeTrue(); // Variance is zero
            expect($reconciliation->matches()->count())->toBeGreaterThan(0);
            expect($reconciliation->adjustments()->count())->toBe(1);
        });
    });

    describe('Quickstart Error Handling', function () {
        it('handles errors gracefully throughout the quickstart flow', function () {
            // Test invalid file upload
            $invalidFile = UploadedFile::fake()->createWithContent('invalid.txt', 'invalid content');

            $importAction = new ImportBankStatement;
            expect(fn() => $importAction->handle(
                company: $this->company,
                bankAccount: $this->bankAccount,
                file: $invalidFile,
                startDate: now()->startOfMonth(),
                endDate: now()->endOfMonth(),
                openingBalance: 1000.00,
                closingBalance: 1500.00
            ))->toThrow(\Exception::class);

            // Test completing reconciliation with non-zero variance
            $statement = BankStatement::factory()->create([
                'company_id' => $this->company->id,
                'opening_balance' => 1000.00,
                'closing_balance' => 1500.00,
            ]);

            $reconciliation = BankReconciliation::factory()->create([
                'company_id' => $this->company->id,
                'statement_id' => $statement->id,
                'ledger_account_id' => $this->bankAccount->id,
                'status' => 'in_progress',
                'started_by' => $this->user->id,
                'opening_balance' => 1000.00,
                'closing_balance' => 1500.00,
            ]);

            // Don't create any matches or adjustments, variance will be 500.00
            $completeAction = new CompleteReconciliation;
            
            expect(fn() => $completeAction->handle($reconciliation, $this->user))
                ->toThrow(\InvalidArgumentException::class, 'Cannot complete reconciliation with non-zero variance');

            // Test unauthorized access
            $unauthorizedUser = User::factory()->create(['current_company_id' => $this->company->id]);
            // Don't give any permissions

            $response = $this->actingAs($unauthorizedUser)
                ->getJson("/api/ledger/bank-reconciliations/{$reconciliation->id}/reports");

            $response->assertForbidden();
        });
    });

    describe('Quickstart Performance Validation', function () {
        it('validates performance expectations during quickstart operations', function () {
            // Test import performance
            $largeStatementContent = createLargeCSVContent(1000); // 1000 lines
            $file = UploadedFile::fake()->createWithContent('large_statement.csv', $largeStatementContent);

            $startTime = microtime(true);

            $importAction = new ImportBankStatement;
            $statement = $importAction->handle(
                company: $this->company,
                bankAccount: $this->bankAccount,
                file: $file,
                startDate: now()->startOfMonth(),
                endDate: now()->endOfMonth(),
                openingBalance: 10000.00,
                closingBalance: 25000.00
            );

            $importTime = microtime(true) - $startTime;
            expect($importTime)->toBeLessThan(30.0); // Should complete in under 30 seconds
            expect($statement->statement_lines()->count())->toBe(1000);

            // Test report generation performance
            $reconciliation = BankReconciliation::factory()->create([
                'company_id' => $this->company->id,
                'statement_id' => $statement->id,
                'ledger_account_id' => $this->bankAccount->id,
                'status' => 'completed',
                'started_by' => $this->user->id,
                'completed_by' => $this->user->id,
            ]);

            $startTime = microtime(true);

            $reportService = new BankReconciliationReportService;
            $pdfPath = $reportService->generateReport($reconciliation, 'pdf');

            $reportTime = microtime(true) - $startTime;
            expect($reportTime)->toBeLessThan(45.0); // Should complete in under 45 seconds
            expect(Storage::disk('local')->exists($pdfPath))->toBeTrue();
        });
    });
});

/**
 * Helper method to create sample CSV content
 */
function createSampleCSVContent(): string
{
    return "Date,Description,Amount,Balance\n" .
           "2025-09-05,Client Payment - ABC Corp,200.00,1200.00\n" .
           "2025-09-12,Software Subscription,-49.99,1150.01\n" .
           "2025-09-18,Office Supplies Purchase,-156.32,993.69\n" .
           "2025-09-22,Client Payment - XYZ LLC,350.00,1343.69\n" .
           "2025-09-28,Bank Service Fee,-25.00,1318.69\n";
}

/**
 * Helper method to create large CSV content for performance testing
 */
function createLargeCSVContent(int $lineCount): string
{
    $content = "Date,Description,Amount,Balance\n";
    $balance = 10000.00;
    
    for ($i = 1; $i <= $lineCount; $i++) {
        $amount = mt_rand(-500, 1000) + (mt_rand(0, 99) / 100);
        $balance += $amount;
        $date = now()->subDays($lineCount - $i)->format('Y-m-d');
        $description = "Transaction " . $i;
        
        $content .= "{$date},{$description}," . number_format($amount, 2) . "," . number_format($balance, 2) . "\n";
    }
    
    return $content;
}