<?php

namespace App\Modules\Umrah\Services;

use App\Constants\Permissions;
use App\Models\User;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;

/** Read-only operational links; purchase membership and accounting never change. */
class GroupTravellingParties
{
    public function __construct(private TravelAccessService $access) {}

    public function forGroup(VisaGroup $group, ?User $user): array
    {
        $isAgent = $this->access->isAgentMember($group->company_id, $user);
        $agentId = $isAgent ? $this->access->linkedAgent($group->company_id, $user)?->id : null;
        $canViewVoucher = (bool) $user?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_VIEW);
        $assignments = VoucherPassenger::where('company_id', $group->company_id)
            ->whereHas('voucher', fn ($query) => $query->where('company_id', $group->company_id)
                ->whereIn('status', [Voucher::STATUS_DRAFT, Voucher::STATUS_APPROVED])
                ->whereNull('superseded_by_voucher_id')
                ->where(fn ($current) => $current->where('status', Voucher::STATUS_APPROVED)->orWhereNull('amends_voucher_id')))
            ->where(fn ($query) => $query->where('visa_group_id', $group->id)
                ->orWhereHas('voucher', fn ($voucher) => $voucher->where('visa_group_id', $group->id)))
            ->with(['voucher.agent', 'voucher.group', 'passenger', 'group'])
            ->orderBy('created_at')->orderBy('id')->get();

        $own = [];
        $joining = [];
        $notices = [];
        foreach ($assignments as $assignment) {
            if (! $assignment->passenger) {
                continue;
            }
            $voucher = $assignment->voucher;
            $visible = $canViewVoucher && (! $isAgent || $voucher->agent_id === $agentId);
            $party = $visible ? [
                'id' => $voucher->id,
                'number' => $voucher->voucher_number,
                'agent' => $voucher->agent?->name,
                'status' => $voucher->status,
                'elsewhere' => $voucher->visa_group_id !== $group->id,
            ] : ['id' => null, 'number' => null, 'agent' => null, 'status' => null,
                'elsewhere' => $voucher->visa_group_id !== $group->id];

            if ($assignment->visa_group_id === $group->id) {
                $own[$assignment->passenger_id] = $party;
                if ($voucher->visa_group_id !== $group->id) {
                    $notices[] = ['name' => $assignment->passenger->full_name, 'direction' => 'out',
                        'group_number' => $visible ? $voucher->group?->group_number : null];
                }
            } elseif ($visible && $voucher->visa_group_id === $group->id) {
                $notices[] = ['name' => $assignment->passenger->full_name, 'direction' => 'in',
                    'group_number' => ! $isAgent ? $assignment->group?->group_number : null];
                $joining[] = [
                    'id' => $assignment->passenger_id,
                    'name' => $assignment->passenger->full_name,
                    'passport' => $assignment->passenger->passport_number,
                    'original_group' => ! $isAgent ? $assignment->group?->group_number : null,
                    'original_group_id' => ! $isAgent ? $assignment->visa_group_id : null,
                    'voucher' => $party,
                ];
            }
        }

        return ['assignments' => (object) $own, 'joining' => $joining, 'notices' => $notices];
    }
}
