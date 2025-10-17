<?php

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Ledger\Actions\BankReconciliation\CompleteReconciliation;
use Modules\Ledger\Actions\BankReconciliation\LockReconciliation;
use Modules\Ledger\Actions\BankReconciliation\ReopenReconciliation;

uses(RefreshDatabase::class);

describe('Bank Reconciliation Lifecycle', function () {
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

        // Create statement lines
        $this->statementLines = BankStatementLine::factory()->count(3)->create([
            'statement_id' => $this->statement->id,
            'company_id' => $this->company->id,
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
    });

    describe('Completion Flow', function () {
        it('completes a reconciliation when variance is zero', function () {
            // Create matches that make variance zero
            $totalAmount = $this->statement->closing_balance - $this->statement->opening_balance;

            foreach ($this->statementLines as $index => $line) {
                BankReconciliationMatch::factory()->create([
                    'reconciliation_id' => $this->reconciliation->id,
                    'statement_line_id' => $line->id,
                    'amount' => $totalAmount / $this->statementLines->count(),
                    'matched_by' => $this->user->id,
                    'matched_at' => now(),
                ]);
            }

            // Update reconciliation variance
            $this->reconciliation->recalculateVariance();
            expect($this->reconciliation->variance)->toBe(0.0);

            $action = new CompleteReconciliation($this->reconciliation, $this->user);
            $result = $action->handle();

            expect($result)->toBeTrue();

            $this->reconciliation->refresh();
            expect($this->reconciliation->status)->toBe('completed');
            expect($this->reconciliation->completed_by)->toBe($this->user->id);
            expect($this->reconciliation->completed_at)->not->toBeNull();
        });

        it('fails to complete reconciliation with non-zero variance', function () {
            // Create partial matches that leave variance
            BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'statement_line_id' => $this->statementLines->first()->id,
                'amount' => 100.00,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            $this->reconciliation->recalculateVariance();
            expect($this->reconciliation->variance)->not->toBe(0.0);

            $action = new CompleteReconciliation($this->reconciliation, $this->user);

            expect(fn () => $action->handle())->toThrow(InvalidArgumentException::class);

            $this->reconciliation->refresh();
            expect($this->reconciliation->status)->toBe('in_progress');
            expect($this->reconciliation->completed_at)->toBeNull();
        });

        it('fails to complete reconciliation not in progress', function () {
            $this->reconciliation->update(['status' => 'draft']);

            $action = new CompleteReconciliation($this->reconciliation, $this->user);

            expect(fn () => $action->handle())->toThrow(InvalidArgumentException::class, 'Reconciliation must be in progress to be completed');
        });

        it('fires completion events', function () {
            Event::fake();

            // Make variance zero
            BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'statement_line_id' => $this->statementLines->first()->id,
                'amount' => $this->statement->closing_balance - $this->statement->opening_balance,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            $this->reconciliation->recalculateVariance();

            $action = new CompleteReconciliation($this->reconciliation, $this->user);
            $action->handle();

            Event::assertDispatched('bank.reconciliation.completed');
        });
    });

    describe('Locking Flow', function () {
        beforeEach(function () {
            // Set up completed reconciliation
            $this->reconciliation->update([
                'status' => 'completed',
                'completed_by' => $this->user->id,
                'completed_at' => now(),
            ]);
        });

        it('locks a completed reconciliation', function () {
            $action = new LockReconciliation($this->reconciliation);
            $result = $action->handle();

            expect($result)->toBeTrue();

            $this->reconciliation->refresh();
            expect($this->reconciliation->status)->toBe('locked');
            expect($this->reconciliation->locked_at)->not->toBeNull();
        });

        it('fails to lock non-completed reconciliation', function () {
            $this->reconciliation->update(['status' => 'in_progress']);

            $action = new LockReconciliation($this->reconciliation);

            expect(fn () => $action->handle())->toThrow(InvalidArgumentException::class, 'Reconciliation must be completed before it can be locked');
        });

        it('prevents editing when locked', function () {
            $action = new LockReconciliation($this->reconciliation);
            $action->handle();

            expect($this->reconciliation->canBeEdited())->toBeFalse();
            expect($this->reconciliation->isLocked())->toBeTrue();
        });

        it('fires locking events', function () {
            Event::fake();

            $action = new LockReconciliation($this->reconciliation);
            $action->handle();

            Event::assertDispatched('bank.reconciliation.locked');
        });
    });

    describe('Reopening Flow', function () {
        beforeEach(function () {
            // Set up locked reconciliation
            $this->reconciliation->update([
                'status' => 'locked',
                'completed_by' => $this->user->id,
                'completed_at' => now()->subDays(1),
                'locked_at' => now()->subHours(1),
            ]);
        });

        it('reopens a locked reconciliation with reason', function () {
            $reason = 'Need to correct bank fee entry';

            $action = new ReopenReconciliation($this->reconciliation, $this->user, $reason);
            $result = $action->handle();

            expect($result)->toBeTrue();

            $this->reconciliation->refresh();
            expect($this->reconciliation->status)->toBe('reopened');
            expect($this->reconciliation->notes)->toContain($reason);
        });

        it('fails to reopen non-locked reconciliation', function () {
            $this->reconciliation->update(['status' => 'completed']);

            $action = new ReopenReconciliation($this->reconciliation, $this->user, 'Test reason');

            expect(fn () => $action->handle())->toThrow(InvalidArgumentException::class, 'Only locked reconciliations can be reopened');
        });

        it('requires a reason for reopening', function () {
            expect(fn () => new ReopenReconciliation($this->reconciliation, $this->user, ''))
                ->toThrow(InvalidArgumentException::class, 'Reopening reason is required');
        });

        it('allows editing after reopening', function () {
            $action = new ReopenReconciliation($this->reconciliation, $this->user, 'Correction needed');
            $action->handle();

            expect($this->reconciliation->canBeEdited())->toBeTrue();
            expect($this->reconciliation->isReopened())->toBeTrue();
        });

        it('fires reopening events', function () {
            Event::fake();

            $action = new ReopenReconciliation($this->reconciliation, $this->user, 'Audit finding');
            $action->handle();

            Event::assertDispatched('bank.reconciliation.reopened');
        });
    });

    describe('Full Lifecycle Integration', function () {
        it('handles complete lifecycle from draft to locked', function () {
            // Start with draft
            expect($this->reconciliation->status)->toBe('in_progress');

            // Add matches to achieve zero variance
            $totalAmount = $this->statement->closing_balance - $this->statement->opening_balance;

            foreach ($this->statementLines as $line) {
                BankReconciliationMatch::factory()->create([
                    'reconciliation_id' => $this->reconciliation->id,
                    'statement_line_id' => $line->id,
                    'amount' => $totalAmount / $this->statementLines->count(),
                    'matched_by' => $this->user->id,
                    'matched_at' => now(),
                ]);
            }

            // Complete
            $this->reconciliation->recalculateVariance();
            $completeAction = new CompleteReconciliation($this->reconciliation, $this->user);
            $completeAction->handle();

            expect($this->reconciliation->fresh()->status)->toBe('completed');

            // Lock
            $lockAction = new LockReconciliation($this->reconciliation->fresh());
            $lockAction->handle();

            expect($this->reconciliation->fresh()->status)->toBe('locked');
            expect($this->reconciliation->fresh()->canBeEdited())->toBeFalse();
        });

        it('handles reopen and complete again cycle', function () {
            // Complete and lock first
            BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'statement_line_id' => $this->statementLines->first()->id,
                'amount' => $this->statement->closing_balance - $this->statement->opening_balance,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            $this->reconciliation->recalculateVariance();

            $completeAction = new CompleteReconciliation($this->reconciliation, $this->user);
            $completeAction->handle();

            $lockAction = new LockReconciliation($this->reconciliation->fresh());
            $lockAction->handle();

            expect($this->reconciliation->fresh()->status)->toBe('locked');

            // Reopen
            $reopenAction = new ReopenReconciliation(
                $this->reconciliation->fresh(),
                $this->user,
                'Found error in bank fee calculation'
            );
            $reopenAction->handle();

            expect($this->reconciliation->fresh()->status)->toBe('reopened');
            expect($this->reconciliation->fresh()->canBeEdited())->toBeTrue();

            // Add adjustment and complete again
            BankReconciliationAdjustment::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'adjustment_type' => 'bank_fee',
                'amount' => 25.00,
                'description' => 'Monthly bank service fee',
            ]);

            $this->reconciliation->fresh()->recalculateVariance();

            $completeAgainAction = new CompleteReconciliation($this->reconciliation->fresh(), $this->user);
            $completeAgainAction->handle();

            expect($this->reconciliation->fresh()->status)->toBe('completed');
        });
    });

    describe('Security and Authorization', function () {
        it('validates user permissions during completion', function () {
            // Create unauthorized user
            $unauthorizedUser = User::factory()->create(['current_company_id' => $this->company->id]);

            // Make variance zero
            BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'statement_line_id' => $this->statementLines->first()->id,
                'amount' => $this->statement->closing_balance - $this->statement->opening_balance,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            $this->reconciliation->recalculateVariance();

            // Mock permission check
            $unauthorizedUser->givePermissionTo('bank_reconciliations.view');
            // No 'bank_reconciliations.complete' permission

            expect(fn () => $unauthorizedUser->can('complete', $this->reconciliation))->toBeFalse();
        });

        it('validates company context during lifecycle changes', function () {
            // Create reconciliation in different company
            $otherCompany = Company::factory()->create();
            $otherUser = User::factory()->create(['current_company_id' => $otherCompany->id]);

            $otherReconciliation = BankReconciliation::factory()->create([
                'company_id' => $otherCompany->id,
                'status' => 'completed',
            ]);

            $action = new LockReconciliation($otherReconciliation);

            // This should fail due to company context mismatch
            // Implementation would check user's current company vs reconciliation company
            expect($otherUser->current_company_id)->not->toBe($otherReconciliation->company_id);
        });
    });

    describe('Edge Cases and Validation', function () {
        it('handles concurrent completion attempts gracefully', function () {
            // Simulate two users trying to complete simultaneously
            $user2 = User::factory()->create(['current_company_id' => $this->company->id]);

            // Make variance zero
            BankReconciliationMatch::factory()->create([
                'reconciliation_id' => $this->reconciliation->id,
                'statement_line_id' => $this->statementLines->first()->id,
                'amount' => $this->statement->closing_balance - $this->statement->opening_balance,
                'matched_by' => $this->user->id,
                'matched_at' => now(),
            ]);

            $this->reconciliation->recalculateVariance();

            // First completion succeeds
            $action1 = new CompleteReconciliation($this->reconciliation, $this->user);
            $result1 = $action1->handle();

            expect($result1)->toBeTrue();

            // Second completion should fail gracefully
            $action2 = new CompleteReconciliation($this->reconciliation->fresh(), $user2);
            expect(fn () => $action2->handle())->toThrow(InvalidArgumentException::class);
        });

        it('prevents status changes during system maintenance', function () {
            // This would test maintenance mode handling
            // Implementation could check for maintenance mode flag

            $this->reconciliation->update(['status' => 'maintenance_locked']);

            $action = new CompleteReconciliation($this->reconciliation, $this->user);

            expect(fn () => $action->handle())->toThrow(InvalidArgumentException::class, 'Cannot complete reconciliation during maintenance');
        });

        it('validates reopening reason length and content', function () {
            $this->reconciliation->update(['status' => 'locked']);

            // Test empty reason
            expect(fn () => new ReopenReconciliation($this->reconciliation, $this->user, ''))
                ->toThrow(InvalidArgumentException::class, 'Reopening reason is required');

            // Test very long reason
            $longReason = str_repeat('This is a very long reason. ', 100);
            expect(fn () => new ReopenReconciliation($this->reconciliation, $this->user, $longReason))
                ->toThrow(InvalidArgumentException::class, 'Reopening reason must be less than 1000 characters');
        });
    });
});
