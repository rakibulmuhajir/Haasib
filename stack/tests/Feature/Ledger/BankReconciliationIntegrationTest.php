<?php

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Modules\Ledger\Services\BankReconciliationReportService;

describe('Bank Reconciliation Integration Validation', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        
        // Grant necessary permissions
        $this->user->givePermissionTo([
            'bank_reconciliation.view',
            'bank_reconciliation_reports.view',
        ]);

        $this->bankAccount = ChartOfAccount::factory()->create([
            'company_id' => $this->company->id,
            'account_type' => 'asset',
            'account_subtype' => 'bank',
        ]);

        $this->statement = BankStatement::factory()->create([
            'company_id' => $this->company->id,
            'opening_balance' => 1000.00,
            'closing_balance' => 1500.00,
        ]);

        $this->reconciliation = BankReconciliation::factory()->create([
            'company_id' => $this->company->id,
            'statement_id' => $this->statement->id,
            'ledger_account_id' => $this->bankAccount->id,
            'status' => 'completed',
            'started_by' => $this->user->id,
            'completed_by' => $this->user->id,
        ]);
    });

    describe('Core Service Validation', function () {
        it('validates report service functionality', function () {
            $reportService = new BankReconciliationReportService;

            // Test summary report generation
            $summaryReport = $reportService->generateSummaryReport($this->reconciliation);
            
            expect($summaryReport)->toBeArray();
            expect($summaryReport)->toHaveKey('reconciliation');
            expect($summaryReport)->toHaveKey('summary');
            expect($summaryReport['reconciliation']['id'])->toBe($this->reconciliation->id);

            // Test variance analysis
            $varianceReport = $reportService->generateVarianceAnalysis($this->reconciliation);
            
            expect($varianceReport)->toBeArray();
            expect($varianceReport)->toHaveKey('variance_amount');
            expect($varianceReport)->toHaveKey('recommendations');

            // Test audit trail
            $auditReport = $reportService->generateAuditTrail($this->reconciliation);
            
            expect($auditReport)->toBeArray();
            expect($auditReport)->toHaveKey('reconciliation_id');
            expect($auditReport)->toHaveKey('activities');
        });

        it('validates reconciliation business logic', function () {
            // Test basic reconciliation properties
            expect($this->reconciliation->company_id)->toBe($this->company->id);
            expect($this->reconciliation->statement_id)->toBe($this->statement->id);
            expect($this->reconciliation->ledger_account_id)->toBe($this->bankAccount->id);
            expect($this->reconciliation->status)->toBe('completed');

            // Test relationships
            expect($this->reconciliation->statement)->not->toBeNull();
            expect($this->reconciliation->statement->id)->toBe($this->statement->id);
            expect($this->reconciliation->ledgerAccount)->not->toBeNull();
            expect($this->reconciliation->ledgerAccount->id)->toBe($this->bankAccount->id);

            // Test business logic methods
            expect($this->reconciliation->canBeViewed())->toBeTrue();
            expect($this->reconciliation->isCompleted())->toBeTrue();
        });

        it('validates adjustment functionality', function () {
            $adjustment = BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'adjustment_type' => 'bank_fee',
                'amount' => 25.00,
                'created_by' => $this->user->id,
            ]);

            expect($adjustment)->not->toBeNull();
            expect($adjustment->reconciliation_id)->toBe($this->reconciliation->id);
            expect($adjustment->type_display_name)->toBe('Bank Fee');
            expect($adjustment->signed_amount)->toBe(-25.00);

            // Test relationship back to reconciliation
            expect($adjustment->reconciliation->id)->toBe($this->reconciliation->id);
        });
    });

    describe('API Endpoint Validation', function () {
        it('validates reports API endpoint structure', function () {
            $response = $this->actingAs($this->user)
                ->getJson("/api/ledger/bank-reconciliations/{$this->reconciliation->id}/reports");

            $response->assertOk();
            $response->assertJsonStructure([
                'reconciliation' => [
                    'id',
                    'status',
                    'statement_period',
                    'bank_account',
                ],
                'available_reports' => [
                    '*' => [
                        'type',
                        'name',
                        'description',
                        'formats',
                    ],
                ],
                'permissions' => [
                    'can_view_reports',
                    'can_export_reports',
                ],
            ]);
        });

        it('validates metrics API endpoint structure', function () {
            $response = $this->actingAs($this->user)
                ->getJson("/api/ledger/bank-reconciliations/{$this->reconciliation->id}/metrics");

            $response->assertOk();
            $response->assertJsonStructure([
                'reconciliation_id',
                'metrics' => [
                    'progress' => [
                        'percent_complete',
                        'matched_lines',
                        'total_lines',
                        'unmatched_lines',
                    ],
                    'variance' => [
                        'amount',
                        'formatted',
                        'status',
                        'is_balanced',
                    ],
                    'activity' => [
                        'total_matches',
                        'auto_matches',
                        'manual_matches',
                        'total_adjustments',
                    ],
                    'timeline' => [
                        'started_at',
                        'completed_at',
                        'locked_at',
                        'active_duration',
                    ],
                    'status' => [
                        'current',
                        'can_be_edited',
                        'can_be_completed',
                        'can_be_locked',
                        'can_be_reopened',
                    ],
                ],
                'updated_at',
            ]);
        });
    });

    describe('Data Integrity Validation', function () {
        it('validates database record integrity', function () {
            // Verify statement exists and is accessible
            $this->assertDatabaseHas('ops.bank_statements', [
                'id' => $this->statement->id,
                'company_id' => $this->company->id,
                'opening_balance' => 1000.00,
                'closing_balance' => 1500.00,
            ]);

            // Verify reconciliation exists and is accessible
            $this->assertDatabaseHas('ledger.bank_reconciliations', [
                'id' => $this->reconciliation->id,
                'company_id' => $this->company->id,
                'statement_id' => $this->statement->id,
                'ledger_account_id' => $this->bankAccount->id,
                'status' => 'completed',
            ]);

            // Verify bank account exists
            $this->assertDatabaseHas('chart_of_accounts', [
                'id' => $this->bankAccount->id,
                'company_id' => $this->company->id,
                'account_type' => 'asset',
                'account_subtype' => 'bank',
            ]);
        });

        it('validates permission system integration', function () {
            // Test user with permissions can access
            $response = $this->actingAs($this->user)
                ->getJson("/api/ledger/bank-reconciliations/{$this->reconciliation->id}/reports");
            $response->assertOk();

            // Test user without permissions is denied
            $unauthorizedUser = User::factory()->create(['current_company_id' => $this->company->id]);
            
            $response = $this->actingAs($unauthorizedUser)
                ->getJson("/api/ledger/bank-reconciliations/{$this->reconciliation->id}/reports");
            $response->assertForbidden();
        });
    });

    describe('Component Integration Validation', function () {
        it('validates service class integration', function () {
            // Test that the service can be instantiated and used
            $reportService = new BankReconciliationReportService();
            
            expect($reportService)->toBeInstanceOf(BankReconciliationReportService::class);
            
            // Test permission validation
            expect(fn() => $reportService->generateSummaryReport($this->reconciliation, 'json', $this->user))
                ->not->toThrow(\Exception::class);
        });

        it('validates model relationships and business logic', function () {
            // Load reconciliation with relationships
            $this->reconciliation->load(['statement', 'ledgerAccount', 'matches', 'adjustments']);
            
            expect($this->reconciliation->statement)->not->toBeNull();
            expect($this->reconciliation->ledgerAccount)->not->toBeNull();
            expect($this->reconciliation->matches)->toHaveCount(0); // No matches in factory
            expect($this->reconciliation->adjustments)->toHaveCount(0); // No adjustments in factory

            // Test variance calculation
            expect($this->reconciliation->variance)->toBeNumeric();
            expect($this->reconciliation->formatted_variance)->toBeString();
        });
    });
});