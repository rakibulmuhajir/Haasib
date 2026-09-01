<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 2a of docs/contracts/refunds.md -- "The allocation problem": reversing
 * one allocation of a payment without touching the rest of it. This is the
 * primitive Phase 2b's refund settlement calls; nothing here posts a refund.
 */
function allocationReversalCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Allocation Reversal '.str()->random(8),
        'slug' => 'allocation-reversal-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

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

    $company->forceFill([
        'bank_account_id' => Account::where('company_id', $company->id)->where('code', '1000')->value('id'),
        'ar_account_id' => Account::where('company_id', $company->id)->where('code', '1100')->value('id'),
    ])->save();

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Field Agent',
        'is_active' => true,
    ]);

    $groupOne = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-'.strtoupper(str()->random(5)),
        'name' => 'Group One',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-01',
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => 900,
        'total_receivable' => 900,
        'balance' => 900,
    ]);

    $groupTwo = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-'.strtoupper(str()->random(5)),
        'name' => 'Group Two',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-09-15',
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => 600,
        'total_receivable' => 600,
        'balance' => 600,
    ]);

    return [$company, $owner, $agent, $groupOne, $groupTwo];
}

function journalTotals(?string $transactionId): array
{
    $rows = DB::table('acct.journal_entries')->where('transaction_id', $transactionId)->get(['debit_amount', 'credit_amount']);

    return [
        'debit' => (float) $rows->sum('debit_amount'),
        'credit' => (float) $rows->sum('credit_amount'),
    ];
}

test('an agent credit can be put against a group whose charge later grew', function () {
    // The group had been allocated in full, so it was struck off this
    // payment's list -- and then an adjustment raised what the agent owed
    // on it. The one group their credit was wanted for was the one group
    // it was barred from.
    [$company, , $agent, $groupOne] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_CASH,
    ]);

    $service->allocatePayment($payment, [
        'visa_group_id' => $groupOne->id,
        'base_amount' => (float) $groupOne->fresh()->balance,
    ]);

    expect((float) $groupOne->fresh()->balance)->toBe(0.0);

    $groupOne->fresh()->update([
        'visa_sale_amount' => (float) $groupOne->visa_sale_amount + 280,
    ]);
    $service->recalculateGroup($groupOne->fresh());

    expect((float) $groupOne->fresh()->balance)->toBe(280.0);

    $service->allocatePayment($payment->fresh(), [
        'visa_group_id' => $groupOne->id,
        'base_amount' => 280,
    ]);

    expect((float) $groupOne->fresh()->balance)->toBe(0.0)
        ->and($payment->fresh()->allocations()->where('visa_group_id', $groupOne->id)->count())->toBe(2);
});

test('a second allocation still cannot exceed what the group owes', function () {
    [$company, , $agent, $groupOne] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_CASH,
    ]);

    $service->allocatePayment($payment, [
        'visa_group_id' => $groupOne->id,
        'base_amount' => (float) $groupOne->fresh()->balance,
    ]);

    $service->allocatePayment($payment->fresh(), ['visa_group_id' => $groupOne->id, 'base_amount' => 50]);
})->throws(Illuminate\Validation\ValidationException::class);

test('a payment records the account its money moved through', function () {
    // Choosing an account is optional and almost nobody does, so a
    // fallback picks one at posting time. Until that choice was written
    // back, the group screen could only say no account was selected about
    // money that had plainly moved.
    [$company, , $agent] = allocationReversalCompany();

    $payment = app(UmrahCoreService::class)->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_CASH,
    ]);

    $account = App\Modules\Accounting\Models\Account::find($payment->fresh()->account_id);

    expect($account)->not->toBeNull()
        ->and($account->subtype)->toBe('cash');
});

test('reversing one allocation of a two-allocation payment leaves the payment posted and the other allocation untouched', function () {
    [$company, $owner, $agent, $groupOne, $groupTwo] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $allocationOne = $service->allocatePayment($payment, ['visa_group_id' => $groupOne->id, 'base_amount' => 900]);
    $allocationTwo = $service->allocatePayment($payment->fresh(), ['visa_group_id' => $groupTwo->id, 'base_amount' => 600]);

    $service->reverseAllocation($allocationOne, 'Refund settlement de-allocation', $owner->id);

    $payment = $payment->fresh();
    $allocationTwo = $allocationTwo->fresh();

    expect($payment->status)->toBe(GroupPayment::STATUS_POSTED)
        ->and($allocationTwo->reversed_at)->toBeNull();
});

test('reversal drops only the affected group\'s total_paid, and returns the credit to the unallocated pool', function () {
    [$company, $owner, $agent, $groupOne, $groupTwo] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $allocationOne = $service->allocatePayment($payment, ['visa_group_id' => $groupOne->id, 'base_amount' => 900]);
    $service->allocatePayment($payment->fresh(), ['visa_group_id' => $groupTwo->id, 'base_amount' => 600]);

    $service->reverseAllocation($allocationOne, 'Refund settlement de-allocation', $owner->id);

    $groupOne = $groupOne->fresh();
    $groupTwo = $groupTwo->fresh();
    $payment = $payment->fresh();

    $livePool = (float) $payment->allocations()->whereNull('reversed_at')->sum('base_amount');

    expect((float) $groupOne->total_paid)->toBe(0.0)
        ->and((float) $groupTwo->total_paid)->toBe(600.0)
        ->and(round((float) $payment->base_amount - $livePool, 2))->toBe(900.0);
});

test('the reversal posts a balanced transaction and does not mutate the original', function () {
    [$company, $owner, $agent, $groupOne, $groupTwo] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 900,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $allocation = $service->allocatePayment($payment, ['visa_group_id' => $groupOne->id, 'base_amount' => 900]);
    $originalTransactionId = $allocation->transaction_id;
    $originalTotalsBefore = journalTotals($originalTransactionId);

    $reversed = $service->reverseAllocation($allocation, 'Refund settlement de-allocation', $owner->id);

    $originalTotalsAfter = journalTotals($originalTransactionId);
    $reversalTotals = journalTotals($reversed->reversal_transaction_id);

    expect($reversed->reversal_transaction_id)->not->toBeNull()
        ->and($reversalTotals['debit'])->toBe($reversalTotals['credit'])
        ->and($reversalTotals['debit'])->toBe(900.0)
        ->and($originalTotalsAfter)->toBe($originalTotalsBefore);
});

test('reversing an already-reversed allocation throws rather than posting twice', function () {
    [$company, $owner, $agent, $groupOne, $groupTwo] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 900,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $allocation = $service->allocatePayment($payment, ['visa_group_id' => $groupOne->id, 'base_amount' => 900]);
    $service->reverseAllocation($allocation, 'first reversal', $owner->id);

    expect(fn () => $service->reverseAllocation($allocation->fresh(), 'second reversal', $owner->id))
        ->toThrow(ValidationException::class);
});

test('reversePayment() still stamps every allocation and recalculates every affected group', function () {
    [$company, $owner, $agent, $groupOne, $groupTwo] = allocationReversalCompany();
    $service = app(UmrahCoreService::class);

    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'agent_id' => $agent->id,
        'amount' => 1500,
        'currency' => 'SAR',
        'payment_date' => '2026-08-20',
        'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);

    $service->allocatePayment($payment, ['visa_group_id' => $groupOne->id, 'base_amount' => 900]);
    $service->allocatePayment($payment->fresh(), ['visa_group_id' => $groupTwo->id, 'base_amount' => 600]);

    $reversedPayment = $service->reversePayment($payment->fresh(), 'full payment reversal', $owner->id);

    expect($reversedPayment->status)->toBe(GroupPayment::STATUS_REVERSED);

    $allAllocations = PaymentAllocation::where('group_payment_id', $payment->id)->get();
    expect($allAllocations)->toHaveCount(2);
    foreach ($allAllocations as $allocation) {
        expect($allocation->reversed_at)->not->toBeNull()
            ->and($allocation->reversed_by_user_id)->toBe($owner->id)
            ->and($allocation->reversal_transaction_id)->not->toBeNull();
    }

    expect((float) $groupOne->fresh()->total_paid)->toBe(0.0)
        ->and((float) $groupTwo->fresh()->total_paid)->toBe(0.0);
});
