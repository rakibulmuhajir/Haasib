<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\RefundService;
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

    return [$company, $owner];
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
        ->and($refund->transaction_id)->toBeNull();
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

test('a paid refund cannot be cancelled', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'e', totalPaid: 500.0, totalReceivable: 0.0);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 150), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $owner->id);

    // Phase 2 owns settlement, so "paid" is reached only by direct
    // manipulation here -- there is no route to it in this phase.
    $refund->fresh()->forceFill(['status' => Refund::STATUS_PAID])->saveQuietly();

    expect(fn () => app(RefundService::class)->cancel($refund->fresh(), 'Too late.', $owner->id))
        ->toThrow(ValidationException::class);

    expect($refund->fresh()->status)->toBe(Refund::STATUS_PAID);
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
