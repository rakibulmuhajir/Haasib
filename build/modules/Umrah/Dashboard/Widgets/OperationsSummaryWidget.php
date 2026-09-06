<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Services\OperationalEventTimelineService;
use Carbon\CarbonImmutable;

class OperationsSummaryWidget implements DashboardWidget
{
    private const SUMMARY_KEYS = [
        'moving_in',
        'moving_out',
        'makkah_to_madinah',
        'madinah_to_makkah',
    ];

    public function key(): string
    {
        return 'umrah.operations_summary';
    }

    public function title(): string
    {
        return "Today's movements";
    }

    public function description(): string
    {
        return 'People moving into Saudi Arabia, leaving, or travelling between the holy cities.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_OPERATIONS_VIEW;
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
        $date = CarbonImmutable::now((string) config('umrah.operations.operational_timezone', 'Asia/Riyadh'))
            ->toDateString();
        $filters = [
            'period' => 'today',
            'date' => $date,
            'event_type' => 'all',
            'readiness' => 'all',
        ];
        $timeline = app(OperationalEventTimelineService::class)->build($company, $user, $filters);

        $summary = collect($timeline['summary'])
            ->whereIn('key', self::SUMMARY_KEYS)
            ->map(function (array $item) use ($company, $date): array {
                $item['href'] = route('umrah.operations.index', [
                    'company' => $company->slug,
                    'period' => 'today',
                    'date' => $date,
                    'event_type' => $item['event_type'] ?? 'all',
                    'readiness' => $item['readiness'] ?? 'all',
                ]);

                return $item;
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'footer_label' => 'Open full Operations',
            'footer_href' => route('umrah.operations.index', [
                'company' => $company->slug,
                'period' => 'today',
                'date' => $date,
            ]),
        ];
    }
}
