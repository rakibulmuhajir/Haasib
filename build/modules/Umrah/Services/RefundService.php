<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 1 of docs/contracts/refunds.md -- "The obligation". Moves a refund
 * through request -> approve/reject, and approved -> cancel. No ledger
 * posting happens here: Phase 2 owns the accounts, the postings, and the
 * single-allocation reversal a group refund requires before it can be
 * approved. Every seam Phase 2 needs to fill in is marked below rather than
 * half-built.
 */
class RefundService
{
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
                'status' => Refund::STATUS_REQUESTED,
                'requested_by_user_id' => $userId,
                'requested_at' => now(),
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Moves a requested refund to approved. This is the control point that
     * stops an agent refunding themselves -- only someone with
     * `refund.approve` reaches this method (enforced by the FormRequest).
     *
     * PHASE 2 SEAM: refunds.md's Accounting section requires this moment to
     * post Dr 2200/Cr 2300 (agent) or Dr 1170/Cr cost account (vendor), and
     * -- for a refund against a group -- to reverse the corresponding
     * PaymentAllocation first via the single-allocation-reversal primitive
     * extracted from UmrahCoreService::reversePayment(). None of that is
     * built yet. This method only moves the status and stamps the approver,
     * exactly as the task for this phase requires.
     */
    public function approve(Refund $refund, array $data, string $userId): Refund
    {
        return DB::transaction(function () use ($refund, $data, $userId) {
            $refund = Refund::where('company_id', $refund->company_id)->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== Refund::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['refund' => 'Only a requested refund can be approved.']);
            }

            $this->assertWithinAvailableCredit($refund);

            // PHASE 2 SEAM: post the ledger entry for this refund and, if
            // visa_group_id is set, reverse the allocation it settles before
            // this update runs. See class docblock.

            $refund->update([
                'status' => Refund::STATUS_ACCEPTED,
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'review_remarks' => $data['review_remarks'] ?? null,
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
     * PHASE 2 SEAM: cancelling an approved refund reverses whatever Phase 2
     * posted at approval (Dr 2300/Cr 2200 for an agent, Dr cost/Cr 1170 for a
     * vendor). Nothing has been posted in this phase, so there is nothing to
     * reverse yet -- this only moves the status and records who cancelled it.
     */
    public function cancel(Refund $refund, string $reason, string $userId): Refund
    {
        return DB::transaction(function () use ($refund, $reason, $userId) {
            $refund = Refund::where('company_id', $refund->company_id)->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== Refund::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['refund' => 'Only an approved refund can be cancelled.']);
            }

            $refund->update([
                'status' => Refund::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
                'cancellation_reason' => $reason,
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
     * and Phase 2 should replace it with a query against the posted ledger
     * once those accounts exist.
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
        $available = $this->availableCredit($refund);

        if (round((float) $refund->base_amount, 2) > round($available, 2)) {
            throw ValidationException::withMessages([
                'amount' => 'This refund exceeds the credit currently available to this party.',
            ]);
        }
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
     * (status approved or paid, this refund excluded) is subtracted first.
     * This subtraction is exact, a straight sum over this table; it is only
     * the ceiling underneath (total_paid) that remains a denormalised
     * approximation Phase 2's ledger query should replace. The subtraction
     * itself does not change when that happens.
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
     * `approved` or `paid`, this refund excluded. `requested` does not count
     * (not yet an obligation); `rejected` and `cancelled` do not count
     * either (never became one, or were reversed before settlement).
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
     */
    private function consumedCredit(Refund $refund): float
    {
        return (float) Refund::where('company_id', $refund->company_id)
            ->where('party_type', $refund->party_type)
            ->where('party_id', $refund->party_id)
            ->where('id', '!=', $refund->id)
            ->whereIn('status', [Refund::STATUS_ACCEPTED, Refund::STATUS_PAID])
            ->lockForUpdate()
            ->get()
            ->sum(fn (Refund $granted) => round((float) $granted->base_amount, 2));
    }

    private function creditFromOverpayment(mixed $party): float
    {
        return max(0.0, round((float) $party?->total_paid, 2) - round((float) $party?->total_receivable, 2));
    }

    private function creditFromAmountPaid(mixed $party): float
    {
        return max(0.0, round((float) $party?->total_paid, 2));
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
