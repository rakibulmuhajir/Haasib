<?php

namespace App\Modules\FuelStation\Actions\CardTax;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Services\CardTaxService;

/** Tax a card company deducted from card sales, entered on any date; see CardTaxService. */
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
            'deduction_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'section' => 'nullable|string|max:30',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $deduction = app(CardTaxService::class)->record($company, $params, auth()->user());

        return ['message' => 'Card tax recorded', 'data' => ['id' => $deduction->id]];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
