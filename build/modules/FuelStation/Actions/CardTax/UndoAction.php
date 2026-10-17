<?php

namespace App\Modules\FuelStation\Actions\CardTax;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Models\CardTaxDeduction;
use App\Modules\FuelStation\Services\CardTaxService;

/** Reverse a card tax deduction; the row stays, marked undone. */
class UndoAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function permission(): ?string
    {
        return Permissions::PAYMENT_CREATE;
    }

    public function rules(): array
    {
        return ['id' => 'required|uuid'];
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $deduction = CardTaxDeduction::where('company_id', $company->id)->findOrFail($params['id']);
        app(CardTaxService::class)->undo($company, $deduction, auth()->user());

        return ['message' => 'Card tax undone', 'data' => ['id' => $deduction->id]];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
