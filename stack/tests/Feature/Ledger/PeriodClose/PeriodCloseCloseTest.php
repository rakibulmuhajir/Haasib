<?php

namespace Tests\Feature\Ledger\PeriodClose;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Ledger\Domain\PeriodClose\Actions\CompletePeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Actions\LockPeriodCloseAction;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create();
    $this->company->users()->attach($this->user->id, ['role' => 'admin']);

    $this->actingAs($this->user)
        ->withSession(['current_company' => $this->company->id]);

    // Create accounting period
    $this->period = AccountingPeriod::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'open',
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
    ]);

    // Start period close
    $this->periodClose = PeriodClose::factory()->create([
        'company_id' => $this->company->id,
        'accounting_period_id' => $this->period->id,
        'status' => 'in_review',
        'started_by' => $this->user->id,
        'started_at' => now(),
    ]);
});

it('can lock a period close when user has appropriate permissions', function () {
    // Give user lock permissions
    $this->user->givePermissionTo('period-close.lock');

    $lockAction = new LockPeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    $result = $lockAction->execute($this->periodClose, [
        'reason' => 'Ready for final processing',
    ], $service);

    expect($result)->toBeTrue();

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('locked');
    expect($this->periodClose->locked_at)->not->toBeNull();
    expect($this->periodClose->locked_by)->toBe($this->user->id);
    expect($this->periodClose->lock_reason)->toBe('Ready for final processing');

    // Check audit trail
    expect($this->periodClose->audit_trail)->toHaveKey('lock_events');
    expect($this->periodClose->audit_trail['lock_events'])->toHaveCount(1);
    expect($this->periodClose->audit_trail['lock_events'][0])->toMatchArray([
        'action' => 'locked',
        'user_id' => $this->user->id,
        'timestamp' => now()->toISOString(),
        'reason' => 'Ready for final processing',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
});

it('cannot lock a period close without lock permissions', function () {
    // User does not have lock permissions

    $lockAction = new LockPeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $lockAction->execute($this->periodClose, [
        'reason' => 'Should not work',
    ], $service))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('in_review');
    expect($this->periodClose->locked_at)->toBeNull();
});

it('cannot lock a period close that is not in appropriate status', function () {
    $this->user->givePermissionTo('period-close.lock');

    // Set period close to a status that cannot be locked
    $this->periodClose->update(['status' => 'pending']);

    $lockAction = new LockPeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $lockAction->execute($this->periodClose, [
        'reason' => 'Should not work',
    ], $service))->toThrow(\InvalidArgumentException::class);

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('pending');
});

it('cannot lock a period close when required tasks are not completed', function () {
    $this->user->givePermissionTo('period-close.lock');

    // Create incomplete required tasks
    $task = \Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask::factory()->create([
        'period_close_id' => $this->periodClose->id,
        'code' => 'BANK_RECONCILIATION',
        'title' => 'Bank Reconciliation',
        'is_required' => true,
        'status' => 'pending', // Not completed
    ]);

    $lockAction = new LockPeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $lockAction->execute($this->periodClose, [
        'reason' => 'Should not work',
    ], $service))->toThrow(\InvalidArgumentException::class, 'Cannot lock period: required tasks not completed');

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('in_review');
});

it('can complete a period close when user has appropriate permissions', function () {
    // Give user complete permissions
    $this->user->givePermissionTo('period-close.complete');

    // First lock the period
    $this->periodClose->update([
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->user->id,
    ]);

    $completeAction = new CompletePeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    $result = $completeAction->execute($this->periodClose, [
        'notes' => 'Period close completed successfully',
    ], $service);

    expect($result)->toBeTrue();

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('closed');
    expect($this->periodClose->completed_at)->not->toBeNull();
    expect($this->periodClose->completed_by)->toBe($this->user->id);
    expect($this->periodClose->completion_notes)->toBe('Period close completed successfully');

    // Check that accounting period is also closed
    $this->period->refresh();
    expect($this->period->status)->toBe('closed');

    // Check audit trail
    expect($this->periodClose->audit_trail)->toHaveKey('completion_events');
    expect($this->periodClose->audit_trail['completion_events'])->toHaveCount(1);
    expect($this->periodClose->audit_trail['completion_events'][0])->toMatchArray([
        'action' => 'completed',
        'user_id' => $this->user->id,
        'timestamp' => now()->toISOString(),
        'notes' => 'Period close completed successfully',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
});

it('cannot complete a period close without complete permissions', function () {
    // User does not have complete permissions

    // First lock the period
    $this->periodClose->update([
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->user->id,
    ]);

    $completeAction = new CompletePeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $completeAction->execute($this->periodClose, [
        'notes' => 'Should not work',
    ], $service))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('locked');
    expect($this->periodClose->completed_at)->toBeNull();
});

it('cannot complete a period close that is not locked', function () {
    $this->user->givePermissionTo('period-close.complete');

    // Period close is not locked
    $this->periodClose->update(['status' => 'in_review']);

    $completeAction = new CompletePeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $completeAction->execute($this->periodClose, [
        'notes' => 'Should not work',
    ], $service))->toThrow(\InvalidArgumentException::class, 'Cannot complete period: period must be locked first');

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('in_review');
});

it('cannot complete a period close that belongs to a different company', function () {
    $this->user->givePermissionTo('period-close.complete');

    // Create period close for different company
    $otherCompany = Company::factory()->create();
    $otherPeriod = AccountingPeriod::factory()->create([
        'company_id' => $otherCompany->id,
        'status' => 'open',
    ]);

    $otherPeriodClose = PeriodClose::factory()->create([
        'company_id' => $otherCompany->id,
        'accounting_period_id' => $otherPeriod->id,
        'status' => 'locked',
    ]);

    $completeAction = new CompletePeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $completeAction->execute($otherPeriodClose, [
        'notes' => 'Should not work',
    ], $service))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('generates proper audit metadata when locking period close', function () {
    $this->user->givePermissionTo('period-close.lock');

    $lockAction = new LockPeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    $lockAction->execute($this->periodClose, [
        'reason' => 'Ready for final processing',
    ], $service);

    $this->periodClose->refresh();

    expect($this->periodClose->metadata)->toHaveKey('lock_metadata');
    expect($this->periodClose->metadata['lock_metadata'])->toMatchArray([
        'lock_timestamp' => now()->toISOString(),
        'lock_duration_ms' => expect(\PHPUnit\Framework\Constraint\IsType::TYPE_INT),
        'total_tasks_count' => 0, // Will be populated based on actual tasks
        'completed_tasks_count' => 0,
        'validation_score' => 100, // Assuming all validations pass
        'adjustments_count' => 0, // Will be populated based on actual adjustments
        'locking_user_id' => $this->user->id,
        'locking_user_role' => 'admin',
        'session_id' => session()->getId(),
    ]);
});

it('prevents further period close actions after completion', function () {
    $this->user->givePermissionTo(['period-close.lock', 'period-close.complete']);

    // Complete the period close
    $this->periodClose->update([
        'status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $this->user->id,
    ]);

    $completeAction = new CompletePeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    $completeAction->execute($this->periodClose, [
        'notes' => 'Period close completed',
    ], $service);

    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('closed');

    // Try to lock again
    $lockAction = new LockPeriodCloseAction;

    expect(fn () => $lockAction->execute($this->periodClose, [
        'reason' => 'Should not work',
    ], $service))->toThrow(\InvalidArgumentException::class, 'Cannot lock period: period is already closed');
});

it('validates task completion status before allowing lock', function () {
    $this->user->givePermissionTo('period-close.lock');

    // Create tasks with mixed completion status
    $requiredTask1 = \Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask::factory()->create([
        'period_close_id' => $this->periodClose->id,
        'code' => 'BANK_RECONCILIATION',
        'title' => 'Bank Reconciliation',
        'is_required' => true,
        'status' => 'completed',
        'completed_by' => $this->user->id,
        'completed_at' => now(),
    ]);

    $requiredTask2 = \Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask::factory()->create([
        'period_close_id' => $this->periodClose->id,
        'code' => 'TRIAL_BALANCE',
        'title' => 'Trial Balance Review',
        'is_required' => true,
        'status' => 'pending', // Still pending
    ]);

    $optionalTask = \Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask::factory()->create([
        'period_close_id' => $this->periodClose->id,
        'code' => 'MANAGEMENT_REVIEW',
        'title' => 'Management Review',
        'is_required' => false,
        'status' => 'pending', // Optional tasks don't matter for locking
    ]);

    $lockAction = new LockPeriodCloseAction;
    $service = new ServiceContext($this->company, $this->user);

    expect(fn () => $lockAction->execute($this->periodClose, [
        'reason' => 'Should not work',
    ], $service))->toThrow(\InvalidArgumentException::class, 'Cannot lock period: required tasks not completed');

    // Complete the required task
    $requiredTask2->update([
        'status' => 'completed',
        'completed_by' => $this->user->id,
        'completed_at' => now(),
    ]);

    // Now locking should work
    $result = $lockAction->execute($this->periodClose, [
        'reason' => 'All required tasks completed',
    ], $service);

    expect($result)->toBeTrue();
    $this->periodClose->refresh();
    expect($this->periodClose->status)->toBe('locked');
});
