<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExistingPassengerJoinService
{
    public function canJoin(Voucher $voucher): bool
    {
        return $voucher->status === Voucher::STATUS_DRAFT
            && VisaGroup::where('company_id', $voucher->company_id)->whereKey($voucher->visa_group_id)
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)->exists()
            && ! $voucher->amends_voucher_id && ! $voucher->superseded_at
            && ! $voucher->superseded_by_voucher_id && ! $voucher->billing_voucher_id
            && ! $voucher->hotel_sale_transaction_id && ! $voucher->hotel_cost_transaction_id
            && ! Voucher::where('company_id', $voucher->company_id)
                ->where(fn ($q) => $q->where('billing_voucher_id', $voucher->id)
                    ->orWhere(fn ($a) => $a->where('amends_voucher_id', $voucher->id)->where('status', Voucher::STATUS_DRAFT)))->exists();
    }

    private function eligible(string $companyId): Builder
    {
        return Passenger::where('company_id', $companyId)
            ->whereHas('group', fn ($q) => $q->where('company_id', $companyId)->where('status', '!=', VisaGroup::STATUS_CANCELLED))
            ->whereNotIn('id', VoucherPassenger::where('company_id', $companyId)->select('passenger_id'));
    }

    public function search(Voucher $voucher, string $search): array
    {
        $search = mb_substr(trim($search), 0, 100);
        if (mb_strlen($search) < 2 || ! $this->canJoin($voucher)) {
            return [];
        }

        return $this->eligible($voucher->company_id)
            ->where(fn ($q) => $q->where('full_name', 'ilike', '%'.$search.'%')
                ->orWhere('passport_number', 'ilike', '%'.$search.'%')
                ->orWhereHas('group', fn ($g) => $g->where('group_number', 'ilike', '%'.$search.'%')))
            ->with('group:id,group_number')->orderBy('full_name')->orderBy('id')->limit(50)
            ->get(['id', 'full_name', 'passport_number', 'visa_group_id'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name, 'passport' => $p->passport_number,
                'group_number' => $p->group->group_number])->all();
    }

    public function join(Voucher $voucher, array $passengerIds): array
    {
        return DB::transaction(function () use ($voucher, $passengerIds) {
            $voucher = Voucher::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($voucher->id);
            if (empty($passengerIds) || count($passengerIds) > 50 || ! $this->canJoin($voucher)) {
                throw ValidationException::withMessages(['passenger_ids' => 'Add passengers before approval, on an ordinary draft voucher without shared billing.']);
            }
            $passengers = Passenger::where('company_id', $voucher->company_id)->whereIn('id', $passengerIds)
                ->orderBy('id')->lockForUpdate()->get();
            $groupIds = $passengers->pluck('visa_group_id')->push($voucher->visa_group_id)->unique()->sort()->values();
            $groups = VisaGroup::where('company_id', $voucher->company_id)->whereIn('id', $groupIds)
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($passengers->count() !== count($passengerIds) || $groups->count() !== $groupIds->count()
                || VoucherPassenger::where('company_id', $voucher->company_id)->whereIn('passenger_id', $passengerIds)->exists()) {
                throw ValidationException::withMessages(['passenger_ids' => 'A selected passenger is unavailable or already has a voucher. Search again; nothing was added.']);
            }
            $joined = [];
            foreach ($passengers as $passenger) {
                VoucherPassenger::create(['company_id' => $voucher->company_id, 'voucher_id' => $voucher->id,
                    'visa_group_id' => $passenger->visa_group_id, 'passenger_id' => $passenger->id]);
                $joined[] = ['id' => $passenger->id, 'name' => $passenger->full_name,
                    'original_group_id' => $passenger->visa_group_id, 'original_group' => $groups[$passenger->visa_group_id]->group_number];
            }

            return $joined;
        });
    }
}
