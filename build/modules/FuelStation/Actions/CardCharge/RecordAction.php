<?php

namespace App\Modules\FuelStation\Actions\CardCharge;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Services\CardChargeService;

/** A card charge the bank or card supplier kept, entered on any date; see CardChargeService. */
class RecordAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function permission(): ?string
    {
        return Permissions::PAYMENT_CREATE;
    }

    public function rules(): array
    {
        return [
            'channel_code' => 'required|string|max:50',
            'charge_date' => 'required|date',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date|after_or_equal:period_from',
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $charge = app(CardChargeService::class)->record($company, $params, auth()->user());

        return ['message' => 'Card charge recorded', 'data' => ['id' => $charge->id]];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
