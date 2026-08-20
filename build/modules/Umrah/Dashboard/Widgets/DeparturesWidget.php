<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use Carbon\Carbon;

/**
 * "Nearest departures" — upcoming visa groups ordered by how soon they travel.
 */
class DeparturesWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.departures';
    }

    public function title(): string
    {
        return 'Nearest departures';
    }

    public function description(): string
    {
        return 'Upcoming groups ordered by travel date.';
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
            ->with('agent:id,name')
            ->orderBy('travel_date')
            ->limit($limit)
            ->get(['id', 'agent_id', 'group_number', 'name', 'travel_date', 'passenger_count', 'balance']);

        return [
            'rows' => $groups->map(fn (VisaGroup $group): array => [
                'id' => $group->id,
                'group_number' => $group->group_number,
                'name' => $group->name,
                'travel_date' => optional($group->travel_date)->toDateString(),
                'days_until' => $group->travel_date ? $today->diffInDays(Carbon::parse($group->travel_date), false) : null,
                'agent_name' => $group->agent?->name,
                'passenger_count' => $group->passenger_count,
                'balance' => (float) $group->balance,
            ])->values()->all(),
            'currency' => $company->base_currency,
        ];
    }
}
