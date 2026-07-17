<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherWorkflowService
{
    public function __construct(private UmrahCoreService $core) {}

    public function createAmendment(Voucher $voucher, string $voucherNumber, ?string $userId): Voucher
    {
        return DB::transaction(function () use ($voucher, $voucherNumber, $userId) {
            $voucher = Voucher::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($voucher->id);
            if ($voucher->status !== Voucher::STATUS_APPROVED || $voucher->superseded_at) {
                throw ValidationException::withMessages(['voucher' => 'Only the current approved voucher can be amended.']);
            }
            if ($voucher->amendments()->where('status', Voucher::STATUS_DRAFT)->exists()) {
                throw ValidationException::withMessages(['voucher' => 'This voucher already has an open draft amendment.']);
            }
            $assignments = $voucher->voucherPassengers()->lockForUpdate()->get();
            $amendment = $voucher->replicate([
                'source_voucher_id', 'billing_voucher_id', 'superseded_by_voucher_id', 'hotel_sale_transaction_id',
                'hotel_cost_transaction_id', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'superseded_at',
            ]);
            $amendment->fill([
                'voucher_number' => $voucherNumber,
                'status' => Voucher::STATUS_DRAFT,
                'amends_voucher_id' => $voucher->id,
                'version_number' => (int) $voucher->version_number + 1,
                'source_voucher_id' => $voucher->source_voucher_id ?: $voucher->id,
                'billing_voucher_id' => $voucher->billing_voucher_id,
                'hotel_sale_transaction_id' => null,
                'hotel_cost_transaction_id' => null,
                'created_by_user_id' => $userId,
            ]);
            $amendment->save();
            $assignments->each->delete();
            foreach ($assignments as $assignment) {
                VoucherPassenger::create([
                    'company_id' => $voucher->company_id,
                    'voucher_id' => $amendment->id,
                    'visa_group_id' => $voucher->visa_group_id,
                    'passenger_id' => $assignment->passenger_id,
                ]);
            }

            return $amendment->fresh();
        });
    }

    public function deleteDraft(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            $voucher = Voucher::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($voucher->id);
            if ($voucher->status !== Voucher::STATUS_DRAFT) {
                throw ValidationException::withMessages(['voucher' => 'Only draft vouchers can be deleted.']);
            }
            $passengerIds = $voucher->voucherPassengers()->pluck('passenger_id');
            $voucher->voucherPassengers()->delete();
            if ($voucher->amends_voucher_id) {
                foreach ($passengerIds as $passengerId) {
                    $old = VoucherPassenger::withTrashed()->where('voucher_id', $voucher->amends_voucher_id)
                        ->where('passenger_id', $passengerId)->first();
                    $old?->restore();
                }
            }
            $voucher->delete();
        });
    }

    public function approve(Voucher $voucher): Voucher
    {
        return DB::transaction(function () use ($voucher) {
            $voucher = Voucher::where('company_id', $voucher->company_id)->with('group')->lockForUpdate()->findOrFail($voucher->id);
            if ($voucher->status !== Voucher::STATUS_DRAFT) {
                return $voucher;
            }
            if ($voucher->amends_voucher_id) {
                $previous = Voucher::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($voucher->amends_voucher_id);
                $this->core->reverseVoucherHotelAccounting($previous, "Superseded by {$voucher->voucher_number}");
                $previous->update(['superseded_at' => now(), 'superseded_by_voucher_id' => $voucher->id]);
            }
            $voucher->update(['status' => Voucher::STATUS_APPROVED]);
            $this->core->applyVoucherHotelAccounting($voucher->fresh(), $voucher->group);

            return $voucher->fresh();
        });
    }

    public function cancel(Voucher $voucher, string $reason, ?string $userId): Voucher
    {
        return DB::transaction(function () use ($voucher, $reason, $userId) {
            $voucher = Voucher::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($voucher->id);
            if ($voucher->status !== Voucher::STATUS_APPROVED || $voucher->superseded_at) {
                throw ValidationException::withMessages(['voucher' => 'Only the current approved voucher can be cancelled.']);
            }
            $dependent = Voucher::where('company_id', $voucher->company_id)->where('billing_voucher_id', $voucher->id)
                ->where('status', '!=', Voucher::STATUS_CANCELLED)->whereNull('superseded_at')->lockForUpdate()->first();
            if (! $voucher->billing_voucher_id && $dependent) {
                $dependent->update([
                    'billing_voucher_id' => null,
                    'hotel_sale_amount' => $voucher->hotel_sale_amount,
                    'hotel_cost_amount' => $voucher->hotel_cost_amount,
                    'hotel_sale_transaction_id' => $voucher->hotel_sale_transaction_id,
                    'hotel_cost_transaction_id' => $voucher->hotel_cost_transaction_id,
                ]);
                Voucher::where('company_id', $voucher->company_id)->where('billing_voucher_id', $voucher->id)
                    ->whereKeyNot($dependent->id)->update(['billing_voucher_id' => $dependent->id]);
            } else {
                $this->core->reverseVoucherHotelAccounting($voucher, $reason);
            }
            $voucher->voucherPassengers()->delete();
            $voucher->update([
                'status' => Voucher::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
                'cancellation_reason' => $reason,
            ]);

            return $voucher->fresh();
        });
    }
}
