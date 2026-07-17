<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherPassengerAssignmentService
{
    public function __construct(private UmrahCoreService $core) {}

    public function move(Voucher $source, Voucher $target, array $passengerIds): array
    {
        return DB::transaction(function () use ($source, $target, $passengerIds) {
            $voucherIds = collect([$source->id, $target->id])->sort()->values();
            $lockedVouchers = Voucher::where('company_id', $source->company_id)
                ->whereIn('id', $voucherIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $source = $lockedVouchers->get($source->id);
            $target = $lockedVouchers->get($target->id);
            if (! $source || ! $target) {
                throw ValidationException::withMessages(['target_voucher_id' => 'The source or destination voucher is no longer available.']);
            }
            $this->assertDraftPair($source, $target);

            $assignments = $this->selectedAssignments($source, $passengerIds);
            $sourceBefore = $this->passengerIds($source);
            $targetBefore = $this->passengerIds($target);
            if ($assignments->count() >= count($sourceBefore)) {
                throw ValidationException::withMessages([
                    'passenger_ids' => 'At least one passenger must remain on the source voucher. Use Separate vouchers to separate everyone.',
                ]);
            }

            VoucherPassenger::whereIn('id', $assignments->pluck('id')->all())->update(['voucher_id' => $target->id]);

            return [
                'source' => $source->fresh(),
                'target' => $target->fresh(),
                'source_before' => $sourceBefore,
                'source_after' => $this->passengerIds($source),
                'target_before' => $targetBefore,
                'target_after' => $this->passengerIds($target),
                'moved_passenger_ids' => $assignments->pluck('passenger_id')->values()->all(),
            ];
        });
    }

    public function separate(Voucher $source, array $passengerIds, ?string $userId): array
    {
        return DB::transaction(function () use ($source, $passengerIds, $userId) {
            $source = Voucher::where('company_id', $source->company_id)->lockForUpdate()->findOrFail($source->id);
            $this->assertDraft($source, 'Only draft vouchers can be separated.');
            $assignments = $this->selectedAssignments($source, $passengerIds);
            $sourceBefore = $this->passengerIds($source);
            $archiveSource = $assignments->count() === count($sourceBefore);
            $sourceOwnsBilling = $source->billing_voucher_id === null;
            $created = collect();

            foreach ($assignments as $index => $assignment) {
                $clone = $source->replicate([
                    'voucher_number',
                    'status',
                    'created_by_user_id',
                    'hotel_sale_transaction_id',
                    'hotel_cost_transaction_id',
                    'source_voucher_id',
                    'billing_voucher_id',
                ]);
                $clone->voucher_number = $this->core->nextVoucherNumber($source->company_id);
                $clone->status = Voucher::STATUS_DRAFT;
                $clone->created_by_user_id = $userId;
                $clone->source_voucher_id = $source->id;
                $clone->hotel_sale_transaction_id = null;
                $clone->hotel_cost_transaction_id = null;

                $billingPlan = $source->separatedBillingPlan($archiveSource, $index);
                $clone->billing_voucher_id = $billingPlan['billing_voucher_id'];
                if (! $billingPlan['retain_hotel_amounts']) {
                    $clone->hotel_sale_amount = 0;
                    $clone->hotel_cost_amount = 0;
                }
                $clone->save();

                $assignment->update(['voucher_id' => $clone->id]);
                $created->push($clone);
            }

            if ($archiveSource) {
                if ($sourceOwnsBilling) {
                    $newBillingOwner = $created->first();
                    Voucher::where('company_id', $source->company_id)
                        ->where('billing_voucher_id', $source->id)
                        ->whereKeyNot($newBillingOwner->id)
                        ->update(['billing_voucher_id' => $newBillingOwner->id]);
                }
                $source->delete();
            }

            return [
                'source' => $source,
                'source_before' => $sourceBefore,
                'source_after' => $archiveSource ? [] : $this->passengerIds($source),
                'source_archived' => $archiveSource,
                'created' => $created->map->fresh()->values(),
                'separated_passenger_ids' => $assignments->pluck('passenger_id')->values()->all(),
            ];
        });
    }

    private function assertDraftPair(Voucher $source, Voucher $target): void
    {
        $this->assertDraft($source, 'Passengers can be moved only from a draft voucher.');
        $this->assertDraft($target, 'Passengers can be moved only to a draft voucher.');
        if ($source->is($target)) {
            throw ValidationException::withMessages(['target_voucher_id' => 'Choose a different destination voucher.']);
        }
        if ($source->visa_group_id !== $target->visa_group_id || $source->agent_id !== $target->agent_id) {
            throw ValidationException::withMessages(['target_voucher_id' => 'The destination must belong to the same group and agent.']);
        }
    }

    private function assertDraft(Voucher $voucher, string $message): void
    {
        if ($voucher->status !== Voucher::STATUS_DRAFT) {
            throw ValidationException::withMessages(['voucher' => $message]);
        }
    }

    private function selectedAssignments(Voucher $source, array $passengerIds): Collection
    {
        $ids = array_values(array_unique($passengerIds));
        $assignments = VoucherPassenger::where('company_id', $source->company_id)
            ->where('voucher_id', $source->id)
            ->whereIn('passenger_id', $ids)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        if ($assignments->count() !== count($ids)) {
            throw ValidationException::withMessages(['passenger_ids' => 'One or more selected passengers are not assigned to this voucher.']);
        }

        return $assignments;
    }

    private function passengerIds(Voucher $voucher): array
    {
        return VoucherPassenger::where('company_id', $voucher->company_id)
            ->where('voucher_id', $voucher->id)
            ->orderBy('created_at')
            ->pluck('passenger_id')
            ->all();
    }
}
