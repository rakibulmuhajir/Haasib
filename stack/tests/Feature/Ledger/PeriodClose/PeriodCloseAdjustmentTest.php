<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use App\Support\ServiceContext;
use Illuminate\Support\Facades\DB;
use Modules\Ledger\Domain\PeriodClose\Actions\CreatePeriodCloseAdjustmentAction;

it('can create a period close adjustment entry', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $period = AccountingPeriod::factory()->create([
        'company_id' => $company->id,
        'status' => 'closing',
    ]);

    // Create a period close
    $periodClose = \Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::create([
        'company_id' => $company->id,
        'accounting_period_id' => $period->id,
        'status' => 'in_review',
        'started_by' => $user->id,
        'started_at' => now(),
    ]);

    // Create test accounts
    $assetAccount = ChartOfAccount::factory()->create([
        'company_id' => $company->id,
        'account_type' => 'asset',
    ]);
    $expenseAccount = ChartOfAccount::factory()->create([
        'company_id' => $company->id,
        'account_type' => 'expense',
    ]);

    $context = new ServiceContext(
        userId: $user->id,
        companyId: $company->id,
        requestId: 'test-request-id',
        idempotencyKey: 'test-idempotency-key'
    );

    $action = new CreatePeriodCloseAdjustmentAction;

    $adjustmentData = [
        'reference' => 'ADJ-2025-001',
        'description' => 'Period end adjustment for depreciation',
        'entry_date' => now()->toDateString(),
        'lines' => [
            [
                'account_id' => $expenseAccount->id,
                'debit' => 1000.00,
                'credit' => 0,
                'description' => 'Depreciation expense',
            ],
            [
                'account_id' => $assetAccount->id,
                'debit' => 0,
                'credit' => 1000.00,
                'description' => 'Accumulated depreciation',
            ],
        ],
        'period_close_id' => $periodClose->id,
    ];

    $journalEntry = $action->execute($periodClose, $adjustmentData, $context);

    expect($journalEntry)->toBeInstanceOf(\App\Models\JournalEntry::class);
    expect($journalEntry->type)->toBe('period_adjustment');
    expect($journalEntry->reference)->toBe('ADJ-2025-001');
    expect($journalEntry->description)->toBe('Period end adjustment for depreciation');
    expect($journalEntry->company_id)->toBe($company->id);
    expect($journalEntry->metadata['created_during_period_close'])->toBe(true);
    expect($journalEntry->metadata['period_close_id'])->toBe($periodClose->id);
    expect($journalEntry->metadata['adjustment_type'])->toBe('period_close');

    // Verify journal lines were created
    expect($journalEntry->lines)->toHaveCount(2);

    $expenseLine = $journalEntry->lines->firstWhere('account_id', $expenseAccount->id);
    expect($expenseLine->debit)->toBe(1000.00);
    expect($expenseLine->credit)->toBe(0);

    $assetLine = $journalEntry->lines->firstWhere('account_id', $assetAccount->id);
    expect($assetLine->debit)->toBe(0);
    expect($assetLine->credit)->toBe(1000.00);
});

it('validates adjustment balances before creating', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $period = AccountingPeriod::factory()->create([
        'company_id' => $company->id,
        'status' => 'closing',
    ]);

    $periodClose = \Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::create([
        'company_id' => $company->id,
        'accounting_period_id' => $period->id,
        'status' => 'in_review',
        'started_by' => $user->id,
        'started_at' => now(),
    ]);

    $context = new ServiceContext(
        userId: $user->id,
        companyId: $company->id,
        requestId: 'test-request-id',
        idempotencyKey: 'test-idempotency-key'
    );

    $action = new CreatePeriodCloseAdjustmentAction;

    // Create unbalanced adjustment (debits != credits)
    $unbalancedData = [
        'reference' => 'ADJ-2025-002',
        'description' => 'Unbalanced adjustment',
        'entry_date' => now()->toDateString(),
        'lines' => [
            [
                'account_id' => ChartOfAccount::factory()->create(['company_id' => $company->id])->id,
                'debit' => 1000.00,
                'credit' => 0,
                'description' => 'Debit line',
            ],
            [
                'account_id' => ChartOfAccount::factory()->create(['company_id' => $company->id])->id,
                'debit' => 0,
                'credit' => 500.00, // Only 500 credit, doesn't balance
                'description' => 'Credit line',
            ],
        ],
        'period_close_id' => $periodClose->id,
    ];

    expect(fn () => $action->execute($periodClose, $unbalancedData, $context))
        ->toThrow(\InvalidArgumentException::class, 'must balance');
});

it('prevents adjustments for closed periods', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $period = AccountingPeriod::factory()->create([
        'company_id' => $company->id,
        'status' => 'closed',
    ]);

    $periodClose = \Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::create([
        'company_id' => $company->id,
        'accounting_period_id' => $period->id,
        'status' => 'closed',
        'started_by' => $user->id,
        'started_at' => now(),
        'closed_by' => $user->id,
        'closed_at' => now(),
    ]);

    $context = new ServiceContext(
        userId: $user->id,
        companyId: $company->id,
        requestId: 'test-request-id',
        idempotencyKey: 'test-idempotency-key'
    );

    $action = new CreatePeriodCloseAdjustmentAction;

    $adjustmentData = [
        'reference' => 'ADJ-2025-003',
        'description' => 'Should not be allowed',
        'entry_date' => now()->toDateString(),
        'lines' => [
            [
                'account_id' => ChartOfAccount::factory()->create(['company_id' => $company->id])->id,
                'debit' => 100.00,
                'credit' => 0,
                'description' => 'Test line',
            ],
            [
                'account_id' => ChartOfAccount::factory()->create(['company_id' => $company->id])->id,
                'debit' => 0,
                'credit' => 100.00,
                'description' => 'Test line',
            ],
        ],
        'period_close_id' => $periodClose->id,
    ];

    expect(fn () => $action->execute($periodClose, $adjustmentData, $context))
        ->toThrow(\Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException::class, 'already closed');
});

it('creates adjustment with proper audit trail', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $period = AccountingPeriod::factory()->create([
        'company_id' => $company->id,
        'status' => 'closing',
    ]);

    $periodClose = \Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::create([
        'company_id' => $company->id,
        'accounting_period_id' => $period->id,
        'status' => 'in_review',
        'started_by' => $user->id,
        'started_at' => now(),
    ]);

    $context = new ServiceContext(
        userId: $user->id,
        companyId: $company->id,
        requestId: 'test-request-id',
        idempotencyKey: 'test-idempotency-key'
    );

    $action = new CreatePeriodCloseAdjustmentAction;

    $adjustmentData = [
        'reference' => 'ADJ-2025-004',
        'description' => 'Audit trail test',
        'entry_date' => now()->toDateString(),
        'lines' => [
            [
                'account_id' => ChartOfAccount::factory()->create(['company_id' => $company->id])->id,
                'debit' => 500.00,
                'credit' => 0,
                'description' => 'Test line 1',
            ],
            [
                'account_id' => ChartOfAccount::factory()->create(['company_id' => $company->id])->id,
                'debit' => 0,
                'credit' => 500.00,
                'description' => 'Test line 2',
            ],
        ],
        'period_close_id' => $periodClose->id,
    ];

    $journalEntry = $action->execute($periodClose, $adjustmentData, $context);

    // Check audit log was created
    expect(DB::table('audit.audit_logs'))
        ->where('action_type', 'ledger.period_close_adjustment_created')
        ->where('user_id', $user->id)
        ->where('company_id', $company->id)
        ->whereJsonContains('details->journal_entry_id', $journalEntry->id)
        ->whereJsonContains('details->period_close_id', $periodClose->id)
        ->exists()
        ->toBeTrue();
});

it('validates account ownership before creating adjustment', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->create();
    $period = AccountingPeriod::factory()->create([
        'company_id' => $company->id,
        'status' => 'closing',
    ]);

    $periodClose = \Modules\Ledger\Domain\PeriodClose\Models\PeriodClose::create([
        'company_id' => $company->id,
        'accounting_period_id' => $period->id,
        'status' => 'in_review',
        'started_by' => $user->id,
        'started_at' => now(),
    ]);

    $context = new ServiceContext(
        userId: $user->id,
        companyId: $company->id,
        requestId: 'test-request-id',
        idempotencyKey: 'test-idempotency-key'
    );

    $action = new CreatePeriodCloseAdjustmentAction;

    // Try to use an account from another company
    $otherAccount = ChartOfAccount::factory()->create(['company_id' => $otherCompany->id]);
    $ownAccount = ChartOfAccount::factory()->create(['company_id' => $company->id]);

    $adjustmentData = [
        'reference' => 'ADJ-2025-005',
        'description' => 'Cross-company test',
        'entry_date' => now()->toDateString(),
        'lines' => [
            [
                'account_id' => $otherAccount->id, // This should fail
                'debit' => 100.00,
                'credit' => 0,
                'description' => 'Other company account',
            ],
            [
                'account_id' => $ownAccount->id,
                'debit' => 0,
                'credit' => 100.00,
                'description' => 'Own company account',
            ],
        ],
        'period_close_id' => $periodClose->id,
    ];

    expect(fn () => $action->execute($periodClose, $adjustmentData, $context))
        ->toThrow(\InvalidArgumentException::class, 'do not belong to this company');
});
