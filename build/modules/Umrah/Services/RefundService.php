<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 1 of docs/contracts/refunds.md -- "The obligation" -- moves a
 * refund through request -> approve/reject, and approved -> cancel/settle.
 *
 * Phase 2b (this file, as it stands) fills in the ledger: approve() posts
 * the accept entry (and, for a group refund, de-allocates the credit it is
 * about to return before posting), cancel() reverses it, and the new
 * settle() posts the accountant's choice of paying it back or keeping it
 * as credit. Every posting itself is delegated to UmrahCoreService -- this
 * class only decides which posting applies and moves the refund's own
 * columns.
 */
class RefundService
{
    public function __construct(private UmrahCoreService $coreService) {}

    public function request(string $companyId, array $data, ?string $userId): Refund
    {
        return DB::transaction(function () use ($companyId, $data, $userId) {
            $company = $this->company($companyId);

            $group = ! empty($data['visa_group_id'])
                ? VisaGroup::where('company_id', $companyId)->lockForUpdate()->findOrFail($data['visa_group_id'])
                : null;

            if ($group && $data['party_type'] === Refund::PARTY_AGENT && $group->agent_id !== $data['party_id']) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group does not belong to this agent.']);
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
            }

            $currency = strtoupper($data['currency']);
            $exchangeRate = $currency === $company->base_currency ? null : (float) $data['exchange_rate'];
            $baseAmount = round($amount * ($exchangeRate ?? 1), 2);

            $refund = Refund::create([
                'company_id' => $companyId,
                'party_type' => $data['party_type'],
                'party_id' => $data['party_id'],
                'visa_group_id' => $group?->id,
                'service' => $data['service'],
                'refund_number' => ($data['refund_number'] ?? null) ?: $this->nextRefundNumber($companyId),
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $company->base_currency,
                'base_amount' => $baseAmount,
                'reason' => $data['reason'],
                'reason_category' => $data['reason_category'] ?? null,
                'status' => Refund::STATUS_REQUESTED,
                'requested_by_user_id' => $userId,
                'requested_at' => now(),
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Moves a requested refund to accepted. This is the control point that
     * stops an agent refunding themselves -- only someone with
     * `refund.approve` reaches this method (enforced by the FormRequest).
     *
     * Order of operations, deliberately:
     *
     *  1. assertWithinAvailableCredit() runs first, against the state
     *     before this refund does anything -- exactly where Phase 1 already
     *     ran it. The credit ceiling is built from Agent/Vendor::total_paid,
     *     which recalculateGroup() derives from *live* allocations. If the
     *     de-allocation step below ran first, reversing an allocation would
     *     shrink that ceiling before the check that is supposed to test
     *     against it -- even though the money has not left the company, it
     *     is just momentarily unallocated. Checking first avoids a
     *     spurious rejection of a legitimate group refund.
     *  2. For a group refund (visa_group_id set, agent only), enough of the
     *     group's live allocations are reversed to cover the refund, with
     *     any excess re-allocated back onto the same group -- the group's
     *     total_paid must drop by exactly the refund amount, not by
     *     whichever whole allocation happened to cover it.
     *  3. The accept entry posts (Dr 2200/Cr 2300 agent, Dr 1170/Cr cost
     *     vendor).
     *  4. One update() moves the status, stamps the reviewer, and stamps
     *     transaction_id -- the accept transaction's id, which is never
     *     overwritten by anything settle() or cancel() do later.
     */
    public function approve(Refund $refund, array $data, string $userId): Refund
    {
        return DB::transaction(function () use ($refund, $data, $userId) {
            $refund = Refund::where('company_id', $refund->company_id)->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== Refund::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['refund' => 'Only a requested refund can be approved.']);
            }

            $this->assertWithinAvailableCredit($refund);

            // Agent side only: a refund to an agent comes out of what they
            // have already paid against that group. A supplier credit has
            // no agent allocations to unwind, and running this for one
            // found none and refused the whole refund.
            if ($refund->visa_group_id && $refund->party_type === Refund::PARTY_AGENT) {
                $this->deallocateForGroupRefund($refund, $userId);
            }

            $transaction = $this->coreService->postRefundAccept($refund);

            // The supplier charged this trip less than first billed, so the
            // trip cost less. The ledger's cost account was just credited;
            // this moves the group's own figure with it.
            $this->coreService->applyVendorCreditToGroup($refund, -1);

            // Mirrors the Dr agent_advances entry just posted, on the
            // GroupPayment rows themselves -- see
            // UmrahCoreService::debitAgentAdvances() for why this cannot
            // be skipped: without it, allocatePayment() and the reporting
            // views go on treating money the ledger already moved into
            // refunds_payable as if it were still an unspent advance.
            if ($refund->party_type === Refund::PARTY_AGENT) {
                $this->coreService->debitAgentAdvances($refund);
            }

            $refund->update([
                'status' => Refund::STATUS_ACCEPTED,
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'review_remarks' => $data['review_remarks'] ?? null,
                'transaction_id' => $transaction->id,
            ]);

            return $refund->fresh();
        });
    }

    public function reject(Refund $refund, string $remarks, string $userId): Refund
    {
        return DB::transaction(function () use ($refund, $remarks, $userId) {
            $refund = Refund::where('company_id', $refund->company_id)->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== Refund::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['refund' => 'Only a requested refund can be rejected.']);
            }

            $refund->update([
                'status' => Refund::STATUS_REJECTED,
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Reverses whatever approve() posted (Dr 2300/Cr 2200 for an agent,
     * Dr cost/Cr 1170 for a vendor) and stamps cancellation_transaction_id.
     * The de-allocation approve() ran for a group refund is not undone here
     * -- that credit was already returned to the group at accept time, and
     * cancelling the refund does not owe it back a second time.
     */
    public function cancel(Refund $refund, string $reason, string $userId): Refund
    {
        return DB::transaction(function () use ($refund, $reason, $userId) {
            $refund = Refund::where('company_id', $refund->company_id)->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== Refund::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['refund' => 'Only an approved refund can be cancelled.']);
            }

            $transaction = $this->coreService->postRefundCancel($refund);

            // Mirrors the Cr agent_advances entry postRefundCancel() just
            // posted -- restores availability on the exact rows
            // debitAgentAdvances() took it from at approve() time.
            if ($refund->party_type === Refund::PARTY_AGENT) {
                $this->coreService->reverseAgentAdvanceDebits($refund, $reason, $userId);
            }

            // Cancelling the credit puts the trip's cost back where it was.
            $this->coreService->applyVendorCreditToGroup($refund, 1);

            $refund->update([
                'status' => Refund::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
                'cancellation_reason' => $reason,
                'cancellation_transaction_id' => $transaction->id,
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Settles an accepted refund: pay it back in cash/bank (status becomes
     * `refunded`) or keep it as credit (status becomes `credited`, agent
     * only -- refunds.md is explicit that a vendor refund settles by
     * receiving cash only, so `credit` for a vendor is refused here even
     * though the frontend never offers it).
     *
     * `settled_at` on the refund row is the audit-action timestamp (now()),
     * consistent with requested_at/reviewed_at/cancelled_at, which are all
     * "when this action happened" rather than a backdatable figure. The
     * accounting date the accountant may choose for a cash settlement
     * (invariant 5's settling transaction) is a separate concept and lives
     * only on the posted GL transaction's own date field, via $data['date'].
     */
    public function settle(Refund $refund, array $data, string $userId): Refund
    {
        return DB::transaction(function () use ($refund, $data, $userId) {
            $refund = Refund::where('company_id', $refund->company_id)->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== Refund::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['refund' => 'Only an accepted refund can be settled.']);
            }

            $method = $data['settlement_method'];

            if ($method === Refund::SETTLEMENT_CREDIT && $refund->party_type !== Refund::PARTY_AGENT) {
                throw ValidationException::withMessages(['settlement_method' => 'A vendor refund can only be settled by receiving cash.']);
            }

            $creditPayment = null;

            if ($method === Refund::SETTLEMENT_CASH) {
                $this->coreService->postRefundSettleCash($refund, $data['account_id'], $data['date'] ?? now()->toDateString());
                $status = Refund::STATUS_REFUNDED;
            } else {
                $creditPayment = $this->coreService->postRefundSettleCredit($refund);
                $status = Refund::STATUS_CREDITED;
            }

            // settled_payment_id stays null for a cash settlement -- cash
            // settles straight to a bank/cash account, not a party, and no
            // GroupPayment row exists to point at (see
            // 2026_08_21_000004_add_umrah_refund_settlement_lifecycle.php).
            // A credit settlement's GroupPayment IS the credit, so recording
            // the link here is what stops the refund and the advance it
            // created from ever being reconciled apart.
            $refund->update([
                'status' => $status,
                'settlement_method' => $method,
                'settled_at' => now(),
                'settled_by_user_id' => $userId,
                'settled_payment_id' => $creditPayment?->id,
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Invariant 3: a refund cannot be approved for more than the credit
     * available to that party.
     *
     * Phase 1 has no per-party balance on account 2200 (Agent Advances) or
     * account 1170 (Refunds Receivable, which does not exist until Phase 2)
     * to check against -- those require the GL postings and accounts Phase 2
     * builds. What this method checks honestly, today, are the same
     * denormalised balances the rest of the Umrah module already trusts for
     * this party (Agent::total_paid/total_receivable,
     * VisaVendor/HotelVendor::total_paid/total_cost). This is an
     * approximation of the real 2200/1170 balance, not the balance itself,
     * and a future phase should replace it with a query against the posted
     * ledger.
     *
     * The comparison is in the company's BASE currency throughout, not the
     * refund's transaction currency: total_paid/total_receivable/total_cost
     * are denormalised base-currency balances (see
     * UmrahCoreService::recalculateGroup(), which accumulates them from
     * base_amount), so the refund side of the comparison must be
     * base_amount too. Umrah companies routinely transact against a base
     * currency other than the one on the voucher -- comparing amount
     * (transaction currency) against a base-currency ceiling would compare
     * two different currencies as if they were the same number.
     *
     * Called from inside approve()'s transaction, after that method has
     * already locked the refund row with lockForUpdate -- see
     * consumedCredit() for why the sum this depends on is locked too.
     */
    public function assertWithinAvailableCredit(Refund $refund): void
    {
        $available = round($this->availableCredit($refund), 2);
        $requested = round((float) $refund->base_amount, 2);

        if ($requested <= $available) {
            return;
        }

        /*
         * "Exceeds the credit currently available" is true and tells nobody
         * what to do about it. Two different situations arrive here and they
         * need different sentences: a party with nothing behind them can never
         * have a refund approved at any amount, which is a different problem
         * from having asked for too much. Only the second one has a number
         * worth quoting, and quoting it is the whole point -- it turns a
         * refusal into an instruction.
         */
        throw ValidationException::withMessages([
            'amount' => $available <= 0
                ? ($refund->party_type === Refund::PARTY_AGENT
                    ? 'This agent has no unallocated advance left, so there is nothing to refund. An agent can only be refunded what they have paid in excess of what they owe.'
                    : 'There is nothing left to refund against this vendor — either nothing has been paid to them, or earlier refunds already account for all of it.')
                : sprintf(
                    'This refund is for %s but only %s is available. Approve it for %s or less.',
                    number_format($requested, 2),
                    number_format($available, 2),
                    number_format($available, 2),
                ),
        ]);
    }

    /**
     * The ceiling differs by party because what each is owed differs:
     *
     * - **Agent**: a liability. The company owes back only what the agent
     *   paid in excess of what they owe -- the 2200 balance attributable to
     *   them, i.e. `max(0, total_paid - total_receivable)`.
     * - **Vendor** (visa, transport, hotel): an asset. The company is owed
     *   back what it actually handed the vendor, full stop -- there is no
     *   "excess" to isolate. The canonical case is a visa fee paid but never
     *   processed: cost recorded equals amount paid, so an overpayment-style
     *   ceiling would always yield zero and the refund could never be
     *   approved. The ceiling is `max(0, total_paid)`.
     *
     * Either ceiling is a balance, not a pool: it does not shrink on its own
     * as refunds are approved against it. So the ceiling alone is not the
     * available credit -- what has already been granted against this party
     * (status accepted, refunded or credited, this refund excluded) is
     * subtracted first. This subtraction is exact, a straight sum over this
     * table; it is only the ceiling underneath (total_paid) that remains a
     * denormalised approximation a future phase's ledger query should
     * replace. The subtraction itself does not change when that happens.
     */
    public function availableCredit(Refund $refund): float
    {
        $ceiling = match ($refund->party_type) {
            Refund::PARTY_AGENT => $this->creditFromOverpayment(
                Agent::find($refund->party_id)
            ),
            Refund::PARTY_VISA_VENDOR, Refund::PARTY_TRANSPORT_VENDOR => $this->creditFromAmountPaid(
                VisaVendor::find($refund->party_id)
            ),
            Refund::PARTY_HOTEL_VENDOR => $this->creditFromAmountPaid(
                HotelVendor::find($refund->party_id)
            ),
            default => 0.0,
        };

        return round($ceiling, 2) - round($this->consumedCredit($refund), 2);
    }

    /**
     * Sum of `base_amount` already granted against this party -- refunds
     * `accepted`, `refunded` or `credited`, this refund excluded. `requested`
     * does not count (not yet an obligation); `rejected` and `cancelled` do
     * not count either (never became one, or were reversed before
     * settlement). `refunded`/`credited` count exactly as `accepted` did in
     * Phase 1: settling a refund does not release its claim on the ceiling,
     * it fulfils it -- the money (or the credit) is gone either way, and
     * only rejecting or cancelling ever gives the ceiling room back.
     *
     * Summed in base_amount, not amount, for the same reason
     * assertWithinAvailableCredit() compares base_amount: the ceiling this
     * is subtracted from is a base-currency figure, and two refunds against
     * the same party need not share a transaction currency.
     *
     * Locks the matching rows with lockForUpdate so that two approvals
     * racing each other against the same party cannot both read the same
     * stale sum and both pass a check that, taken together, overspends the
     * ceiling. Postgres will not combine FOR UPDATE with an aggregate, so
     * the rows are fetched and summed in PHP rather than summed in SQL.
     * Callers must already be inside the transaction approve() opens.
     *
     * What is subtracted is a granted refund's amount MINUS whatever of it
     * has already been drawn off the party's advances as
     * payment_allocations rows -- see
     * UmrahCoreService::debitAgentAdvances(). A draw has already shrunk
     * the ceiling on the other side, inside
     * unallocatedAgentAdvances(), so counting it here as well would
     * subtract the same refund twice and make an agent's own credit
     * unreachable the moment they used any of it. The two cases the
     * remainder covers are real, not theoretical: a vendor refund draws
     * nothing (debitAgentAdvances only runs for agents), and an agent
     * whose ceiling came from the denormalised Agent::total_paid rather
     * than from live advances has nothing on the rows to draw against.
     * Both still have to consume the ceiling, and here is the only place
     * that can do it.
     */
    private function consumedCredit(Refund $refund): float
    {
        $granted = Refund::where('company_id', $refund->company_id)
            ->where('party_type', $refund->party_type)
            ->where('party_id', $refund->party_id)
            ->where('id', '!=', $refund->id)
            ->whereIn('status', [Refund::STATUS_ACCEPTED, Refund::STATUS_REFUNDED, Refund::STATUS_CREDITED])
            ->lockForUpdate()
            ->get();

        if ($granted->isEmpty()) {
            return 0.0;
        }

        $drawnByRefund = PaymentAllocation::whereIn('refund_id', $granted->pluck('id'))
            ->whereNull('reversed_at')
            ->lockForUpdate()
            ->get()
            ->groupBy('refund_id')
            ->map(fn ($rows) => round((float) $rows->sum('base_amount'), 2));

        return (float) $granted->sum(function (Refund $row) use ($drawnByRefund) {
            $drawn = (float) ($drawnByRefund[$row->id] ?? 0.0);

            return max(0.0, round((float) $row->base_amount, 2) - $drawn);
        });
    }

    /**
     * `Agent::total_paid` is `recalculateAgent()`'s sum of *group*
     * total_paid, and a group's total_paid can never exceed its
     * total_receivable -- `allocatePayment()` caps every allocation at the
     * group's outstanding balance (see `allocationOutstanding()`). So
     * `total_paid - total_receivable` is structurally zero for every agent,
     * every time recalculateAgent() has run; it is kept here only because
     * existing tests seed it directly as a stand-in for a future ledger
     * query, per the class docblock. A real overpayment never lives on a
     * group at all -- it is the part of a payment `allocatePayment()`
     * refused to cap onto one, which is exactly what settling a refund as
     * credit now creates (an unallocated `GroupPayment`, see
     * UmrahCoreService::postRefundSettleCredit()). Without counting that
     * directly, a credited refund's money would be real in 2200 and
     * invisible to the one check that decides whether it can ever come
     * back out -- so it is summed here from the same unallocated-advance
     * total `TravelReportService::agentStatement()` labels "Available
     * advances" and `advances()` treats as `state = unallocated`.
     */
    private function creditFromOverpayment(mixed $party): float
    {
        $denormalised = max(0.0, round((float) $party?->total_paid, 2) - round((float) $party?->total_receivable, 2));
        $unallocated = $party instanceof Agent ? $this->unallocatedAgentAdvances($party) : 0.0;

        return round($denormalised + $unallocated, 2);
    }

    /**
     * Locked for the same reason consumedCredit() locks its rows: two
     * refund approvals (or an approval racing an allocation) must not both
     * read the same unspent advance and both pass a check that, taken
     * together, spends it twice. Callers must already be inside the
     * transaction approve() opens.
     */
    private function unallocatedAgentAdvances(Agent $agent): float
    {
        $payments = GroupPayment::where('company_id', $agent->company_id)
            ->where('agent_id', $agent->id)
            ->where('direction', GroupPayment::DIRECTION_RECEIVED)
            ->where('status', GroupPayment::STATUS_POSTED)
            ->lockForUpdate()
            ->get(['id', 'base_amount']);

        if ($payments->isEmpty()) {
            return 0.0;
        }

        $allocatedByPayment = PaymentAllocation::whereIn('group_payment_id', $payments->pluck('id'))
            ->whereNull('reversed_at')
            ->lockForUpdate()
            ->get()
            ->groupBy('group_payment_id')
            ->map(fn ($rows) => (float) $rows->sum('base_amount'));

        // The query above is deliberately not filtered by visa_group_id. A
        // refund's approve() draws the agent's advances down through
        // UmrahCoreService::debitAgentAdvances(), which writes ordinary
        // payment_allocations rows carrying a refund_id instead of a
        // visa_group_id. That money left 2200 for 2300 the moment approve()
        // ran, so it must count against "available" exactly like a group
        // allocation does -- and because both are the same row type, it
        // already does. Subtracting refund draws a second time here is what
        // would double-count them.
        return round($payments->sum(function (GroupPayment $payment) use ($allocatedByPayment) {
            $allocated = round((float) ($allocatedByPayment[$payment->id] ?? 0.0), 2);

            return max(0.0, round((float) $payment->base_amount, 2) - $allocated);
        }), 2);
    }

    private function creditFromAmountPaid(mixed $party): float
    {
        return max(0.0, round((float) $party?->total_paid, 2));
    }

    /**
     * The four-step de-allocation sequence docs/contracts/refunds.md
     * requires before a group refund can be accepted -- only reached when
     * visa_group_id is set, which StoreRefundRequest already restricts to
     * agent refunds:
     *
     *  1. Find this party's live allocations against this group.
     *  2. If their total is less than the refund, the refund cannot be
     *     covered by this group's credit -- refuse it.
     *  3. Reverse whole allocations, oldest first, until the reversed total
     *     covers the refund.
     *  4. If the allocation that tipped the total over the refund amount
     *     reversed more than needed, re-allocate the difference back onto
     *     the same group immediately. Skipping this step is the easy
     *     mistake: without it the group's total_paid drops by the whole
     *     reversed allocation instead of by exactly the refund amount.
     */
    private function deallocateForGroupRefund(Refund $refund, ?string $userId): void
    {
        $refundAmount = round((float) $refund->base_amount, 2);

        $allocations = PaymentAllocation::where('company_id', $refund->company_id)
            ->where('visa_group_id', $refund->visa_group_id)
            ->whereNull('reversed_at')
            ->whereHas('payment', fn ($query) => $query
                ->where('direction', GroupPayment::DIRECTION_RECEIVED)
                ->where('agent_id', $refund->party_id))
            ->with('payment')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $available = round((float) $allocations->sum('base_amount'), 2);

        if ($available < $refundAmount) {
            throw ValidationException::withMessages([
                'amount' => 'This refund exceeds the credit allocated to this group.',
            ]);
        }

        $reason = "Refund {$refund->refund_number} accepted against this group's allocation.";
        $reversedSoFar = 0.0;

        foreach ($allocations as $allocation) {
            if ($reversedSoFar >= $refundAmount) {
                break;
            }

            $remainingNeeded = round($refundAmount - $reversedSoFar, 2);
            $allocationAmount = round((float) $allocation->base_amount, 2);
            $payment = $allocation->payment;

            $this->coreService->reverseAllocation($allocation, $reason, $userId);
            $reversedSoFar = round($reversedSoFar + $allocationAmount, 2);

            if ($allocationAmount > $remainingNeeded) {
                $this->coreService->allocatePayment($payment, [
                    'visa_group_id' => $refund->visa_group_id,
                    'base_amount' => round($allocationAmount - $remainingNeeded, 2),
                ]);
            }
        }
    }

    public function nextRefundNumber(string $companyId): string
    {
        $latest = Refund::where('company_id', $companyId)
            ->where('refund_number', 'like', 'URF-%')
            ->orderByDesc('refund_number')
            ->value('refund_number');

        $next = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('URF-%05d', $next);
    }

    private function company(string $companyId): Company
    {
        return Company::findOrFail($companyId);
    }
}
