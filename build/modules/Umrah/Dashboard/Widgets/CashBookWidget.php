<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Services\TravelAccessService;

/**
 * "What's been happening" — the most recent posted cash movements.
 */
class CashBookWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.cash_book';
    }

    public function title(): string
    {
        return "What's been happening";
    }

    public function description(): string
    {
        return 'Recent posted payments, in and out.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_PAYMENT_VIEW;
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
        $limit = (int) ($options['limit'] ?? 7);
        $access = app(TravelAccessService::class);

        $base = $access->scopeAgentRecords(
            GroupPayment::where('company_id', $company->id)
                ->where('status', GroupPayment::STATUS_POSTED),
            $company->id,
            $user,
        );

        $totalMovements = (clone $base)->count();
        $totalIn = (float) (clone $base)->where('direction', GroupPayment::DIRECTION_RECEIVED)->sum('base_amount');
        $totalOut = (float) (clone $base)->where('direction', GroupPayment::DIRECTION_SENT)->sum('base_amount');

        $payments = (clone $base)
            ->with(['agent:id,name', 'group:id,group_number', 'visaVendor:id,name', 'transportVendor:id,name', 'hotelVendor:id,name'])
            ->orderByDesc('payment_date')
            ->limit($limit)
            ->get();

        return [
            'rows' => $payments->map(fn (GroupPayment $payment): array => [
                'id' => $payment->id,
                'payment_date' => optional($payment->payment_date)->toDateString(),
                'payment_number' => $payment->payment_number,
                'particulars' => $this->particulars($payment),
                'direction' => $payment->direction,
                'in' => $payment->direction === GroupPayment::DIRECTION_RECEIVED ? (float) $payment->base_amount : null,
                'out' => $payment->direction === GroupPayment::DIRECTION_SENT ? (float) $payment->base_amount : null,
            ])->values()->all(),
            'totals' => [
                'in' => $totalIn,
                'out' => $totalOut,
            ],
            'total_movements' => $totalMovements,
            'currency' => $company->base_currency,
        ];
    }

    private function particulars(GroupPayment $payment): string
    {
        $party = $payment->direction === GroupPayment::DIRECTION_RECEIVED
            ? $payment->agent?->name
            : ($payment->visaVendor?->name ?? $payment->transportVendor?->name ?? $payment->hotelVendor?->name);

        $groupNumber = $payment->group?->group_number;

        return collect([$party, $groupNumber])->filter()->implode(' — ');
    }
}
