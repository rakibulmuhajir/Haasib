<?php

namespace App\Modules\FuelStation\Actions\CardCharge;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Models\CardCharge;
use App\Modules\FuelStation\Services\CardChargeService;

/** Reverse a card charge on its own date; the row stays, marked undone. */
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
        $charge = CardCharge::where('company_id', $company->id)->findOrFail($params['id']);
        app(CardChargeService::class)->undo($company, $charge, auth()->user());

        return ['message' => 'Card charge undone', 'data' => ['id' => $charge->id]];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
