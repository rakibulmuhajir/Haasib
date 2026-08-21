<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaVendor;

/**
 * "Refunds waiting on you" — refunds sitting at `requested`, waiting for an
 * approver to accept or reject them. This is the queue that makes an agent's
 * request visible to the company instead of it living only in WhatsApp,
 * which is the entire reason the `requested` status exists
 * (docs/contracts/refunds.md, Phase 3).
 *
 * Gated on umrah.refund.approve, not umrah.refund.view: everyone who can see
 * a refund can view it on the Refunds page already, but this widget is a
 * decision queue, and only an approver can act on what it lists.
 *
 * Oldest first — the request that has waited longest is the one that most
 * needs a person, so it must not be able to hide behind newer ones.
 */
class RefundsAwaitingDecisionWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.refunds_awaiting_decision';
    }

    public function title(): string
    {
        return 'Refunds waiting on you';
    }

    public function description(): string
    {
        return 'Requested refunds not yet accepted or rejected.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_REFUND_APPROVE;
    }

    public function defaultSpan(): int
    {
        return 12;
    }

    public function minSpan(): int
    {
        return 12;
    }

    public function resolve(Company $company, User $user, array $options): array
    {
        $limit = (int) ($options['limit'] ?? 10);

        $refunds = Refund::where('company_id', $company->id)
            ->where('status', Refund::STATUS_REQUESTED)
            ->with('requestedBy:id,name')
            ->orderBy('requested_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'refund_number', 'party_type', 'party_id', 'amount', 'currency', 'reason', 'requested_by_user_id', 'requested_at']);

        $rows = $refunds->map(fn (Refund $refund): array => [
            'id' => $refund->id,
            'refund_number' => $refund->refund_number,
            'party_type' => $refund->party_type,
            'party_name' => $this->partyName($refund),
            'amount' => (float) $refund->amount,
            'currency' => $refund->currency,
            'reason' => $refund->reason,
            'requested_by' => $refund->requestedBy?->name,
            'requested_at' => $refund->requested_at?->toIso8601String(),
            'status' => $refund->status,
            'href' => route('umrah.refunds.show', ['company' => $company->slug, 'refund' => $refund->id]),
        ])->values()->all();

        return [
            'rows' => $rows,
            'currency' => $company->base_currency,
        ];
    }

    private function partyName(Refund $refund): ?string
    {
        return match ($refund->party_type) {
            Refund::PARTY_AGENT => Agent::find($refund->party_id)?->name,
            Refund::PARTY_VISA_VENDOR, Refund::PARTY_TRANSPORT_VENDOR => VisaVendor::find($refund->party_id)?->name,
            Refund::PARTY_HOTEL_VENDOR => HotelVendor::find($refund->party_id)?->name,
            default => null,
        };
    }
}
