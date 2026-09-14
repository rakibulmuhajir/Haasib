<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherPassengerAssignmentService
{
    public function __construct(private UmrahCoreService $core, private VoucherWorkflowService $workflow) {}

    public function move(Voucher $source, Voucher $target, array $passengerIds, bool $allowApproved = false, ?string $reason = null): array
    {
        return DB::transaction(function () use ($source, $target, $passengerIds, $allowApproved, $reason) {
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
            $this->assertTransferPair($source, $target, $allowApproved, $reason);

            $assignments = $this->selectedAssignments($source, $passengerIds);
            $sourceBefore = $this->passengerIds($source);
            $targetBefore = $this->passengerIds($target);
            $sourceManifest = $this->manifest($source);
            $targetManifest = $this->manifest($target);
            $sourcePurchaseSnapshot = $this->purchaseSnapshot($source);
            $targetPurchaseSnapshot = $this->purchaseSnapshot($target);
            if ($source->status === Voucher::STATUS_DRAFT && $target->status === Voucher::STATUS_DRAFT
                && $source->visa_group_id === $target->visa_group_id && $assignments->count() >= count($sourceBefore)) {
                throw ValidationException::withMessages([
                    'passenger_ids' => 'At least one passenger must remain on the source voucher. Use Separate vouchers to separate everyone.',
                ]);
            }

            VoucherPassenger::whereIn('id', $assignments->pluck('id')->all())->update(['voucher_id' => $target->id]);
            if ($assignments->contains('passenger_id', $source->leader_passenger_id)) {
                $source->update(['leader_passenger_id' => null]);
            }
            if ($target->status === Voucher::STATUS_APPROVED) {
                $this->workflow->assertPassengerSourcesReady($target->fresh());
            }
            // Issued copies need a distinguishable version for reprinting,
            // without entering the financial amendment workflow.
            foreach ([$source, $target] as $voucher) {
                if ($voucher->status === Voucher::STATUS_APPROVED) {
                    $voucher->version_number = ($voucher->version_number ?: 1) + 1;
                }
                $voucher->touch();
            }

            return [
                'source' => $source->fresh(),
                'target' => $target->fresh(),
                'source_before' => $sourceBefore,
                'source_after' => $this->passengerIds($source),
                'target_before' => $targetBefore,
                'target_after' => $this->passengerIds($target),
                'moved_passenger_ids' => $assignments->pluck('passenger_id')->values()->all(),
                'source_manifest_before' => $sourceManifest,
                'source_manifest_after' => $this->manifest($source),
                'target_manifest_before' => $targetManifest,
                'target_manifest_after' => $this->manifest($target),
                'source_purchase_snapshot' => $sourcePurchaseSnapshot,
                'target_purchase_snapshot' => $targetPurchaseSnapshot,
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
                    'hotel_confirmations',
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
                $clone->leader_passenger_id = $source->leader_passenger_id === $assignment->passenger_id
                    ? $assignment->passenger_id : null;
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
            } elseif ($assignments->contains('passenger_id', $source->leader_passenger_id)) {
                $source->update(['leader_passenger_id' => null]);
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

    private function assertTransferPair(Voucher $source, Voucher $target, bool $allowApproved, ?string $reason): void
    {
        foreach ([$source, $target] as $voucher) {
            if (! in_array($voucher->status, [Voucher::STATUS_DRAFT, Voucher::STATUS_APPROVED], true)
                || $voucher->superseded_at || $voucher->superseded_by_voucher_id) {
                throw ValidationException::withMessages(['target_voucher_id' => 'Use current draft or approved vouchers, not cancelled or superseded vouchers.']);
            }
            if ($voucher->status === Voucher::STATUS_APPROVED && (! $allowApproved || mb_strlen(trim($reason ?? '')) < 5)) {
                throw ValidationException::withMessages(['override_reason' => 'An authorised company approver and a reason are required to change an issued travelling party.']);
            }
        }
        if ($source->is($target)) {
            throw ValidationException::withMessages(['target_voucher_id' => 'Choose a different destination voucher.']);
        }
        if ($source->company_id !== $target->company_id) {
            throw ValidationException::withMessages(['target_voucher_id' => 'The destination must belong to this company.']);
        }
        if (($source->status === Voucher::STATUS_DRAFT && $source->amends_voucher_id)
            || ($target->status === Voucher::STATUS_DRAFT && $target->amends_voucher_id)
            || Voucher::where('company_id', $source->company_id)->where('status', Voucher::STATUS_DRAFT)
                ->whereIn('amends_voucher_id', [$source->id, $target->id])->exists()) {
            throw ValidationException::withMessages(['target_voucher_id' => 'Finish or discard the pending amendment before moving passengers.']);
        }
        if (($source->visa_group_id !== $target->visa_group_id
            || $source->status === Voucher::STATUS_APPROVED || $target->status === Voucher::STATUS_APPROVED) && (
                $source->billing_voucher_id || $target->billing_voucher_id
                || Voucher::where('company_id', $source->company_id)
                    ->whereIn('billing_voucher_id', [$source->id, $target->id])->exists()
            )) {
            throw ValidationException::withMessages(['target_voucher_id' => 'Shared hotel-billing vouchers cannot be transferred in this workflow. Resolve the shared booking separately.']);
        }
    }

    private function manifest(Voucher $voucher): array
    {
        return [
            'version_number' => $voucher->version_number ?: 1,
            'leader_passenger_id' => $voucher->leader_passenger_id,
            'passengers' => $voucher->passengers()->orderBy('sort_order')->orderBy('created_at')->get()
                ->map(fn ($passenger) => [
                    'id' => $passenger->id,
                    'full_name' => $passenger->full_name,
                    'passport_number' => $passenger->passport_number,
                    'visa_group_id' => $passenger->pivot->visa_group_id,
                ])->all(),
        ];
    }

    private function purchaseSnapshot(Voucher $voucher): array
    {
        return $voucher->only([
            'status', 'version_number', 'agent_id', 'visa_group_id', 'service_bundle',
            'hotel_stays', 'hotel_sale_amount', 'hotel_cost_amount',
            'hotel_sale_transaction_id', 'hotel_cost_transaction_id',
            'onward_departure_at', 'onward_arrival_at', 'return_departure_at', 'return_arrival_at',
        ]);
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
        if ($ids === []) {
            throw ValidationException::withMessages(['passenger_ids' => 'Select at least one passenger to move.']);
        }
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
