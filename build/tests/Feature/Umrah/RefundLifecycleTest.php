<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\RefundService;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 1 of docs/contracts/refunds.md -- "The obligation". These tests
 * cover the lifecycle (request -> approve/reject, approve -> cancel) and its
 * illegal transitions, permission gating, agent self-service scoping, and
 * the two invariants that hold without Phase 2's ledger: amount is always
 * positive (invariant 1) and an approved refund's amount/party_id/service
 * are immutable (invariant 4). Invariant 3 (credit ceiling) is checked here
 * as the honest denormalised-balance approximation RefundService documents
 * -- not a stub, but not the eventual GL-backed check either.
 */
function refundTestCompany(string $baseCurrency = 'SAR'): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Refund Test '.str()->random(8),
        'slug' => 'refund-test-'.str()->lower(str()->random(10)),
        'base_currency' => $baseCurrency,
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    refundTestMember($company, $owner, 'owner');

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    refundTestAccounts($company);

    return [$company, $owner];
}

/**
 * Phase 2b postings need real accounts to post to -- Phase 1's tests never
 * did, so refundTestCompany() never created any. This is the same set
 * database/migrations/2026_08_21_000003_add_umrah_refund_accounts.php and
 * database/migrations/2026_06_23_000002_add_umrah_industry_coa_pack.php put
 * on a real company, kept minimal to exactly what refund postings touch:
 * 1000 bank, 2200 agent advances, 2300 refunds payable, 1170 refunds
 * receivable, and the three 51xx cost accounts.
 */
function refundTestAccounts(Company $company): void
{
    $accounts = [
        ['1000', 'Operating Bank Account', 'asset', 'bank', 'debit'],
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2200', 'Agent Advances', 'liability', 'other_current_liability', 'credit'],
        ['2300', 'Refunds Payable', 'liability', 'other_current_liability', 'credit'],
        ['1170', 'Refunds Receivable', 'asset', 'other_current_asset', 'debit'],
        ['5100', 'Visa Cost', 'cogs', 'cogs', 'debit'],
        ['5110', 'Transport Cost', 'cogs', 'cogs', 'debit'],
        ['5120', 'Hotel Cost', 'cogs', 'cogs', 'debit'],
    ];

    // accounts_currency_allowed_chk only permits a currency on subtypes that
    // actually hold foreign-currency balances (bank, cash, AR/AP, etc) --
    // the COGS subtype used by 5100/5110/5120 must stay null, same as the
    // real COA pack in database/migrations/2026_06_23_000002_add_umrah_industry_coa_pack.php.
    $currencyBearing = ['bank', 'cash', 'accounts_receivable', 'accounts_payable', 'credit_card', 'other_current_asset', 'other_asset', 'other_current_liability', 'other_liability'];

    foreach ($accounts as [$code, $name, $type, $subtype, $normal]) {
        Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => $normal,
            'currency' => in_array($subtype, $currencyBearing, true) ? $company->base_currency : null,
            'is_active' => true,
        ]);
    }

    $company->forceFill([
        'bank_account_id' => Account::where('company_id', $company->id)->where('code', '1000')->value('id'),
        'ar_account_id' => Account::where('company_id', $company->id)->where('code', '1100')->value('id'),
    ])->save();
}

function refundTestBankAccount(Company $company): Account
{
    return Account::where('company_id', $company->id)->where('code', '1000')->firstOrFail();
}

// Named distinctly from PaymentAllocationReversalTest.php's journalTotals()
// -- Pest loads every test file's top-level functions into one global
// namespace, and that one doesn't return the account-level rows these
// posting-shape assertions need.
function refundJournalTotals(?string $transactionId): array
{
    $rows = DB::table('acct.journal_entries')->where('transaction_id', $transactionId)->get(['debit_amount', 'credit_amount', 'account_id']);

    return [
        'debit' => (float) $rows->sum('debit_amount'),
        'credit' => (float) $rows->sum('credit_amount'),
        'rows' => $rows,
    ];
}

function refundTestMember(Company $company, User $user, string $role): void
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

function refundTestAgent(Company $company, string $seed = 'agent', float $totalPaid = 0.0, float $totalReceivable = 0.0): array
{
    $agentUser = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $agentUser, 'agent');

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Field Agent '.$seed,
        'user_id' => $agentUser->id,
        'is_active' => true,
        'total_paid' => $totalPaid,
        'total_receivable' => $totalReceivable,
        'balance' => $totalReceivable - $totalPaid,
    ]);

    return [$agentUser, $agent];
}

function refundTestVisaVendor(Company $company, string $seed = 'vendor', float $totalPaid = 0.0, float $totalCost = 0.0): VisaVendor
{
    return VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEN-'.strtoupper(str()->random(5)),
        'name' => 'Visa Vendor '.$seed,
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'total_cost' => $totalCost,
        'total_paid' => $totalPaid,
        'balance' => $totalCost - $totalPaid,
        'is_active' => true,
    ]);
}

function requestRefundPayload(Agent $agent, float $amount = 200.0): array
{
    return [
        'party_type' => Refund::PARTY_AGENT,
        'party_id' => $agent->id,
        'service' => Refund::SERVICE_OTHER,
        'refund_number' => null,
        'amount' => $amount,
        'currency' => 'SAR',
        'reason' => 'Overpaid on visa package, refunding the excess.',
    ];
}

function requestVendorRefundPayload(VisaVendor $vendor, float $amount = 200.0): array
{
    return [
        'party_type' => Refund::PARTY_VISA_VENDOR,
        'party_id' => $vendor->id,
        'service' => Refund::SERVICE_VISA,
        'refund_number' => null,
        'amount' => $amount,
        'currency' => 'SAR',
        'reason' => 'Visa fee paid but never processed by the vendor.',
    ];
}

function requestForeignCurrencyVendorRefundPayload(VisaVendor $vendor, float $amount, string $currency, float $exchangeRate): array
{
    return [
        'party_type' => Refund::PARTY_VISA_VENDOR,
        'party_id' => $vendor->id,
        'service' => Refund::SERVICE_VISA,
        'refund_number' => null,
        'amount' => $amount,
        'currency' => $currency,
        'exchange_rate' => $exchangeRate,
        'reason' => 'Visa fee paid but never processed by the vendor.',
    ];
}

test('a refund moves from requested to approved and stamps the approver', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'a', totalPaid: 500.0, totalReceivable: 200.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $this->actingAs($agentUser)
        ->post("/{$company->slug}/umrah/refunds", requestRefundPayload($agent, 200))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $refund = Refund::where('company_id', $company->id)->firstOrFail();
    expect($refund->status)->toBe(Refund::STATUS_REQUESTED)
        ->and($refund->requested_by_user_id)->toBe($agentUser->id)
        ->and($refund->party_id)->toBe($agent->id)
        ->and((float) $refund->amount)->toBe(200.0)
        ->and((float) $refund->base_amount)->toBe(200.0);

    $this->actingAs($manager)
        ->post("/{$company->slug}/umrah/refunds/{$refund->id}/approve", [
            'review_remarks' => 'Confirmed against the group ledger.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $refund->refresh();
    expect($refund->status)->toBe(Refund::STATUS_ACCEPTED)
        ->and($refund->reviewed_by_user_id)->toBe($manager->id)
        ->and($refund->reviewed_at)->not->toBeNull()
        ->and($refund->review_remarks)->toBe('Confirmed against the group ledger.')
        ->and($refund->transaction_id)->not->toBeNull();
});

test('a refund moves from requested to rejected, stamps the decider, and posts nothing', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'b', totalPaid: 500.0, totalReceivable: 0.0);
    $accountant = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $accountant, 'accountant');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);

    $this->actingAs($accountant)
        ->post("/{$company->slug}/umrah/refunds/{$refund->id}/reject", [
            'review_remarks' => 'Group already refunded through a different channel.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $refund->refresh();
    expect($refund->status)->toBe(Refund::STATUS_REJECTED)
        ->and($refund->review_remarks)->toBe('Group already refunded through a different channel.')
        ->and($refund->reviewed_by_user_id)->toBe($accountant->id)
        ->and($refund->reviewed_at)->not->toBeNull()
        ->and($refund->transaction_id)->toBeNull();
});

test('an approved refund can be cancelled before settlement', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'c', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);

    $this->actingAs($manager)
        ->post("/{$company->slug}/umrah/refunds/{$refund->id}/cancel", [
            'cancellation_reason' => 'Agent withdrew the request before settlement.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $refund->refresh();
    expect($refund->status)->toBe(Refund::STATUS_CANCELLED)
        ->and($refund->cancelled_by_user_id)->toBe($manager->id)
        ->and($refund->cancelled_at)->not->toBeNull()
        ->and($refund->cancellation_reason)->toBe('Agent withdrew the request before settlement.');
});

test('a rejected refund cannot be approved', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'd', totalPaid: 500.0, totalReceivable: 0.0);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);
    app(RefundService::class)->reject($refund, 'Not valid.', $owner->id);

    expect(fn () => app(RefundService::class)->approve($refund->fresh(), [], $owner->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REJECTED);
});

test('a refunded refund cannot be cancelled', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'e', totalPaid: 500.0, totalReceivable: 0.0);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $owner->id);
    app(RefundService::class)->settle($refund->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CASH,
        'account_id' => refundTestBankAccount($company)->id,
    ], $owner->id);

    expect(fn () => app(RefundService::class)->cancel($refund->fresh(), 'Too late.', $owner->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REFUNDED);
});

test('a requested refund cannot be cancelled directly', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'f', totalPaid: 500.0, totalReceivable: 0.0);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);

    expect(fn () => app(RefundService::class)->cancel($refund, 'Changed my mind.', $owner->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REQUESTED);
});

test('a user without refund.approve is forbidden from approving', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'g', totalPaid: 500.0, totalReceivable: 0.0);
    $operations = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $operations, 'operations');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);

    $this->actingAs($operations)
        ->post("/{$company->slug}/umrah/refunds/{$refund->id}/approve", [])
        ->assertForbidden();

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REQUESTED);
});

test('an agent sees only their own refunds', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUserOne, $agentOne] = refundTestAgent($company, 'h1', totalPaid: 500.0, totalReceivable: 0.0);
    [$agentUserTwo, $agentTwo] = refundTestAgent($company, 'h2', totalPaid: 500.0, totalReceivable: 0.0);

    $ownRefund = app(RefundService::class)->request($company->id, requestRefundPayload($agentOne, 100), $agentUserOne->id);
    app(RefundService::class)->request($company->id, requestRefundPayload($agentTwo, 100), $agentUserTwo->id);

    $this->actingAs($agentUserOne)
        ->get("/{$company->slug}/umrah/refunds")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('refunds.data', 1)
            ->where('refunds.data.0.id', $ownRefund->id)
        );
});

test('an agent cannot see another agents refund by id', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUserOne, $agentOne] = refundTestAgent($company, 'i1', totalPaid: 500.0, totalReceivable: 0.0);
    [$agentUserTwo, $agentTwo] = refundTestAgent($company, 'i2', totalPaid: 500.0, totalReceivable: 0.0);

    $otherRefund = app(RefundService::class)->request($company->id, requestRefundPayload($agentTwo, 100), $agentUserTwo->id);

    $this->actingAs($agentUserOne)
        ->get("/{$company->slug}/umrah/refunds/{$otherRefund->id}")
        ->assertNotFound();
});

test('refund amount must be positive', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'j', totalPaid: 500.0, totalReceivable: 0.0);

    $this->actingAs($agentUser)
        ->post("/{$company->slug}/umrah/refunds", requestRefundPayload($agent, 0))
        ->assertSessionHasErrors('amount');

    $this->actingAs($agentUser)
        ->post("/{$company->slug}/umrah/refunds", requestRefundPayload($agent, -50))
        ->assertSessionHasErrors('amount');

    expect(fn () => app(RefundService::class)->request($company->id, requestRefundPayload($agent, 0), $agentUser->id))
        ->toThrow(ValidationException::class);
});

test('an approved refunds amount, party and service are immutable', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'k', totalPaid: 500.0, totalReceivable: 0.0);
    [$otherAgentUser, $otherAgent] = refundTestAgent($company, 'k2', totalPaid: 500.0, totalReceivable: 0.0);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $owner->id);
    $refund = $refund->fresh();

    expect(fn () => $refund->update(['amount' => 999]))->toThrow(RuntimeException::class);
    expect(fn () => $refund->fresh()->update(['party_id' => $otherAgent->id]))->toThrow(RuntimeException::class);
    expect(fn () => $refund->fresh()->update(['service' => Refund::SERVICE_VISA]))->toThrow(RuntimeException::class);

    // Fields outside the invariant remain editable after approval.
    $refund->fresh()->update(['review_remarks' => 'Amended note, unrelated to the guarded fields.']);
    expect($refund->fresh()->review_remarks)->toBe('Amended note, unrelated to the guarded fields.');
});

test('a refund cannot be approved for more than the credit available to the party', function () {
    [$company, $owner] = refundTestCompany();
    // total_paid 100, total_receivable 100 -> zero credit available.
    [$agentUser, $agent] = refundTestAgent($company, 'l', totalPaid: 100.0, totalReceivable: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 50), $agentUser->id);

    expect(fn () => app(RefundService::class)->approve($refund, [], $manager->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REQUESTED);

    // Same refund succeeds once the agent's available credit covers it.
    $agent->update(['total_paid' => 300.0]);
    app(RefundService::class)->approve($refund->fresh(), [], $manager->id);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);
});

test('a vendor refund for the full amount paid can be approved when cost equals paid', function () {
    [$company, $owner] = refundTestCompany();
    // total_paid 100, total_cost 100 -> zero *overpayment*, but the vendor
    // ceiling is what was actually paid, not the excess over cost. This is
    // the canonical case: a visa fee paid but never processed.
    $vendor = refundTestVisaVendor($company, 'm', totalPaid: 100.0, totalCost: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);

    app(RefundService::class)->approve($refund, [], $manager->id);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);
});

test('a vendor refund cannot exceed what was actually paid', function () {
    [$company, $owner] = refundTestCompany();
    $vendor = refundTestVisaVendor($company, 'n', totalPaid: 100.0, totalCost: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 150), $owner->id);

    expect(fn () => app(RefundService::class)->approve($refund, [], $manager->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REQUESTED);
});

test('an already-approved refund consumes the ceiling, blocking a second full-amount refund against the same vendor', function () {
    [$company, $owner] = refundTestCompany();
    $vendor = refundTestVisaVendor($company, 'o', totalPaid: 100.0, totalCost: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $first = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);
    app(RefundService::class)->approve($first, [], $manager->id);
    expect($first->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);

    // The ceiling (total_paid = 100) is unchanged -- Phase 1 does not touch
    // it -- but the first refund already consumed all of it.
    $second = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);

    expect(fn () => app(RefundService::class)->approve($second, [], $manager->id))
        ->toThrow(ValidationException::class);

    expect($second->fresh()->status)->toBe(Refund::STATUS_REQUESTED);
});

test('a rejected or cancelled refund does not consume the ceiling', function () {
    [$company, $owner] = refundTestCompany();
    $vendor = refundTestVisaVendor($company, 'p', totalPaid: 100.0, totalCost: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $rejected = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);
    app(RefundService::class)->reject($rejected, 'Vendor confirmed the visa was processed after all.', $manager->id);
    expect($rejected->fresh()->status)->toBe(Refund::STATUS_REJECTED);

    $cancelled = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);
    app(RefundService::class)->approve($cancelled, [], $manager->id);
    app(RefundService::class)->cancel($cancelled->fresh(), 'Approved in error, withdrawn before settlement.', $manager->id);
    expect($cancelled->fresh()->status)->toBe(Refund::STATUS_CANCELLED);

    // Neither the rejection nor the cancellation left anything consuming the
    // ceiling, so a fresh refund for the full amount still approves.
    $final = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);
    app(RefundService::class)->approve($final, [], $manager->id);

    expect($final->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);
});

test('the credit ceiling is compared in base currency, not the refund transaction currency', function () {
    [$company, $owner] = refundTestCompany('PKR');
    // Vendor::total_paid is a base-currency (PKR) balance -- see
    // UmrahCoreService.php:385, which accumulates it from baseAmount, not
    // amount. Comparing it against the refund's transaction-currency amount
    // would compare PKR to SAR as if they were the same number.
    $vendor = refundTestVisaVendor($company, 'q', totalPaid: 500.0, totalCost: 500.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    // 100 SAR at exchange_rate 6 -> base_amount 600 PKR, which exceeds the
    // 500 PKR ceiling. The transaction-currency figure (100) is smaller
    // than the ceiling number (500), so comparing amount instead of
    // base_amount would wrongly let this through.
    $overCeiling = app(RefundService::class)->request(
        $company->id,
        requestForeignCurrencyVendorRefundPayload($vendor, 100.0, 'SAR', 6.0),
        $owner->id
    );
    expect((float) $overCeiling->base_amount)->toBe(600.0);

    expect(fn () => app(RefundService::class)->approve($overCeiling, [], $manager->id))
        ->toThrow(ValidationException::class);
    expect($overCeiling->fresh()->status)->toBe(Refund::STATUS_REQUESTED);

    // 100 SAR at exchange_rate 4 -> base_amount 400 PKR, inside the 500 PKR
    // ceiling, so the same transaction-currency amount now approves.
    $withinCeiling = app(RefundService::class)->request(
        $company->id,
        requestForeignCurrencyVendorRefundPayload($vendor, 100.0, 'SAR', 4.0),
        $owner->id
    );
    expect((float) $withinCeiling->base_amount)->toBe(400.0);

    app(RefundService::class)->approve($withinCeiling, [], $manager->id);
    expect($withinCeiling->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);
});

test('the create screen prefills the party from a valid query parameter', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'r1', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $this->actingAs($manager)
        ->get("/{$company->slug}/umrah/refunds/create?party_type=agent&party_id={$agent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('initial.party_type', Refund::PARTY_AGENT)
            ->where('initial.party_id', $agent->id)
        );
});

test('a malformed party_id in the link lands on a blank form, not an error', function () {
    [$company, $owner] = refundTestCompany();
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    // These columns are uuid. Postgres raises a cast error on a malformed
    // one rather than simply not matching, so an unguarded query would 500
    // on a stale or hand-edited link instead of ignoring it.
    $this->actingAs($manager)
        ->get("/{$company->slug}/umrah/refunds/create?party_type=agent&party_id=not-a-uuid&visa_group_id=also-not-a-uuid")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('initial', [])
        );
});

test('the create screen prefills the group and its agent from a valid visa_group_id', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'r2', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-RF01',
        'name' => 'Refund Prefill Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-01',
        'transport_required' => false,
        'visa_sale_amount' => 1000,
    ]);

    $this->actingAs($manager)
        ->get("/{$company->slug}/umrah/refunds/create?visa_group_id={$group->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('initial.party_type', Refund::PARTY_AGENT)
            ->where('initial.party_id', $agent->id)
            ->where('initial.visa_group_id', $group->id)
        );
});

test('the create screen ignores a party that does not belong to this company', function () {
    [$company, $owner] = refundTestCompany();
    [$otherCompany, $otherOwner] = refundTestCompany();
    [$agentUser, $foreignAgent] = refundTestAgent($otherCompany, 'r3', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $this->actingAs($manager)
        ->get("/{$company->slug}/umrah/refunds/create?party_type=agent&party_id={$foreignAgent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('initial', [])
        );
});

test('an agent member cannot widen their refund scope through a query parameter', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUserOne, $agentOne] = refundTestAgent($company, 'r4a', totalPaid: 500.0, totalReceivable: 0.0);
    [$agentUserTwo, $agentTwo] = refundTestAgent($company, 'r4b', totalPaid: 500.0, totalReceivable: 0.0);

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agentTwo->id,
        'group_number' => 'UGR-RF02',
        'name' => 'Other Agent Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-01',
        'transport_required' => false,
        'visa_sale_amount' => 1000,
    ]);

    // agentUserOne tries to request a refund as agentTwo, and against
    // agentTwo's group, purely through the query string.
    $this->actingAs($agentUserOne)
        ->get("/{$company->slug}/umrah/refunds/create?party_type=agent&party_id={$agentTwo->id}&visa_group_id={$group->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('initial.party_type', Refund::PARTY_AGENT)
            ->where('initial.party_id', $agentOne->id)
            ->missing('initial.visa_group_id')
        );

    // The same narrowing holds on submission: attempting to actually post a
    // refund for agentTwo's party still lands on agentOne, exactly as
    // "an agent user must not be able to widen their own scope through a
    // query parameter" requires end to end, not just on the create screen.
    $this->actingAs($agentUserOne)
        ->post("/{$company->slug}/umrah/refunds?party_type=agent&party_id={$agentTwo->id}", requestRefundPayload($agentTwo, 100))
        ->assertSessionHasNoErrors();

    $refund = Refund::where('company_id', $company->id)->firstOrFail();
    expect($refund->party_id)->toBe($agentOne->id);
});

/**
 * Phase 2b of docs/contracts/refunds.md -- settlement. These tests cover
 * the postings themselves (accept, settle cash, settle credit, cancel),
 * the group refund de-allocation sequence, and the vendor-side rules
 * (cost account resolution, no credit option). Phase 1's tests above cover
 * the lifecycle and the credit-ceiling approximation; these assume that
 * groundwork and add only what Phase 2b introduces.
 */
function refundTestGroup(Company $company, Agent $agent, string $seed, float $saleAmount): VisaGroup
{
    return VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-'.strtoupper(str()->random(5)),
        'name' => 'Refund Group '.$seed,
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-01',
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => $saleAmount,
        'total_receivable' => $saleAmount,
        'balance' => $saleAmount,
    ]);
}

test('accepting an agent refund posts a balanced Dr 2200 / Cr 2300 entry and stamps transaction_id', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'aa', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    $refund = $refund->fresh();

    expect($refund->status)->toBe(Refund::STATUS_ACCEPTED)
        ->and($refund->transaction_id)->not->toBeNull();

    $agentAdvances = Account::where('company_id', $company->id)->where('code', '2200')->value('id');
    $refundsPayable = Account::where('company_id', $company->id)->where('code', '2300')->value('id');

    $totals = refundJournalTotals($refund->transaction_id);
    expect($totals['debit'])->toBe(200.0)
        ->and($totals['credit'])->toBe(200.0)
        ->and($totals['debit'])->toBe($totals['credit']);

    $debitAccount = $totals['rows']->firstWhere('debit_amount', '>', 0);
    $creditAccount = $totals['rows']->firstWhere('credit_amount', '>', 0);
    expect($debitAccount->account_id)->toBe($agentAdvances)
        ->and($creditAccount->account_id)->toBe($refundsPayable);
});

test('settling an accepted refund as cash posts Dr 2300 / Cr bank and stamps the settlement', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ab', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');
    $bank = refundTestBankAccount($company);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);

    $settled = app(RefundService::class)->settle($refund->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CASH,
        'account_id' => $bank->id,
        'date' => '2026-08-21',
    ], $manager->id);

    expect($settled->status)->toBe(Refund::STATUS_REFUNDED)
        ->and($settled->settlement_method)->toBe(Refund::SETTLEMENT_CASH)
        ->and($settled->settled_at)->not->toBeNull()
        ->and($settled->settled_by_user_id)->toBe($manager->id);

    $refundsPayable = Account::where('company_id', $company->id)->where('code', '2300')->value('id');

    $settlementTransactionId = DB::table('acct.transactions')
        ->where('reference_type', 'umrah.refunds')
        ->where('reference_id', $settled->id)
        ->where('transaction_type', 'umrah_refund_settle_cash')
        ->value('id');

    $totals = refundJournalTotals($settlementTransactionId);
    expect($totals['debit'])->toBe(200.0)
        ->and($totals['credit'])->toBe(200.0);

    $debitAccount = $totals['rows']->firstWhere('debit_amount', '>', 0);
    $creditAccount = $totals['rows']->firstWhere('credit_amount', '>', 0);
    expect($debitAccount->account_id)->toBe($refundsPayable)
        ->and($creditAccount->account_id)->toBe($bank->id);
});

test('settling an accepted refund as credit posts Dr 2300 / Cr 2200', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ac', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);

    $settled = app(RefundService::class)->settle($refund->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CREDIT,
    ], $manager->id);

    expect($settled->status)->toBe(Refund::STATUS_CREDITED)
        ->and($settled->settlement_method)->toBe(Refund::SETTLEMENT_CREDIT);

    $agentAdvances = Account::where('company_id', $company->id)->where('code', '2200')->value('id');
    $refundsPayable = Account::where('company_id', $company->id)->where('code', '2300')->value('id');

    $settlementTransactionId = DB::table('acct.transactions')
        ->where('reference_type', 'umrah.refunds')
        ->where('reference_id', $settled->id)
        ->where('transaction_type', 'umrah_refund_settle_credit')
        ->value('id');

    $totals = refundJournalTotals($settlementTransactionId);
    expect($totals['debit'])->toBe(200.0)
        ->and($totals['credit'])->toBe(200.0);

    $debitAccount = $totals['rows']->firstWhere('debit_amount', '>', 0);
    $creditAccount = $totals['rows']->firstWhere('credit_amount', '>', 0);
    expect($debitAccount->account_id)->toBe($refundsPayable)
        ->and($creditAccount->account_id)->toBe($agentAdvances);
});

test('after a credit settlement the credit is genuinely available, and only the amount actually still unspent can be reached again', function () {
    // Originally this test asserted the opposite -- that a second refund
    // against just-credited money was refused. That encoded the very bug
    // fixed by postRefundSettleCredit() creating a GroupPayment: before the
    // fix, settling as credit was invisible to availableCredit(), so a
    // second refund against real, unspent money looked like a double-spend
    // when it was not one. Now that the credit is a real, unallocated
    // GroupPayment, a second refund for money still sitting there must
    // succeed -- and a further refund for more than what remains must not.
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ad', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $first = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 500), $agentUser->id);
    app(RefundService::class)->approve($first, [], $manager->id);
    app(RefundService::class)->settle($first->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);
    expect($first->fresh()->status)->toBe(Refund::STATUS_CREDITED);

    $second = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 50), $agentUser->id);
    app(RefundService::class)->approve($second, [], $manager->id);
    expect($second->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);

    // Worth spelling out, because two 500s appear and only one of them is
    // money. The ceiling reads 500 from the fixture's denormalised
    // total_paid plus 450 still unspent on the credit GroupPayment (500
    // less the 50 the second approval just drew off it) = 950.
    // consumedCredit() subtracts the first refund's 500 in full, since it
    // drew nothing off any payment row -- there was none to draw from --
    // and subtracts nothing more for the second refund, whose 50 is already
    // missing from the advance. 950 - 500 = 450, which is exactly the real,
    // unspent money left. Asking for one more than that must fail.
    $third = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 451), $agentUser->id);

    expect(fn () => app(RefundService::class)->approve($third, [], $manager->id))
        ->toThrow(ValidationException::class);

    expect($third->fresh()->status)->toBe(Refund::STATUS_REQUESTED);
});

test('cancelling an accepted refund posts the reversing entry and leaves the books balanced', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ae', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);

    $cancelled = app(RefundService::class)->cancel($refund->fresh(), 'Approved in error.', $manager->id);

    expect($cancelled->status)->toBe(Refund::STATUS_CANCELLED)
        ->and($cancelled->cancellation_transaction_id)->not->toBeNull();

    $agentAdvances = Account::where('company_id', $company->id)->where('code', '2200')->value('id');
    $refundsPayable = Account::where('company_id', $company->id)->where('code', '2300')->value('id');

    $totals = refundJournalTotals($cancelled->cancellation_transaction_id);
    expect($totals['debit'])->toBe(200.0)
        ->and($totals['credit'])->toBe(200.0);

    $debitAccount = $totals['rows']->firstWhere('debit_amount', '>', 0);
    $creditAccount = $totals['rows']->firstWhere('credit_amount', '>', 0);
    expect($debitAccount->account_id)->toBe($refundsPayable)
        ->and($creditAccount->account_id)->toBe($agentAdvances);
});

test('a group refund reverses enough allocation to cover itself and re-allocates the remainder', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'af', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');
    $group = refundTestGroup($company, $agent, 'af', 1000);

    $core = app(UmrahCoreService::class);
    $payment = $core->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1000,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);
    $core->allocatePayment($payment, ['visa_group_id' => $group->id, 'base_amount' => 1000]);

    // The credit ceiling is a separate concern from the de-allocation
    // sequence this test targets -- pin it high so the ceiling check
    // cannot be the reason approval succeeds or fails here.
    $agent->fresh()->forceFill(['total_paid' => 100000.0, 'total_receivable' => 0.0])->saveQuietly();

    $refund = app(RefundService::class)->request($company->id, array_merge(
        requestRefundPayload($agent, 400),
        ['visa_group_id' => $group->id]
    ), $agentUser->id);

    app(RefundService::class)->approve($refund, [], $manager->id);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);

    $group = $group->fresh();
    expect((float) $group->total_paid)->toBe(600.0);

    $liveAllocation = PaymentAllocation::where('group_payment_id', $payment->id)
        ->whereNull('reversed_at')->whereNotNull('visa_group_id')->sole();
    expect((float) $liveAllocation->base_amount)->toBe(600.0);

    $originalAllocation = PaymentAllocation::where('group_payment_id', $payment->id)->whereNotNull('reversed_at')->sole();
    expect((float) $originalAllocation->base_amount)->toBe(1000.0);

    // De-allocating freed 400 off this payment, and the refund immediately
    // claimed it -- otherwise that 400 would sit there looking spendable
    // while the ledger had already moved it into refunds_payable. The draw
    // is an ordinary allocation row naming the refund instead of a group,
    // so the payment now reads as fully consumed: 600 to the group, 400 to
    // the refund, nothing available.
    $draw = PaymentAllocation::where('group_payment_id', $payment->id)
        ->whereNull('reversed_at')->whereNotNull('refund_id')->sole();
    expect((float) $draw->base_amount)->toBe(400.0)
        ->and($draw->refund_id)->toBe($refund->id)
        ->and($draw->visa_group_id)->toBeNull();
});

test('a group refund larger than the credit allocated to that group is refused', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ag', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');
    $group = refundTestGroup($company, $agent, 'ag', 1000);

    $core = app(UmrahCoreService::class);
    $payment = $core->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);
    $core->allocatePayment($payment, ['visa_group_id' => $group->id, 'base_amount' => 500]);

    $agent->fresh()->forceFill(['total_paid' => 100000.0, 'total_receivable' => 0.0])->saveQuietly();

    $refund = app(RefundService::class)->request($company->id, array_merge(
        requestRefundPayload($agent, 700),
        ['visa_group_id' => $group->id]
    ), $agentUser->id);

    expect(fn () => app(RefundService::class)->approve($refund, [], $manager->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_REQUESTED);

    $liveAllocation = PaymentAllocation::where('group_payment_id', $payment->id)->whereNull('reversed_at')->sole();
    expect((float) $liveAllocation->base_amount)->toBe(500.0);
});

test('a vendor refund accepts against 1170 and the correct cost account for its service', function () {
    [$company, $owner] = refundTestCompany();
    $vendor = refundTestVisaVendor($company, 'ah', totalPaid: 100.0, totalCost: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    $refund = $refund->fresh();

    expect($refund->status)->toBe(Refund::STATUS_ACCEPTED);

    $refundsReceivable = Account::where('company_id', $company->id)->where('code', '1170')->value('id');
    $visaCost = Account::where('company_id', $company->id)->where('code', '5100')->value('id');

    $totals = refundJournalTotals($refund->transaction_id);
    expect($totals['debit'])->toBe(100.0)
        ->and($totals['credit'])->toBe(100.0);

    $debitAccount = $totals['rows']->firstWhere('debit_amount', '>', 0);
    $creditAccount = $totals['rows']->firstWhere('credit_amount', '>', 0);
    expect($debitAccount->account_id)->toBe($refundsReceivable)
        ->and($creditAccount->account_id)->toBe($visaCost);
});

test('no route offers keep-as-credit settlement for a vendor refund', function () {
    [$company, $owner] = refundTestCompany();
    $vendor = refundTestVisaVendor($company, 'ai', totalPaid: 100.0, totalCost: 100.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestVendorRefundPayload($vendor, 100), $owner->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    $refund = $refund->fresh();

    // The service layer refuses it directly...
    expect(fn () => app(RefundService::class)->settle($refund, ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id))
        ->toThrow(ValidationException::class);
    expect($refund->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);

    // ...and the route rejects it too, so a forged request cannot reach it
    // even if the frontend never renders the option.
    $this->actingAs($manager)
        ->post("/{$company->slug}/umrah/refunds/{$refund->id}/settle", [
            'settlement_method' => Refund::SETTLEMENT_CREDIT,
        ])
        ->assertSessionHasErrors('settlement_method');

    expect($refund->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);
});
