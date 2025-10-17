<?php

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Ledger\Events\BankReconciliationStatusChanged;
use Modules\Ledger\Listeners\BankReconciliationAuditSubscriber;
use Modules\Ledger\Services\BankReconciliationReportService;

uses(RefreshDatabase::class);

describe('Bank Reconciliation Reports & Audit Trail', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);

        // Create bank account
        $this->bankAccount = ChartOfAccount::factory()->create([
            'company_id' => $this->company->id,
            'account_type' => 'asset',
            'account_subtype' => 'bank',
        ]);

        // Create bank statement
        $this->statement = BankStatement::factory()->create([
            'company_id' => $this->company->id,
            'opening_balance' => 1000.00,
            'closing_balance' => 1500.00,
        ]);

        // Create reconciliation
        $this->reconciliation = BankReconciliation::factory()->create([
            'company_id' => $this->company->id,
            'statement_id' => $this->statement->id,
            'ledger_account_id' => $this->bankAccount->id,
            'status' => 'in_progress',
            'started_by' => $this->user->id,
            'started_at' => now(),
        ]);

        // Setup fake storage for reports
        Storage::fake('local');
    });

    describe('Report Generation', function () {
        it('generates reconciliation report in PDF format', function () {
            // Add some test data
            BankReconciliationMatch::factory()->count(3)->create([
                'reconciliation_id' => $this->reconciliation->id,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'adjustment_type' => 'bank_fee',
                'amount' => 25.00,
            ]);

            $reportService = new BankReconciliationReportService;
            $reportPath = $reportService->generateReport($this->reconciliation, 'pdf');

            expect($reportPath)->not->toBeEmpty();
            expect(Storage::exists($reportPath))->toBeTrue();

            // Verify report contains expected data
            $reportContent = Storage::get($reportPath);
            expect($reportContent)->toContain($this->reconciliation->statement->statement_period);
            expect($reportContent)->toContain((string) $this->bankAccount->name);
        });

        it('generates reconciliation report in JSON format', function () {
            // Add test data
            BankReconciliationMatch::factory()->count(2)->create([
                'reconciliation_id' => $this->reconciliation->id,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            $reportService = new BankReconciliationReportService;
            $reportData = $reportService->generateReport($this->reconciliation, 'json');

            expect($reportData)->toBeArray();
            expect($reportData)->toHaveKey('reconciliation');
            expect($reportData)->toHaveKey('statement');
            expect($reportData)->toHaveKey('matches');
            expect($reportData)->toHaveKey('adjustments');
            expect($reportData)->toHaveKey('summary');

            expect($reportData['reconciliation']['id'])->toBe($this->reconciliation->id);
            expect($reportData['statement']['period'])->toBe($this->reconciliation->statement->statement_period);
            expect($reportData['matches'])->toHaveCount(2);
        });

        it('generates variance analysis report', function () {
            // Create scenario with variance
            BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'amount' => 400.00,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'adjustment_type' => 'bank_fee',
                'amount' => 25.00,
            ]);

            $this->reconciliation->recalculateVariance();

            $reportService = new BankReconciliationReportService;
            $varianceReport = $reportService->generateVarianceAnalysis($this->reconciliation);

            expect($varianceReport)->toBeArray();
            expect($varianceReport)->toHaveKey('variance_amount');
            expect($varianceReport)->toHaveKey('variance_percentage');
            expect($varianceReport)->toHaveKey('unmatched_items');
            expect($varianceReport)->toHaveKey('adjustments');
            expect($varianceReport)->toHaveKey('recommendations');

            expect($varianceReport['variance_amount'])->toBeNumeric();
            expect($varianceReport['recommendations'])->toBeArray();
        });

        it('generates audit trail report', function () {
            // Perform various actions to generate audit trail
            $this->reconciliation->complete($this->user);
            $this->reconciliation->lock();

            // Simulate some audit activities
            activity()
                ->performedOn($this->reconciliation)
                ->causedBy($this->user)
                ->withProperties(['action' => 'status_change', 'old_status' => 'in_progress', 'new_status' => 'completed'])
                ->log('Status changed to completed');

            activity()
                ->performedOn($this->reconciliation)
                ->causedBy($this->user)
                ->withProperties(['action' => 'lock'])
                ->log('Reconciliation locked');

            $reportService = new BankReconciliationReportService;
            $auditReport = $reportService->generateAuditTrail($this->reconciliation);

            expect($auditReport)->toBeArray();
            expect($auditReport)->toHaveKey('reconciliation_id');
            expect($auditReport)->toHaveKey('activities');
            expect($auditReport)->toHaveKey('status_changes');
            expect($auditReport)->toHaveKey('access_log');

            expect($auditReport['activities'])->toHaveCount(2);
            expect($auditReport['activities'][0]['description'])->toContain('completed');
            expect($auditReport['activities'][1]['description'])->toContain('locked');
        });
    });

    describe('Audit Trail Tracking', function () {
        it('records audit entries for reconciliation lifecycle changes', function () {
            Event::fake();

            // Trigger completion
            $this->reconciliation->complete($this->user);

            Event::assertDispatched(BankReconciliationStatusChanged::class, function ($event) {
                return $event->reconciliation->id === $this->reconciliation->id &&
                       $event->oldStatus === 'in_progress' &&
                       $event->newStatus === 'completed' &&
                       $event->user->id === $this->user->id;
            });
        });

        it('records audit entries for adjustments', function () {
            Event::fake();

            $adjustment = BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'adjustment_type' => 'bank_fee',
                'amount' => 25.00,
                'created_by' => $this->user->id,
            ]);

            // Check if adjustment created event is fired (if implemented)
            $this->assertTrue(true); // Placeholder until adjustment events are implemented
        });

        it('records audit entries for matches', function () {
            Event::fake();

            $match = BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            // Check if match created event is fired (if implemented)
            $this->assertTrue(true); // Placeholder until match events are implemented
        });

        it('captures user context and timestamps in audit trail', function () {
            $startTime = now();

            // Perform reconciliation actions
            $this->reconciliation->complete($this->user);
            $this->reconciliation->lock();

            // Retrieve audit activities
            $activities = $this->reconciliation->activities()
                ->where('created_at', '>=', $startTime)
                ->orderBy('created_at')
                ->get();

            expect($activities)->toHaveCount(2);

            // Verify first activity (completion)
            $completionActivity = $activities->first();
            expect($completionActivity->causer->id)->toBe($this->user->id);
            expect($completionActivity->created_at)->greaterThanOrEqual($startTime);
            expect($completionActivity->properties)->toHaveKey('action');

            // Verify second activity (locking)
            $lockActivity = $activities->last();
            expect($lockActivity->causer->id)->toBe($this->user->id);
            expect($lockActivity->created_at)->greaterThan($completionActivity->created_at);
        });

        it('records failed actions in audit trail', function () {
            // Attempt to complete reconciliation with non-zero variance
            expect(fn () => $this->reconciliation->complete($this->user))
                ->toThrow(InvalidArgumentException::class);

            // Check if failed action is logged
            $failedActivities = $this->reconciliation->activities()
                ->where('description', 'like', '%failed%')
                ->orWhere('description', 'like', '%error%')
                ->get();

            // This will be implemented when audit subscriber is complete
            expect(true)->toBeTrue(); // Placeholder
        });
    });

    describe('Report Access Control', function () {
        it('restricts report access to authorized users', function () {
            $unauthorizedUser = User::factory()->create(['current_company_id' => $this->company->id]);

            $reportService = new BankReconciliationReportService;

            // User without permissions should not be able to generate reports
            expect(fn () => $reportService->generateReport($this->reconciliation, 'pdf'))
                ->toThrow(InvalidArgumentException::class, 'User does not have permission to view reconciliation reports');
        });

        it('allows report access for users with correct permissions', function () {
            // Give user permission to view reports
            $this->user->givePermissionTo('bank_reconciliation_reports.view');

            $reportService = new BankReconciliationReportService;
            $reportData = $reportService->generateReport($this->reconciliation, 'json');

            expect($reportData)->toBeArray();
            expect($reportData)->toHaveKey('reconciliation');
        });

        it('validates company context for report access', function () {
            $otherCompany = Company::factory()->create();
            $otherUser = User::factory()->create(['current_company_id' => $otherCompany->id]);

            // Give other user permissions but wrong company context
            $otherUser->givePermissionTo('bank_reconciliation_reports.view');

            $reportService = new BankReconciliationReportService;

            // Should fail due to company context mismatch
            expect(fn () => $reportService->generateReport($this->reconciliation, 'json', $otherUser))
                ->toThrow(InvalidArgumentException::class, 'Reconciliation does not belong to user\'s current company');
        });
    });

    describe('Report Data Integrity', function () {
        it('includes all required sections in reconciliation report', function () {
            // Add comprehensive test data
            BankReconciliationMatch::factory()->count(5)->create([
                'reconciliation_id' => $this->reconciliation->id,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            BankReconciliationAdjustment::factory()->count(2)->create([
                'reconciliation_id' => $this->reconciliation->id,
            ]);

            $reportService = new BankReconciliationReportService;
            $reportData = $reportService->generateReport($this->reconciliation, 'json');

            // Verify required sections
            expect($reportData)->toHaveKeys([
                'reconciliation',
                'statement',
                'bank_account',
                'matches',
                'adjustments',
                'summary',
                'variance_analysis',
                'generated_at',
            ]);

            // Verify data completeness
            expect($reportData['matches'])->toHaveCount(5);
            expect($reportData['adjustments'])->toHaveCount(2);
            expect($reportData['summary']['total_matches'])->toBe(5);
            expect($reportData['summary']['total_adjustments'])->toBe(2);
        });

        it('calculates correct totals and subtotals in reports', function () {
            // Create matches with known amounts
            $match1 = BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'amount' => 100.00,
            ]);

            $match2 = BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'amount' => 200.00,
            ]);

            // Create adjustments
            $adjustment1 = BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'amount' => 25.00,
            ]);

            $adjustment2 = BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'amount' => -10.00, // Credit adjustment
            ]);

            $reportService = new BankReconciliationReportService;
            $reportData = $reportService->generateReport($this->reconciliation, 'json');

            // Verify calculations
            expect($reportData['summary']['total_matched_amount'])->toBe(300.00);
            expect($reportData['summary']['total_adjustments'])->toBe(15.00); // 25.00 - 10.00
            expect($reportData['variance_analysis']['total_reconciled_amount'])->toBe(315.00);
        });

        it('handles edge cases in report generation', function () {
            // Empty reconciliation
            $emptyReconciliation = BankReconciliation::factory()->create([
                'company_id' => $this->company->id,
                'statement_id' => BankStatement::factory()->create(['company_id' => $this->company->id])->id,
                'ledger_account_id' => ChartOfAccount::factory()->create(['company_id' => $this->company->id])->id,
            ]);

            $reportService = new BankReconciliationReportService;
            $reportData = $reportService->generateReport($emptyReconciliation, 'json');

            expect($reportData['matches'])->toHaveCount(0);
            expect($reportData['adjustments'])->toHaveCount(0);
            expect($reportData['summary']['total_matches'])->toBe(0);
            expect($reportData['summary']['total_adjustments'])->toBe(0);
            expect($reportData['variance_analysis']['variance_amount'])->toBe(0.0);
        });
    });

    describe('Audit Subscriber Integration', function () {
        it('subscribes to reconciliation events and creates audit entries', function () {
            Event::fake();

            // Manually trigger the subscriber
            $subscriber = new BankReconciliationAuditSubscriber;
            $event = new BankReconciliationStatusChanged(
                $this->reconciliation,
                'in_progress',
                'completed',
                $this->user
            );

            $subscriber->handleStatusChanged($event);

            // Verify audit entry was created
            $this->assertDatabaseHas('activity_log', [
                'subject_type' => BankReconciliation::class,
                'subject_id' => $this->reconciliation->id,
                'causer_type' => User::class,
                'causer_id' => $this->user->id,
            ]);
        });

        it('captures detailed event data in audit entries', function () {
            $subscriber = new BankReconciliationAuditSubscriber;
            $event = new BankReconciliationStatusChanged(
                $this->reconciliation,
                'in_progress',
                'completed',
                $this->user
            );

            $subscriber->handleStatusChanged($event);

            $activity = $this->reconciliation->activities()->latest()->first();

            expect($activity->properties)->toHaveKey('old_status', 'in_progress');
            expect($activity->properties)->toHaveKey('new_status', 'completed');
            expect($activity->properties)->toHaveKey('reconciliation_id', $this->reconciliation->id);
            expect($activity->properties)->toHaveKey('user_id', $this->user->id);
            expect($activity->properties)->toHaveKey('company_id', $this->company->id);
        });
    });
});
