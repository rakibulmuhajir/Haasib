<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use Carbon\Carbon;

/**
 * "What needs you" — an exceptions feed pulled from three independent
 * sources: groups travelling soon with money still owed, passengers not yet
 * visa-cleared on groups travelling soon, and payments awaiting an
 * accountant's rate + approval.
 */
class NeedsAttentionWidget implements DashboardWidget
{
    private const CAP = 6;

    public function key(): string
    {
        return 'umrah.needs_attention';
    }

    public function title(): string
    {
        return 'What needs you';
    }

    public function description(): string
    {
        return 'Groups, passengers and payments that need action.';
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
        $days = (int) ($options['days'] ?? 14);
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($days);
        $access = app(TravelAccessService::class);
        $agentId = $access->isAgentMember($company->id, $user)
            ? $access->linkedAgent($company->id, $user)?->id
            : null;
        $isAgent = $access->isAgentMember($company->id, $user);

        if ($isAgent && ! $agentId) {
            return ['items' => [], 'currency' => $company->base_currency];
        }

        $items = collect()
            ->concat($this->groupBalanceItems($company, $today, $horizon, $isAgent, $agentId))
            ->concat($this->passengerVisaItems($company, $today, $horizon, $isAgent, $agentId))
            ->concat($this->submittedPaymentItems($company, $isAgent, $agentId));

        $items = $items
            ->sort(fn (array $a, array $b) => $a['_rank'] <=> $b['_rank'] ?: $a['_urgency'] <=> $b['_urgency'])
            ->take(self::CAP)
            ->map(fn (array $item): array => collect($item)->except(['_rank', '_urgency'])->all())
            ->values()
            ->all();

        return ['items' => $items, 'currency' => $company->base_currency];
    }

    private function groupBalanceItems(Company $company, Carbon $today, Carbon $horizon, bool $isAgent, ?string $agentId): array
    {
        $groups = VisaGroup::where('company_id', $company->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->when($isAgent, fn ($q) => $q->where('agent_id', $agentId))
            ->whereNotNull('travel_date')
            ->whereDate('travel_date', '<=', $horizon)
            ->where('balance', '>', 0)
            ->get(['id', 'group_number', 'name', 'travel_date', 'balance']);

        return $groups->map(function (VisaGroup $group) use ($today, $company) {
            $daysUntil = $today->diffInDays(Carbon::parse($group->travel_date), false);
            [$severity, $chip] = $this->urgencyFromDays($daysUntil);

            return [
                'key' => 'group_balance:'.$group->id,
                'severity' => $severity,
                'chip' => $chip,
                'title' => $group->name.' ('.$group->group_number.')',
                'context' => 'Balance still outstanding before travel',
                'amount' => (float) $group->balance,
                'href' => route('umrah.groups.show', ['company' => $company->slug, 'group' => $group->id]),
                '_rank' => $this->severityRank($severity),
                '_urgency' => $daysUntil,
            ];
        })->all();
    }

    private function passengerVisaItems(Company $company, Carbon $today, Carbon $horizon, bool $isAgent, ?string $agentId): array
    {
        $passengers = Passenger::where('company_id', $company->id)
            ->whereNotIn('visa_status', [Passenger::STATUS_DELIVERED, Passenger::STATUS_REJECTED])
            ->whereHas('group', function ($query) use ($today, $horizon, $isAgent, $agentId) {
                $query->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                    ->when($isAgent, fn ($q) => $q->where('agent_id', $agentId))
                    ->whereNotNull('travel_date')
                    ->whereDate('travel_date', '<=', $horizon);
            })
            ->with('group:id,group_number,name,travel_date')
            ->get(['id', 'visa_group_id', 'full_name', 'visa_status']);

        return $passengers->filter(fn (Passenger $passenger) => $passenger->group !== null)
            ->map(function (Passenger $passenger) use ($today, $company) {
                $daysUntil = $today->diffInDays(Carbon::parse($passenger->group->travel_date), false);
                [$severity, $chip] = $this->urgencyFromDays($daysUntil);

                return [
                    'key' => 'passenger_visa:'.$passenger->id,
                    'severity' => $severity,
                    'chip' => $chip,
                    'title' => $passenger->full_name,
                    'context' => 'Visa '.(Passenger::STATUSES[$passenger->visa_status] ?? $passenger->visa_status).' — '.$passenger->group->group_number,
                    'amount' => null,
                    'href' => route('umrah.groups.show', ['company' => $company->slug, 'group' => $passenger->visa_group_id]),
                    '_rank' => $this->severityRank($severity),
                    '_urgency' => $daysUntil,
                ];
            })->values()->all();
    }

    private function submittedPaymentItems(Company $company, bool $isAgent, ?string $agentId): array
    {
        $payments = GroupPayment::where('company_id', $company->id)
            ->where('status', GroupPayment::STATUS_SUBMITTED)
            ->when($isAgent, fn ($q) => $q->where('agent_id', $agentId))
            ->with('agent:id,name')
            ->orderBy('submitted_at')
            ->get(['id', 'agent_id', 'payment_number', 'amount', 'currency', 'submitted_at']);

        return $payments->map(fn (GroupPayment $payment) => [
            'key' => 'payment_submitted:'.$payment->id,
            'severity' => 'attention',
            'chip' => 'awaiting approval',
            'title' => 'Payment '.($payment->payment_number ?? $payment->id).' needs a rate and approval',
            'context' => $payment->agent?->name ?? 'Unassigned agent',
            'amount' => (float) $payment->amount,
            'href' => route('umrah.payments.show', ['company' => $company->slug, 'payment' => $payment->id]),
            '_rank' => $this->severityRank('attention'),
            '_urgency' => $payment->submitted_at ? Carbon::parse($payment->submitted_at)->timestamp : 0,
        ])->all();
    }

    private function urgencyFromDays(int $daysUntil): array
    {
        if ($daysUntil < 0) {
            return ['critical', abs($daysUntil).' days late'];
        }

        if ($daysUntil === 0) {
            return ['critical', 'today'];
        }

        if ($daysUntil <= 3) {
            return ['critical', 'in '.$daysUntil.' days'];
        }

        if ($daysUntil <= 7) {
            return ['attention', 'in '.$daysUntil.' days'];
        }

        return ['info', 'in '.$daysUntil.' days'];
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 0,
            'attention' => 1,
            default => 2,
        };
    }
}
