<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseReopenService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Models\SalaryAdvanceRecovery;
use App\Modules\Payroll\Services\EmployeeStatementService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Edit day soft-deletes what a close created and posting it again used to write a second copy,
 * so every re-save left one more trashed salary advance. The re-post now updates the same row.
 */
function advanceReuseFixture(array $advances = [[8000, 'Petrol for bike'], [3000, 'Medicine']]): array
{
    $f = creditCloseFixture();
    $f['accounts']['1150'] = Account::create([
        'company_id' => $f['company']->id, 'code' => '1150', 'name' => 'Employee advances', 'type' => 'asset',
        'subtype' => 'receivable', 'normal_balance' => 'debit', 'is_active' => true,
    ]);
    $f['employee'] = Employee::create([
        'company_id' => $f['company']->id, 'employee_number' => 'EMP-001', 'first_name' => 'Abdul', 'last_name' => 'Hameed',
        'hire_date' => '2025-01-01', 'currency' => 'PKR',
    ]);
    $f['payload']['employee_advances'] = array_map(fn ($a) => ['employee_id' => $f['employee']->id, 'amount' => $a[0], 'reason' => $a[1]], $advances);
    $f['payload']['closing_cash'] = 25000 - array_sum(array_column($advances, 0));
    $f['close'] = Transaction::findOrFail(creditClosePost($f)['transaction_id']);

    return $f;
}

/** Edit day, then post the parked draft again (optionally changed by $change, as the Create page would). */
function reopenAndRepost(array &$f, ?Closure $change = null): array
{
    app(DailyCloseReopenService::class)->reopen($f['close'], $f['user'], 'Fixing the day');
    $draft = app(DailyCloseReconciliationService::class)->draft($f['company']->id, '2026-09-15');
    if ($change) {
        $draft = $change($draft);
    }
    $f['close'] = Transaction::findOrFail(app(DailyCloseService::class)->processDailyClose($f['company']->id, $draft, $f['user'])['transaction_id']);

    return $draft;
}

function advanceRows(array $f)
{
    return SalaryAdvance::withTrashed()->where('company_id', $f['company']->id)->orderBy('created_at')->orderBy('amount')->get();
}

test('a posted advance remembers its id on the close form input', function () {
    $f = advanceReuseFixture();

    $rows = $f['close']->metadata['form_input']['employee_advances'];

    expect(advanceRows($f)->pluck('id')->sort()->values()->all())->toBe(collect($rows)->pluck('advance_id')->sort()->values()->all());
});

test('reopening and re-posting a close reuses its advances instead of adding copies', function () {
    $f = advanceReuseFixture();
    $before = advanceRows($f)->keyBy('amount')->map->id;

    reopenAndRepost($f);

    $after = advanceRows($f);
    expect($after)->toHaveCount(2)
        ->and($after->whereNotNull('deleted_at'))->toHaveCount(0)
        ->and($after->keyBy('amount')->map->id->all())->toEqual($before->all());

    $statement = app(EmployeeStatementService::class)->statement($f['employee'], '2026-09-01', '2026-09-30');
    expect(abs($statement['closing_balance']))->toBe(11000.0);
});

test('three reopen and re-post cycles still leave one row per advance', function () {
    $f = advanceReuseFixture();
    $ids = advanceRows($f)->pluck('id')->sort()->values()->all();

    foreach (range(1, 3) as $cycle) {
        reopenAndRepost($f);
    }

    expect(advanceRows($f)->pluck('id')->sort()->values()->all())->toBe($ids)
        ->and(advanceRows($f)->whereNotNull('deleted_at'))->toHaveCount(0)
        ->and(SalaryAdvance::withTrashed()->where('company_id', $f['company']->id)->count())->toBe(2);
});

test('an advance edited in the draft updates the same row', function () {
    $f = advanceReuseFixture();
    $row = advanceRows($f)->firstWhere('amount', 8000);

    reopenAndRepost($f, function (array $draft) {
        foreach ($draft['employee_advances'] as $k => $r) {
            if ((float) $r['amount'] === 8000.0) {
                $draft['employee_advances'][$k]['amount'] = 9500;
                $draft['employee_advances'][$k]['reason'] = 'Bike and tyres';
            }
        }
        $draft['closing_cash'] -= 1500;

        return $draft;
    });

    $fresh = $row->fresh();
    expect(advanceRows($f))->toHaveCount(2)
        ->and((float) $fresh->amount)->toBe(9500.0)
        ->and((float) $fresh->amount_outstanding)->toBe(9500.0)
        ->and($fresh->reason)->toBe('Bike and tyres')
        ->and($fresh->deleted_at)->toBeNull()
        ->and($fresh->journal_entry_id)->not->toBeNull();
});

test('an advance removed from the draft stays deleted and is not recreated', function () {
    $f = advanceReuseFixture();
    $gone = advanceRows($f)->firstWhere('amount', 3000);
    $kept = advanceRows($f)->firstWhere('amount', 8000);

    reopenAndRepost($f, function (array $draft) {
        $draft['employee_advances'] = array_values(array_filter($draft['employee_advances'], fn ($r) => (float) $r['amount'] !== 3000.0));
        $draft['closing_cash'] += 3000;

        return $draft;
    });

    expect(SalaryAdvance::withTrashed()->where('company_id', $f['company']->id)->count())->toBe(2)
        ->and(SalaryAdvance::find($gone->id))->toBeNull()
        ->and(SalaryAdvance::withTrashed()->find($gone->id))->not->toBeNull()
        ->and(SalaryAdvance::find($kept->id))->not->toBeNull();
});

test('an advance added in the draft is created once and then reused', function () {
    $f = advanceReuseFixture([[8000, 'Petrol for bike']]);

    reopenAndRepost($f, function (array $draft) {
        $draft['employee_advances'][] = ['employee_id' => $draft['employee_advances'][0]['employee_id'], 'amount' => 500, 'reason' => 'Tea'];
        $draft['closing_cash'] -= 500;

        return $draft;
    });
    expect(SalaryAdvance::withTrashed()->where('company_id', $f['company']->id)->count())->toBe(2);

    reopenAndRepost($f);
    expect(SalaryAdvance::withTrashed()->where('company_id', $f['company']->id)->count())->toBe(2)
        ->and(SalaryAdvance::where('company_id', $f['company']->id)->count())->toBe(2);
});

test('an advance with repayments stays locked with its repayments intact', function () {
    $f = advanceReuseFixture();
    $repaid = advanceRows($f)->firstWhere('amount', 8000);
    $repaid->update(['amount_recovered' => 2000, 'amount_outstanding' => 6000, 'status' => 'partially_recovered']);
    $recovery = SalaryAdvanceRecovery::create([
        'company_id' => $f['company']->id, 'salary_advance_id' => $repaid->id, 'recovery_date' => '2026-09-20',
        'amount' => 2000, 'recovery_type' => 'payroll_deduction',
    ]);

    $draft = reopenAndRepost($f);

    $row = collect($draft['employee_advances'])->firstWhere('advance_id', $repaid->id);
    $fresh = $repaid->fresh();
    expect($row['kept_advance_id'])->toBe($repaid->id)
        ->and(SalaryAdvance::withTrashed()->where('company_id', $f['company']->id)->count())->toBe(2)
        ->and($fresh->deleted_at)->toBeNull()
        ->and((float) $fresh->amount)->toBe(8000.0)
        ->and((float) $fresh->amount_recovered)->toBe(2000.0)
        ->and((float) $fresh->amount_outstanding)->toBe(6000.0)
        ->and($fresh->journal_entry_id)->not->toBeNull()
        ->and(SalaryAdvanceRecovery::where('salary_advance_id', $repaid->id)->whereKey($recovery->id)->exists())->toBeTrue();
});

test('a close posted before ids were stored is matched by employee and amount on reopen', function () {
    $f = advanceReuseFixture();
    $ids = advanceRows($f)->pluck('id')->sort()->values()->all();

    // Strip the ids as an older close's form input would not have them.
    $metadata = $f['close']->metadata;
    foreach ($metadata['form_input']['employee_advances'] as $k => $r) {
        unset($metadata['form_input']['employee_advances'][$k]['advance_id']);
    }
    DB::select("SELECT set_config('app.reopening_close_id', ?, false)", [$f['close']->id]); // the snapshot trigger's own bypass
    DB::table('acct.transactions')->where('id', $f['close']->id)->update(['metadata' => json_encode($metadata)]);
    DB::select("SELECT set_config('app.reopening_close_id', '', false)");
    $f['close'] = $f['close']->fresh();

    reopenAndRepost($f);

    expect(advanceRows($f)->pluck('id')->sort()->values()->all())->toBe($ids);
});
