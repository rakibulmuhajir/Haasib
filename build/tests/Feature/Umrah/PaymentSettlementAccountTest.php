<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * A chart with BOTH a bank and a cash account, which is the only shape that
 * can tell the two apart. Most companies have both; the older tests happen to
 * have only cash, so they could not have caught a cash receipt landing in the
 * bank.
 */
function settlementCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Settlement '.str()->random(8),
        'slug' => 'settlement-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $accounts = [
        ['1000', 'Operating Bank Account', 'asset', 'bank', 'debit'],
        ['1001', 'Cash on Hand', 'asset', 'cash', 'debit'],
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2200', 'Agent Advances', 'liability', 'other_current_liability', 'credit'],
    ];

    foreach ($accounts as [$code, $name, $type, $subtype, $normal]) {
        Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => $normal,
            'currency' => 'SAR',
            'is_active' => true,
        ]);
    }

    // The realistic case, and the one that used to fail: a company that has
    // nominated its bank account. bank_or_cash short-circuits on this before
    // it ever looks at subtype.
    $company->forceFill([
        'bank_account_id' => Account::where('company_id', $company->id)->where('code', '1000')->value('id'),
    ])->save();

    $agentUser = User::factory()->withoutTwoFactor()->create();
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Field Agent',
        'user_id' => $agentUser->id,
        'is_active' => true,
    ]);

    return [$company, $owner, $agent];
}

function settlementDebitedAccountCode(GroupPayment $payment): ?string
{
    return DB::table('acct.journal_entries as l')
        ->join('acct.accounts as a', 'a.id', '=', 'l.account_id')
        ->where('l.transaction_id', $payment->transaction_id)
        ->where('l.debit_amount', '>', 0)
        ->value('a.code');
}

test('a cash receipt is posted to the cash account, not the bank', function () {
    [$company, $owner, $agent] = settlementCompany();

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/payments", [
            'payment_date' => '2026-08-15',
            'direction' => GroupPayment::DIRECTION_RECEIVED,
            'agent_id' => $agent->id,
            'amount' => 500,
            'currency' => 'SAR',
            'method' => GroupPayment::METHOD_CASH,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment = GroupPayment::where('company_id', $company->id)->firstOrFail();

    expect($payment->account_id)->toBeNull()
        ->and(settlementDebitedAccountCode($payment))->toBe('1001');
});

test('a bank transfer receipt is still posted to the bank account', function () {
    [$company, $owner, $agent] = settlementCompany();

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/payments", [
            'payment_date' => '2026-08-15',
            'direction' => GroupPayment::DIRECTION_RECEIVED,
            'agent_id' => $agent->id,
            'amount' => 500,
            'currency' => 'SAR',
            'method' => GroupPayment::METHOD_BANK_TRANSFER,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment = GroupPayment::where('company_id', $company->id)->firstOrFail();

    expect(settlementDebitedAccountCode($payment))->toBe('1000');
});

test('an explicitly chosen account still wins over the method', function () {
    [$company, $owner, $agent] = settlementCompany();
    $bankId = Account::where('company_id', $company->id)->where('code', '1000')->value('id');

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/payments", [
            'payment_date' => '2026-08-15',
            'direction' => GroupPayment::DIRECTION_RECEIVED,
            'agent_id' => $agent->id,
            'amount' => 500,
            'currency' => 'SAR',
            'method' => GroupPayment::METHOD_CASH,
            'account_id' => $bankId,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment = GroupPayment::where('company_id', $company->id)->firstOrFail();

    expect(settlementDebitedAccountCode($payment))->toBe('1000');
});
