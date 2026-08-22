<?php

use App\Models\User;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Services\RefundService;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Validation\ValidationException;

test('credit settlement does not conjure advances the agent never paid', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'dc1', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    // The agent pays in 500. That is ALL the money that ever exists here.
    app(UmrahCoreService::class)->addPayment($company->id, [
        'agent_id' => $agent->id,
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'amount' => 500,
        'currency' => $company->base_currency,
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    // They ask for it back, then agree to keep it as credit instead.
    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 500), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    app(RefundService::class)->settle($refund->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);

    // Nothing moved. The agent should still have exactly 500 of spendable credit.
    $unallocated = GroupPayment::where('company_id', $company->id)
        ->where('agent_id', $agent->id)
        ->where('status', GroupPayment::STATUS_POSTED)
        ->get()
        ->sum(fn ($p) => (float) $p->base_amount - (float) $p->allocations->sum('base_amount'));

    expect($unallocated)->toBe(500.0);
});

test('the agent cannot allocate more than they paid', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'dc2', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    app(UmrahCoreService::class)->addPayment($company->id, [
        'agent_id' => $agent->id,
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'amount' => 500,
        'currency' => $company->base_currency,
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 500), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    app(RefundService::class)->settle($refund->fresh(), ['settlement_method' => Refund::SETTLEMENT_CREDIT], $manager->id);

    // Two groups, 500 of real sales each.
    $g1 = creditAdvanceGroup($company, $agent, 'dc2a', 500);
    $g2 = creditAdvanceGroup($company, $agent, 'dc2b', 500);

    $payments = GroupPayment::where('company_id', $company->id)->where('agent_id', $agent->id)->get();

    // Two advance rows exist by now -- the agent's original receipt, which
    // the refund's approval drew empty, and the credit that settlement
    // created. Every pairing of the two is attempted against both groups;
    // the ones the service refuses are the point of the test, so a refusal
    // is counted as zero rather than failing the run. What is being
    // measured is the total that can be made to stick.
    $allocatedTotal = 0.0;
    foreach ([$g1, $g2] as $group) {
        foreach ($payments as $payment) {
            try {
                $allocation = app(UmrahCoreService::class)->allocatePayment($payment, [
                    'visa_group_id' => $group->id,
                    'base_amount' => 500,
                ]);
                $allocatedTotal += (float) $allocation->base_amount;
            } catch (ValidationException) {
                // Refused: no credit left on this payment, or already
                // allocated to this group. Both are the invariant holding.
            }
        }
    }

    // 500 came in. If 1000 can be spent, the credit was counted twice.
    expect($allocatedTotal)->toBe(500.0);

    // And the groups agree: exactly one of them got paid.
    expect((float) $g1->fresh()->total_paid + (float) $g2->fresh()->total_paid)->toBe(500.0);
});

test('cash settlement removes the credit that was paid out', function () {
    [$company, $owner] = refundTestCompany();
    [$agentUser, $agent] = refundTestAgent($company, 'dc3', totalPaid: 0.0, totalReceivable: 0.0);
    $manager = User::factory()->withoutTwoFactor()->create();
    refundTestMember($company, $manager, 'manager');

    app(UmrahCoreService::class)->addPayment($company->id, [
        'agent_id' => $agent->id,
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'amount' => 500,
        'currency' => $company->base_currency,
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $refund = app(RefundService::class)->request($company->id, requestRefundPayload($agent, 500), $agentUser->id);
    app(RefundService::class)->approve($refund, [], $manager->id);
    app(RefundService::class)->settle($refund->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CASH,
        'account_id' => refundTestBankAccount($company)->id,
        'date' => '2026-08-21',
    ], $manager->id);

    // The 500 left the bank. Nothing spendable should remain.
    $unallocated = GroupPayment::where('company_id', $company->id)
        ->where('agent_id', $agent->id)
        ->where('status', GroupPayment::STATUS_POSTED)
        ->get()
        ->sum(fn ($p) => (float) $p->base_amount - (float) $p->allocations->sum('base_amount'));

    expect($unallocated)->toBe(0.0);
});
