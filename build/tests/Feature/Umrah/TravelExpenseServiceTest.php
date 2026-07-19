<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Expense;
use App\Modules\Umrah\Services\TravelExpenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

test('travel expenses are available to company accounting roles but not agents', function () {
    expect(config('role-permissions.admin'))->toContain('umrah.expense.view', 'umrah.expense.create', 'umrah.expense.reverse')
        ->and(config('role-permissions.accountant'))->toContain('umrah.expense.view', 'umrah.expense.create', 'umrah.expense.reverse')
        ->and(config('role-permissions.agent'))->not->toContain('umrah.expense.view', 'umrah.expense.create', 'umrah.expense.reverse');
});

test('travel expense posts a multicurrency paid expense and reverses it', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Travel Expense Test',
        'slug' => 'travel-expense-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    Auth::login($user);

    $expenseAccount = Account::create([
        'company_id' => $company->id,
        'code' => '6100',
        'name' => 'Travel Office Expense',
        'type' => 'expense',
        'subtype' => 'expense',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    $cashAccount = Account::create([
        'company_id' => $company->id,
        'code' => '1050',
        'name' => 'Travel Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'SAR',
        'is_active' => true,
    ]);

    $service = app(TravelExpenseService::class);
    $expense = $service->record($company, [
        'expense_number' => null,
        'expense_date' => '2026-07-19',
        'expense_account_id' => $expenseAccount->id,
        'payment_account_id' => $cashAccount->id,
        'payee' => 'Office supplier',
        'description' => 'Travel document stationery',
        'reference' => 'RCPT-10',
        'amount' => 100,
        'currency' => 'USD',
        'exchange_rate' => 3.75,
    ], $user->id);

    $entries = $expense->transaction->journalEntries()->orderBy('line_number')->get();
    expect($expense->expense_number)->toStartWith('UEX-')
        ->and((float) $expense->base_amount)->toBe(375.0)
        ->and($expense->currency)->toBe('USD')
        ->and((float) $entries[0]->debit_amount)->toBe(375.0)
        ->and((float) $entries[1]->credit_amount)->toBe(375.0)
        ->and((float) $entries[0]->currency_debit)->toBe(100.0)
        ->and((float) $entries[1]->currency_credit)->toBe(100.0);

    $reversed = $service->reverse($expense, 'Receipt was entered twice.', $user->id);

    expect($reversed->status)->toBe(Expense::STATUS_REVERSED)
        ->and($reversed->reversal_transaction_id)->not->toBeNull()
        ->and($expense->transaction->fresh()->reversed_by_id)->toBe($reversed->reversal_transaction_id);
});
