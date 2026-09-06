<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MovementReportService
{
    public function __construct(private OperationalEventTimelineService $events) {}

    /**
     * Present the exact Operations projection as a printable movement report.
     *
     * @return array<string, mixed>
     */
    public function build(Company $company, ?User $user, array $filters): array
    {
        $timeline = $this->events->build($company, $user, $filters);
        $events = collect($timeline['events']);

        return [
            ...$timeline,
            'title' => 'Movement Report',
            'description' => $timeline['shows_details']
                ? 'Flights, stays, road movements and passenger manifests.'
                : 'Movement totals for the selected operational window.',
            'generated_at' => now()->format('d M Y, g:i A'),
            'agent_summary' => $timeline['shows_details']
                ? $this->partySummary($events, 'agent', 'Unassigned agent')
                : [],
            'group_summary' => $timeline['shows_details']
                ? $this->partySummary($events, 'group', 'Ungrouped')
                : [],
        ];
    }

    /**
     * @return array<int, array{label: string, event_count: int, passenger_movements: int}>
     */
    private function partySummary(Collection $events, string $key, string $fallback): array
    {
        return $events
            ->groupBy(fn (array $event): string => (string) ($event[$key]['id'] ?? "__{$key}_unassigned__"))
            ->map(function (Collection $partyEvents) use ($key, $fallback): array {
                $party = $partyEvents->first()[$key] ?? null;

                return [
                    'label' => $party['name'] ?? $party['number'] ?? $fallback,
                    'event_count' => $partyEvents->count(),
                    'passenger_movements' => (int) $partyEvents->sum('passenger_count'),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function filename(array $report): string
    {
        $filters = $report['filters'];
        $period = $filters['period'] === 'custom'
            ? "{$filters['start']}-to-{$filters['end']}"
            : "{$filters['period']}-{$filters['date']}";

        return 'movement-report-'.Str::slug($period).'.pdf';
    }
}
