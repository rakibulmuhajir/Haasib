<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TravelAccessService;
use Carbon\Carbon;

/**
 * "Transport for upcoming groups" — assigned/unassigned + paid/unpaid, per
 * upcoming group. There is no negotiation status (contacted/agreed/hired) in
 * the schema — reporting only assigned/unassigned and paid/unpaid is
 * deliberate, not a shortcut.
 */
class TransportReadinessWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.transport_readiness';
    }

    public function title(): string
    {
        return 'Transport for upcoming groups';
    }

    public function description(): string
    {
        return 'Which upcoming groups have transport assigned and paid.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_GROUP_VIEW;
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
        $limit = (int) ($options['limit'] ?? 8);
        $today = Carbon::today();
        $access = app(TravelAccessService::class);

        $groups = $access->scopeAgentRecords(
            VisaGroup::where('company_id', $company->id)
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->whereNotNull('travel_date')
                ->whereDate('travel_date', '>=', $today),
            $company->id,
            $user,
        )
            ->with(['transportItems' => fn ($q) => $q->whereNotNull('transport_vendor_id')->latest('created_at')])
            ->orderBy('travel_date')
            ->limit($limit)
            ->get(['id', 'group_number', 'travel_date', 'transport_required', 'transport_mode', 'mandatory_transport_vendor_id']);

        $vendorIds = $groups
            ->map(fn (VisaGroup $group) => $this->assignedVendorId($group))
            ->filter()
            ->unique()
            ->values();

        $vendorNames = VisaVendor::whereIn('id', $vendorIds)->pluck('name', 'id');

        $rows = $groups->map(function (VisaGroup $group) use ($vendorNames, $company) {
            $vendorId = $this->assignedVendorId($group);

            $paid = $vendorId
                ? GroupPayment::where('company_id', $company->id)
                    ->where('visa_group_id', $group->id)
                    ->where('transport_vendor_id', $vendorId)
                    ->where('direction', GroupPayment::DIRECTION_SENT)
                    ->where('status', GroupPayment::STATUS_POSTED)
                    ->sum('base_amount')
                : 0.0;

            return [
                'id' => $group->id,
                'group_number' => $group->group_number,
                'travel_date' => optional($group->travel_date)->toDateString(),
                'transport_required' => (bool) $group->transport_required,
                'transport_mode' => $group->transport_mode,
                'vendor_assigned' => $vendorId !== null,
                'vendor_name' => $vendorId ? ($vendorNames[$vendorId] ?? null) : null,
                'paid' => (float) $paid > 0,
                'amount_paid' => (float) $paid,
            ];
        })->values()->all();

        return [
            'rows' => $rows,
        ];
    }

    private function assignedVendorId(VisaGroup $group): ?string
    {
        return $group->transportItems->first()?->transport_vendor_id
            ?? $group->mandatory_transport_vendor_id;
    }
}
