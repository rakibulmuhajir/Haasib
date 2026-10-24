<?php

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\AmanatTransactionChange;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Services\AmanatStatementService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/DailyCloseWorkflowFixtures.php';

/*
 * Opening an amanat movement and fixing it: change the holder (any source) or, for a standalone
 * deposit/withdrawal, correct the amount (reverse + void + replace). Balances and books must
 * agree after every change, and a voided row must be counted nowhere.
 */
function amanatCorrectionFixture(): array
{
    $f = closeWorkflowFixture();
    enableCloseHttp($f);
    Account::create(['company_id' => $f['company']->id, 'code' => '2200', 'name' => 'Customer Amanat Deposits', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $f['a'] = amanatCorrectionHolder($f, 'AC-A', 'Holder A');
    $f['b'] = amanatCorrectionHolder($f, 'AC-B', 'Holder B');
    $f['base'] = "/{$f['company']->slug}/fuel/amanat";

    return $f;
}

function amanatCorrectionHolder(array $f, string $number, string $name): Customer
{
    $customer = Customer::create(['company_id' => $f['company']->id, 'customer_number' => $number, 'name' => $name, 'base_currency' => 'PKR']);
    CustomerProfile::getOrCreateForCustomer($f['company']->id, $customer->id)->update(['is_amanat_holder' => true]);

    return $customer;
}

function amanatCorrectionBalance(Customer $customer): float
{
    return (float) CustomerProfile::where('customer_id', $customer->id)->value('amanat_balance');
}

/** Every account's debit and credit total: the books, independent of who holds the amanat. */
function amanatCorrectionGl(string $companyId): array
{
    return DB::table('acct.journal_entries')->where('company_id', $companyId)
        ->selectRaw('account_id, SUM(debit_amount) AS debit, SUM(credit_amount) AS credit')
        ->groupBy('account_id')->orderBy('account_id')->get()
        ->map(fn ($r) => [$r->account_id, round((float) $r->debit, 2), round((float) $r->credit, 2)])->all();
}

function amanatCorrectionDeposit(array $f, Customer $customer, float $amount, string $date = '2026-09-15'): AmanatTransaction
{
    test()->post("{$f['base']}/{$customer->id}/deposit", ['business_date' => $date, 'amount' => $amount])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    return AmanatTransaction::where('customer_id', $customer->id)->orderByDesc('created_at')->firstOrFail();
}

function amanatCorrectionPostClose(array $f, Customer $holder, float $amount): AmanatTransaction
{
    $f['payload']['amanat_deposits'] = [['customer_id' => $holder->id, 'amount' => $amount]];
    $f['payload']['closing_cash'] = $f['payload']['opening_cash'] + $amount;
    test()->post("/{$f['company']->slug}/fuel/daily-close", $f['payload'])->assertSessionHasNoErrors()->assertSessionHas('success');

    return AmanatTransaction::where('customer_id', $holder->id)->firstOrFail();
}

test('changing the holder of a standalone deposit moves both balances and leaves the books alone', function () {
    $f = amanatCorrectionFixture();
    $movement = amanatCorrectionDeposit($f, $f['a'], 1000);
    $glBefore = amanatCorrectionGl($f['company']->id);

    test()->post("{$f['base']}/{$f['a']->id}/movements/{$movement->id}/holder", ['customer_id' => $f['b']->id])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($movement->fresh()->customer_id)->toBe($f['b']->id)
        ->and(amanatCorrectionBalance($f['a']))->toBe(0.0)
        ->and(amanatCorrectionBalance($f['b']))->toBe(1000.0)
        ->and(amanatCorrectionGl($f['company']->id))->toBe($glBefore);

    $change = AmanatTransactionChange::where('amanat_transaction_id', $movement->id)->sole();
    expect($change->action)->toBe('holder_changed')
        ->and($change->user_id)->toBe($f['user']->id)
        ->and($change->before['customer_id'])->toBe($f['a']->id)
        ->and($change->after['customer_id'])->toBe($f['b']->id);
});

test('changing the holder is refused when it would take the old holder below zero', function () {
    $f = amanatCorrectionFixture();
    $deposit = amanatCorrectionDeposit($f, $f['a'], 1000);
    test()->post("{$f['base']}/{$f['a']->id}/withdraw", ['business_date' => '2026-09-15', 'amount' => 800])
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(amanatCorrectionBalance($f['a']))->toBe(200.0);

    test()->post("{$f['base']}/{$f['a']->id}/movements/{$deposit->id}/holder", ['customer_id' => $f['b']->id])
        ->assertSessionHasErrors('customer_id');

    expect($deposit->fresh()->customer_id)->toBe($f['a']->id)
        ->and(amanatCorrectionBalance($f['a']))->toBe(200.0)
        ->and(amanatCorrectionBalance($f['b']))->toBe(0.0);

    // The same move is fine once the holder may borrow.
    CustomerProfile::where('customer_id', $f['a']->id)->update(['allow_amanat_borrowing' => true]);
    test()->post("{$f['base']}/{$f['a']->id}/movements/{$deposit->id}/holder", ['customer_id' => $f['b']->id])
        ->assertSessionHasNoErrors();
    expect(amanatCorrectionBalance($f['a']))->toBe(-800.0)
        ->and(amanatCorrectionBalance($f['b']))->toBe(1000.0);
});

test('changing the holder of a Daily Close row also moves it inside the close form', function () {
    $f = amanatCorrectionFixture();
    $movement = amanatCorrectionPostClose($f, $f['a'], 500);
    expect(amanatCorrectionBalance($f['a']))->toBe(500.0);
    $glBefore = amanatCorrectionGl($f['company']->id);

    test()->post("{$f['base']}/{$f['a']->id}/movements/{$movement->id}/holder", ['customer_id' => $f['b']->id])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->firstOrFail();
    expect($movement->fresh()->customer_id)->toBe($f['b']->id)
        ->and(amanatCorrectionBalance($f['a']))->toBe(0.0)
        ->and(amanatCorrectionBalance($f['b']))->toBe(500.0)
        ->and($close->metadata['form_input']['amanat_deposits'][0]['customer_id'])->toBe($f['b']->id)
        ->and($close->metadata['amanat_deposit_details'][0]['customer_id'])->toBe($f['b']->id)
        ->and($close->metadata['amanat_deposit_details'][0]['customer_name'])->toBe('Holder B')
        ->and(amanatCorrectionGl($f['company']->id))->toBe($glBefore);
});

test('editing the amount of a standalone deposit reverses, voids and replaces it', function () {
    $f = amanatCorrectionFixture();
    $original = amanatCorrectionDeposit($f, $f['a'], 1000);
    $originalJournal = $original->journalEntry->transaction;

    test()->post("{$f['base']}/{$f['a']->id}/movements/{$original->id}/amend", [
        'business_date' => '2026-09-16', 'amount' => 700, 'reference' => 'Fixed',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $original = $original->fresh();
    $replacement = AmanatTransaction::findOrFail($original->replaced_by_id);
    $amanatAccount = Account::where('company_id', $f['company']->id)->where('code', '2200')->firstOrFail();

    expect($original->voided_at)->not->toBeNull()
        ->and($original->voided_by_user_id)->toBe($f['user']->id)
        ->and($replacement->voided_at)->toBeNull()
        ->and((float) $replacement->amount)->toBe(700.0)
        ->and($replacement->reference)->toBe('Fixed')
        ->and(Transaction::where('company_id', $f['company']->id)->where('reversal_of_id', $originalJournal->id)->exists())->toBeTrue()
        ->and(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'amanat_deposit')->count())->toBe(2)
        ->and(amanatCorrectionBalance($f['a']))->toBe(700.0);

    // Net of the reversal, the Amanat liability in the books is the corrected figure.
    $liability = DB::table('acct.journal_entries')->where('company_id', $f['company']->id)->where('account_id', $amanatAccount->id)
        ->selectRaw('SUM(credit_amount) - SUM(debit_amount) AS net')->value('net');
    expect(round((float) $liability, 2))->toBe(700.0);

    $change = AmanatTransactionChange::where('amanat_transaction_id', $original->id)->sole();
    expect($change->action)->toBe('amount_corrected')
        ->and($change->before['amount'])->toBe(1000.0)
        ->and($change->after['amount'])->toBe(700.0);
});

test('a corrected row is not counted in the holder balance or the history totals', function () {
    $f = amanatCorrectionFixture();
    $original = amanatCorrectionDeposit($f, $f['a'], 1000);
    test()->post("{$f['base']}/{$f['a']->id}/movements/{$original->id}/amend", ['business_date' => '2026-09-15', 'amount' => 700])
        ->assertSessionHasNoErrors();

    expect(AmanatTransaction::where('customer_id', $f['a']->id)->count())->toBe(2)
        ->and((float) AmanatTransaction::active()->where('customer_id', $f['a']->id)->sum('amount'))->toBe(700.0)
        ->and(amanatCorrectionBalance($f['a']))->toBe(700.0);

    $statement = app(AmanatStatementService::class)->statement($f['a'], '2026-09-01', '2026-09-30');
    expect($statement['closing_balance'])->toBe(700.0)
        ->and(collect($statement['rows'])->where('type', 'deposit')->count())->toBe(1);

    // The holder page still lists both, the first flagged as corrected.
    $rows = collect(test()->get("{$f['base']}/{$f['a']->id}")->assertOk()->viewData('page')['props']['transactions']['data']);
    expect($rows)->toHaveCount(2)
        ->and($rows->where('detail.voided', true))->toHaveCount(1);
});

test('the amount of a Daily Close row cannot be edited in place', function () {
    $f = amanatCorrectionFixture();
    $movement = amanatCorrectionPostClose($f, $f['a'], 500);

    test()->post("{$f['base']}/{$f['a']->id}/movements/{$movement->id}/amend", ['business_date' => '2026-09-15', 'amount' => 400])
        ->assertSessionHasErrors('amount');

    expect($movement->fresh()->voided_at)->toBeNull()
        ->and((float) $movement->fresh()->amount)->toBe(500.0)
        ->and(amanatCorrectionBalance($f['a']))->toBe(500.0);
});

test('a user without amanat.update is refused', function () {
    $f = amanatCorrectionFixture();
    $movement = amanatCorrectionDeposit($f, $f['a'], 1000);

    $clerk = User::factory()->create();
    addCompanyMemberRow($f['company'], $clerk, 'operations');
    test()->actingAs($clerk);

    test()->post("{$f['base']}/{$f['a']->id}/movements/{$movement->id}/holder", ['customer_id' => $f['b']->id])->assertForbidden();
    test()->post("{$f['base']}/{$f['a']->id}/movements/{$movement->id}/amend", ['business_date' => '2026-09-15', 'amount' => 5])->assertForbidden();

    expect($movement->fresh()->customer_id)->toBe($f['a']->id)
        ->and(amanatCorrectionBalance($f['a']))->toBe(1000.0);
});
