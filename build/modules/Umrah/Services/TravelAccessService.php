<?php

namespace App\Modules\Umrah\Services;

use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TravelAccessService
{
    public function companyRole(string $companyId, ?User $user): ?string
    {
        if (! $user || $user->isGodMode()) {
            return $user?->isGodMode() ? 'super_admin' : null;
        }

        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('role');
    }

    public function isAgentMember(string $companyId, ?User $user): bool
    {
        return $this->companyRole($companyId, $user) === 'agent';
    }

    public function linkedAgent(string $companyId, ?User $user): ?Agent
    {
        if (! $user) {
            return null;
        }

        return Agent::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    public function scopeAgentRecords(Builder $query, string $companyId, ?User $user): Builder
    {
        if (! $this->isAgentMember($companyId, $user)) {
            return $query;
        }

        $agentId = $this->linkedAgent($companyId, $user)?->id;

        return $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0');
    }

    public function groupTravelStartsAt(VisaGroup $group): ?CarbonImmutable
    {
        $starts = collect();
        if ($group->travel_date) {
            $starts->push(CarbonImmutable::parse($group->travel_date)->startOfDay());
        }

        $group->loadMissing('vouchers:id,visa_group_id,service_bundle,onward_departure_at,hotel_stays');
        foreach ($group->vouchers as $voucher) {
            if ($start = $this->voucherTravelStartsAt($voucher)) {
                $starts->push($start);
            }
        }

        return $starts->sort()->first();
    }

    public function voucherTravelStartsAt(Voucher $voucher): ?CarbonImmutable
    {
        if ($voucher->service_bundle !== Voucher::SERVICE_HOTEL && $voucher->onward_departure_at) {
            return CarbonImmutable::parse($voucher->onward_departure_at);
        }

        $firstCheckIn = collect($voucher->hotel_stays ?? [])->pluck('check_in_date')->filter()->sort()->first();

        return $firstCheckIn ? CarbonImmutable::parse($firstCheckIn)->startOfDay() : null;
    }

    public function groupHasStarted(VisaGroup $group): bool
    {
        return $this->groupTravelStartsAt($group)?->isPast() ?? false;
    }

    public function voucherHasStarted(Voucher $voucher): bool
    {
        return $this->voucherTravelStartsAt($voucher)?->isPast() ?? false;
    }

    public function agentCanEditGroup(string $companyId, ?User $user, VisaGroup $group): bool
    {
        $agent = $this->linkedAgent($companyId, $user);

        return $this->isAgentMember($companyId, $user)
            && $agent !== null
            && $group->agent_id === $agent->id
            && $agent->can_edit_group
            && ! $this->groupHasStarted($group);
    }

    public function agentCanEditVoucher(string $companyId, ?User $user, Voucher $voucher): bool
    {
        $agent = $this->linkedAgent($companyId, $user);

        return $this->isAgentMember($companyId, $user)
            && $agent !== null
            && $voucher->agent_id === $agent->id
            && $agent->can_edit_voucher
            && ! $this->voucherHasStarted($voucher);
    }

    public function agentCanModifyVoucherNow(string $companyId, ?User $user, Voucher $voucher): bool
    {
        if (! $this->agentCanEditVoucher($companyId, $user, $voucher)) {
            return false;
        }

        $agent = $this->linkedAgent($companyId, $user);
        $startsAt = $this->voucherTravelStartsAt($voucher);

        return $agent !== null
            && $startsAt !== null
            && $startsAt->greaterThanOrEqualTo(now()->addHours($agent->voucher_cutoff_hours));
    }
}
