<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\RefundService;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/contracts/refunds.md's settlement section: "Kept as credit ... the
 * money stays with the party as an ordinary advance". Phase 2b's ledger
 * posting (postRefundSettleCredit) landed without the other half -- no
 * `GroupPayment` was ever created, so the credit was real in 2200 and
 * invisible everywhere `allocatePayment()`, `agentStatement()` and
 * `RefundService::availableCredit()` look for it. These tests cover the
 * fix: the GroupPayment that makes the credit spendable, that it can be
 * allocated to a new group, that it can be asked back as a group-less
 * refund, and that credit already spent cannot be asked back twice.
 *
 * Reuses refundTestCompany()/refundTestAgent()/refundTestMember()/etc from
 * RefundLifecycleTest.php -- Pest loads every test file's top-level
 * functions into one global namespace, so redefining them here would
 * collide rather than shadow.
 */
function creditAdvanceGroup(Company $company, Agent $agent, string $seed, float $saleAmount): VisaGroup
{
    return VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-'.strtoupper(str()->random(5)),
        'name' => 'Credit Advance Group '.$seed,
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-01',
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => $saleAmount,
        'total_receivable' => $saleAmount,
        'balance' => $saleAmount,
    ]);
}

test('settling as credit creates exactly one posted, unallocated GroupPayment for the agent, linked back to the refund, and the GL stays balanced', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ca1', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);

    expect(GroupPayment::where('company_id', $company->id)->where('agent_id', $agent->id)->count())->toBe(0);

    $settled = app(RefundService::class)->settle($refund->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CREDIT,
    ], $manager->id);

    $payments = GroupPayment::where('company_id', $company->id)->where('agent_id', $agent->id)->get();
    expect($payments)->toHaveCount(1);

    $payment = $payments->first();
    expect($payment->direction)->toBe(GroupPayment::DIRECTION_RECEIVED)
        ->and($payment->status)->toBe(GroupPayment::STATUS_POSTED)
        ->and($payment->visa_group_id)->toBeNull()
        ->and((float) $payment->base_amount)->toBe(200.0)
        ->and($payment->allocations)->toHaveCount(0);

    // The link is bidirectional in effect: the refund points at the
    // payment via settled_payment_id, and the payment carries the refund
    // number in its own reference -- either one lets you find the other.
    expect($settled->settled_payment_id)->toBe($payment->id)
        ->and($payment->reference)->toBe($settled->refund_number);

    // The credit-settle transaction is the only GL entry this payment is
    // tied to -- proof it was not posted a second time through the normal
    // payment machinery (postAgentPayment/postPaymentAndAllocate).
    expect($payment->transaction_id)->not->toBeNull();
    $creditTransactionId = DB::table('acct.transactions')
        ->where('reference_type', 'umrah.refunds')
        ->where('reference_id', $settled->id)
        ->where('transaction_type', 'umrah_refund_settle_credit')
        ->value('id');
    expect($payment->transaction_id)->toBe($creditTransactionId);

    $rows = DB::table('acct.journal_entries')->where('reference_type', 'umrah.group_payments')->where('reference_id', $payment->id)->get();
    expect($rows)->toHaveCount(0);

    $totals = refundJournalTotals($creditTransactionId);
    expect($totals['debit'])->toBe(200.0)->and($totals['credit'])->toBe(200.0);
});

test('credit from a settled refund can be allocated to a different group', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ca2', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');
    $group = creditAdvanceGroup($company, $agent, 'ca2', 150);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    app(RefundService::class)->settle($refund->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);

    $payment = GroupPayment::where('company_id', $company->id)->where('agent_id', $agent->id)->sole();

    $core = app(UmrahCoreService::class);
    $allocation = $core->allocatePayment($payment, ['visa_group_id' => $group->id, 'base_amount' => 150]);

    expect((float) $allocation->base_amount)->toBe(150.0);
    expect((float) $group->fresh()->total_paid)->toBe(150.0);
    expect((float) $payment->fresh()->allocations->sum('base_amount'))->toBe(150.0);
});

test('an agent can request and be approved for the return of unspent credit', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ca3', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    // The fixture leaves total_paid at 0 -- the very thing this test is
    // proving is that a real, group-less advance (not the denormalised
    // Agent columns) can back a refund's ceiling on its own. addPayment()
    // with no visa_group_id posts an ordinary unallocated advance, exactly
    // like an agent overpaying with no group to apply it to yet.
    app(UmrahCoreService::class)->addPayment($company->id, [
        'agent_id' => $agent->id,
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'amount' => 300,
        'currency' => $company->base_currency,
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $first = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 300), $agentUser->id);
    app(RefundService::class)->approve($first, [], $manager->id);
    app(RefundService::class)->settle($first->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);

    // Nothing was ever manually poked into Agent::total_paid here -- the
    // ceiling for this second, group-less refund must come entirely from
    // the unallocated GroupPayment the first refund's settlement created.
    $second = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 300), $agentUser->id);
    $approved = app(RefundService::class)->approve($second, [], $manager->id);

    expect($approved->status)->toBe(Refund::STATUS_ACCEPTED);

    $settled = app(RefundService::class)->settle($approved->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CASH,
        'account_id' => refundTestBankAccount($company)->id,
        'date' => '2026-08-21',
    ], $manager->id);

    expect($settled->status)->toBe(Refund::STATUS_REFUNDED);
});

test('an agent cannot get back credit already allocated to a group', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ca4', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');
    $group = creditAdvanceGroup($company, $agent, 'ca4', 300);

    // Same reason as the previous test: the fixture leaves total_paid at 0,
    // so the first refund's own ceiling needs a real unallocated advance to
    // approve against.
    app(UmrahCoreService::class)->addPayment($company->id, [
        'agent_id' => $agent->id,
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'amount' => 500,
        'currency' => $company->base_currency,
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $first = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 500), $agentUser->id);
    app(RefundService::class)->approve($first, [], $manager->id);
    $settledFirst = app(RefundService::class)->settle($first->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);

    // Two unallocated advances now exist for this agent -- the seed advance
    // above and the credit this refund's settlement just created. Only the
    // latter is what we're spending here; settled_payment_id names it
    // exactly, so there is no ambiguity about which row is "the credit".
    $payment = GroupPayment::findOrFail($settledFirst->fresh()->settled_payment_id);
    app(UmrahCoreService::class)->allocatePayment($payment, ['visa_group_id' => $group->id, 'base_amount' => 300]);

    // 500 credited, 300 now spent on the group -> only 200 left to ask back.
    $withinRemaining = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($withinRemaining, [], $manager->id);
    expect($withinRemaining->fresh()->status)->toBe(Refund::STATUS_ACCEPTED);

    $overRemaining = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 1), $agentUser->id);
    expect(fn () => app(RefundService::class)->approve($overRemaining, [], $manager->id))
        ->toThrow(ValidationException::class);
    expect($overRemaining->fresh()->status)->toBe(Refund::STATUS_REQUESTED);
});

test('a credited refund cannot be cancelled -- settlement is terminal, matching the contract lifecycle', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'ca5', totalPaid: 500.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 200), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    $settled = app(RefundService::class)->settle($refund->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);

    expect(fn () => app(RefundService::class)->cancel($settled->fresh(), 'Trying to undo a settled credit.', $manager->id))
        ->toThrow(ValidationException::class);

    expect($settled->fresh()->status)->toBe(Refund::STATUS_CREDITED);
});
