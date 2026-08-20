<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\VisaGroup;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

function paymentReviewCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payment Review '.str()->random(8),
        'slug' => 'payment-review-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    paymentReviewMember($company, $owner, 'owner');

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    Account::create([
        'company_id' => $company->id,
        'code' => '1050',
        'name' => 'Operating Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'SAR',
        'is_active' => true,
    ]);
    Account::create([
        'company_id' => $company->id,
        'code' => '2200',
        'name' => 'Agent Advances',
        'type' => 'liability',
        'subtype' => 'other_current_liability',
        'normal_balance' => 'credit',
        'currency' => 'SAR',
        'is_active' => true,
    ]);
    Account::create([
        'company_id' => $company->id,
        'code' => '1100',
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'subtype' => 'accounts_receivable',
        'normal_balance' => 'debit',
        'currency' => 'SAR',
        'is_active' => true,
    ]);

    DB::table('auth.company_currencies')->insert([
        'id' => (string) str()->orderedUuid(),
        'company_id' => $company->id,
        'currency_code' => 'USD',
        'exchange_rate' => 3.75,
        'enabled_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$company, $owner];
}

function paymentReviewMember(Company $company, User $user, string $role): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, $role),
    );
}

function paymentReviewAgent(Company $company, string $agentUserEmailSeed = 'agent'): array
{
    $agentUser = User::factory()->withoutTwoFactor()->create();
    paymentReviewMember($company, $agentUser, 'agent');

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Field Agent '.$agentUserEmailSeed,
        'user_id' => $agentUser->id,
        'is_active' => true,
    ]);

    return [$agentUser, $agent];
}

test('agent can submit a payment and it posts nothing', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);

    $this->actingAs($agentUser)
        ->post("/{$company->slug}/umrah/payments/submit", [
            'payment_date' => '2026-08-15',
            'amount' => 500,
            'currency' => 'SAR',
            'method' => GroupPayment::METHOD_CASH,
            'reference' => 'RCPT-1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment = GroupPayment::where('company_id', $company->id)->firstOrFail();

    expect($payment->status)->toBe(GroupPayment::STATUS_SUBMITTED)
        ->and($payment->submitted_by_user_id)->toBe($agentUser->id)
        ->and($payment->submitted_at)->not->toBeNull()
        ->and($payment->base_amount)->toBeNull()
        ->and($payment->transaction_id)->toBeNull()
        ->and($payment->agent_id)->toBe($agent->id);

    $agent->refresh();
    expect((float) $agent->balance)->toBe(0.0)
        ->and((float) $agent->total_paid)->toBe(0.0);
});

test('agent cannot approve a submitted payment', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);

    $payment = app(App\Modules\Umrah\Services\UmrahCoreService::class)->submitPayment($company->id, [
        'agent_id' => $agent->id,
        'payment_date' => '2026-08-15',
        'amount' => 500,
        'currency' => 'SAR',
        'method' => GroupPayment::METHOD_CASH,
        'submitted_by_user_id' => $agentUser->id,
    ]);

    $this->actingAs($agentUser)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(GroupPayment::STATUS_SUBMITTED);
});

test('accountant approves a base currency payment, posting it and moving the agent balance', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);
    $accountant = User::factory()->withoutTwoFactor()->create();
    paymentReviewMember($company, $accountant, 'accountant');

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-REV1',
        'name' => 'Review Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-01',
        'transport_required' => false,
        'visa_sale_amount' => 1000,
    ]);
    app(App\Modules\Umrah\Services\UmrahCoreService::class)->recalculateGroup($group);
    app(App\Modules\Umrah\Services\UmrahCoreService::class)->recalculateAgent($agent->id);

    $payment = app(App\Modules\Umrah\Services\UmrahCoreService::class)->submitPayment($company->id, [
        'agent_id' => $agent->id,
        'visa_group_id' => $group->id,
        'payment_date' => '2026-08-15',
        'amount' => 400,
        'currency' => 'SAR',
        'method' => GroupPayment::METHOD_CASH,
        'submitted_by_user_id' => $agentUser->id,
    ]);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'approve',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe(GroupPayment::STATUS_POSTED)
        ->and((float) $payment->base_amount)->toBe(400.0)
        ->and($payment->transaction_id)->not->toBeNull()
        ->and($payment->reviewed_by_user_id)->toBe($accountant->id)
        ->and($payment->reviewed_at)->not->toBeNull();

    $agent->refresh();
    expect((float) $agent->total_paid)->toBe(400.0)
        ->and((float) $agent->balance)->toBe(600.0);
});

test('accountant approves a secondary currency payment producing the correct base amount', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);
    $accountant = User::factory()->withoutTwoFactor()->create();
    paymentReviewMember($company, $accountant, 'accountant');

    $payment = app(App\Modules\Umrah\Services\UmrahCoreService::class)->submitPayment($company->id, [
        'agent_id' => $agent->id,
        'payment_date' => '2026-08-15',
        'amount' => 100,
        'currency' => 'USD',
        'method' => GroupPayment::METHOD_CASH,
        'submitted_by_user_id' => $agentUser->id,
    ]);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'approve',
            'currency' => 'USD',
            'exchange_rate' => 3.75,
            'review_remarks' => 'Exchange rate applied at review time.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe(GroupPayment::STATUS_POSTED)
        ->and($payment->currency)->toBe('USD')
        ->and((float) $payment->exchange_rate)->toBe(3.75)
        ->and((float) $payment->base_amount)->toBe(375.0)
        ->and($payment->transaction_id)->not->toBeNull();
});

test('rejection requires remarks and posts nothing', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);
    $accountant = User::factory()->withoutTwoFactor()->create();
    paymentReviewMember($company, $accountant, 'accountant');

    $payment = app(App\Modules\Umrah\Services\UmrahCoreService::class)->submitPayment($company->id, [
        'agent_id' => $agent->id,
        'payment_date' => '2026-08-15',
        'amount' => 500,
        'currency' => 'SAR',
        'method' => GroupPayment::METHOD_CASH,
        'submitted_by_user_id' => $agentUser->id,
    ]);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'reject',
        ])
        ->assertSessionHasErrors('review_remarks');

    expect($payment->fresh()->status)->toBe(GroupPayment::STATUS_SUBMITTED);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'reject',
            'review_remarks' => 'Receipt does not match the collected amount.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe(GroupPayment::STATUS_REJECTED)
        ->and($payment->review_remarks)->toBe('Receipt does not match the collected amount.')
        ->and($payment->transaction_id)->toBeNull()
        ->and($payment->base_amount)->toBeNull()
        ->and($payment->reviewed_by_user_id)->toBe($accountant->id);
});

test('approving with a corrected amount requires remarks', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);
    $accountant = User::factory()->withoutTwoFactor()->create();
    paymentReviewMember($company, $accountant, 'accountant');

    $payment = app(App\Modules\Umrah\Services\UmrahCoreService::class)->submitPayment($company->id, [
        'agent_id' => $agent->id,
        'payment_date' => '2026-08-15',
        'amount' => 500,
        'currency' => 'SAR',
        'method' => GroupPayment::METHOD_CASH,
        'submitted_by_user_id' => $agentUser->id,
    ]);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'approve',
            'amount' => 450,
        ])
        ->assertSessionHasErrors('review_remarks');

    expect($payment->fresh()->status)->toBe(GroupPayment::STATUS_SUBMITTED);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'approve',
            'amount' => 450,
            'review_remarks' => 'Agent over-reported; corrected to receipt total.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe(GroupPayment::STATUS_POSTED)
        ->and((float) $payment->amount)->toBe(450.0)
        ->and((float) $payment->base_amount)->toBe(450.0)
        ->and($payment->review_remarks)->toBe('Agent over-reported; corrected to receipt total.');
});

test('a non submitted payment cannot be reviewed', function () {
    [$company, $owner] = paymentReviewCompany();
    [$agentUser, $agent] = paymentReviewAgent($company);
    $accountant = User::factory()->withoutTwoFactor()->create();
    paymentReviewMember($company, $accountant, 'accountant');

    $payment = app(App\Modules\Umrah\Services\UmrahCoreService::class)->submitPayment($company->id, [
        'agent_id' => $agent->id,
        'payment_date' => '2026-08-15',
        'amount' => 500,
        'currency' => 'SAR',
        'method' => GroupPayment::METHOD_CASH,
        'submitted_by_user_id' => $agentUser->id,
    ]);

    app(App\Modules\Umrah\Services\UmrahCoreService::class)->reviewPayment($payment, 'approve', [], $accountant->id);
    expect($payment->fresh()->status)->toBe(GroupPayment::STATUS_POSTED);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/payments/{$payment->id}/review", [
            'decision' => 'approve',
        ])
        ->assertSessionHasErrors('payment');

    expect($payment->fresh()->status)->toBe(GroupPayment::STATUS_POSTED);
});
