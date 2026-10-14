<?php

namespace App\Modules\FuelStation\Actions;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Services\DailyCloseEntryService;

class DirectFuelSaleAction implements PaletteAction
{
    public function permission(): ?string
    {
        return Permissions::INVOICE_CREATE;
    }

    public function rules(): array
    {
        return ['item_id' => 'required|uuid', 'customer_id' => 'nullable|uuid', 'unit_id' => 'nullable|uuid',
            'reference' => 'nullable|string|max:100', 'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0.01', 'sale_date' => 'required|date|before_or_equal:today',
            'paid_in_cash' => 'required|boolean', 'temporary_storage' => 'nullable|boolean'];
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        return \App\Services\AccountingWriteTransaction::run(fn () => app(DailyCloseEntryService::class)->directSale(
            $company->id, $params['sale_date'], [...$params, 'litres' => $params['quantity'], 'rate' => $params['unit_price']], auth()->user()));
    }
}
