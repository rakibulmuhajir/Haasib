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
 * "Most urgent groups" — ranked by what actually needs doing, not by date alone.
 *
 * Ordering by travel_date over future groups only is the obvious reading and
 * the wrong one: it empties the widget the moment a company has nothing booked
 * ahead, while groups that have already flown and still owe money -- the ones
 * with real exposure -- drop off the page entirely.
 *
 * So urgency is two tiers. Groups yet to travel come first, soonest to latest,
 * because they can still be prepared for. Behind them come groups that have
 * travelled and still carry a balance, most recent first, because that is money
 * to chase. A group that has travelled and settled is not urgent and is not
 * shown -- there is nothing left to do about it.
 */
class DeparturesWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.departures';
    }

    public function title(): string
    {
        return 'Most urgent groups';
    }

    public function description(): string
    {
        return 'Groups yet to travel, then those that have travelled and still owe.';
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

    /**
     * The chip's words live here, not in the template.
     *
     * A negative day count rendered as "-30d" is arithmetic leaking into the
     * interface. Naming the two directions differently is also the non-colour
     * indicator the grammar requires -- "in 6 days" and "30 days ago" stay
     * distinguishable without relying on the chip's tone.
     */
    private function chip(int $daysUntil): string
    {
        return match (true) {
            $daysUntil < 0 => abs($daysUntil).' days ago',
            $daysUntil === 0 => 'today',
            $daysUntil === 1 => 'tomorrow',
            default => 'in '.$daysUntil.' days',
        };
    }

    public function resolve(Company $company, User $user, array $options): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $today = Carbon::today();
        $access = app(TravelAccessService::class);

        $groups = $access->scopeAgentRecords(
            VisaGroup::where('company_id', $company->id)
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->whereNotNull('travel_date')
                ->where(function ($query) use ($today): void {
                    $query->whereDate('travel_date', '>=', $today)
                        ->orWhere(function ($past) use ($today): void {
                            $past->whereDate('travel_date', '<', $today)
                                ->where('balance', '>', 0);
                        });
                }),
            $company->id,
            $user,
        )
            ->with('agent:id,name')
            ->orderByRaw('CASE WHEN travel_date >= ? THEN 0 ELSE 1 END', [$today->toDateString()])
            ->orderByRaw('CASE WHEN travel_date >= ? THEN travel_date END ASC NULLS LAST', [$today->toDateString()])
            ->orderByRaw('CASE WHEN travel_date <  ? THEN travel_date END DESC NULLS LAST', [$today->toDateString()])
            ->limit($limit)
            ->get(['id', 'agent_id', 'group_number', 'name', 'travel_date', 'passenger_count', 'balance']);

        return [
            'rows' => $groups->map(fn (VisaGroup $group): array => [
                'id' => $group->id,
                'group_number' => $group->group_number,
                'name' => $group->name,
                'travel_date' => optional($group->travel_date)->toDateString(),
                'days_until' => $group->travel_date ? (int) $today->diffInDays(Carbon::parse($group->travel_date), false) : null,
                'chip' => $group->travel_date ? $this->chip((int) $today->diffInDays(Carbon::parse($group->travel_date), false)) : null,
                'agent_name' => $group->agent?->name,
                'passenger_count' => $group->passenger_count,
                'balance' => (float) $group->balance,
            ])->values()->all(),
            'currency' => $company->base_currency,
        ];
    }
}
