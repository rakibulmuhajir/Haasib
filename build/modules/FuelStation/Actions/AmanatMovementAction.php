<?php

namespace App\Modules\FuelStation\Actions;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Services\AmanatService;

class AmanatMovementAction implements PaletteAction
{
    public function permission(): ?string { return Permissions::DAILY_CLOSE_CREATE; }
    public function rules(): array { return static::ruleSet(); }

    public static function ruleSet(): array
    {
        return ['customer_id' => 'required|uuid', 'kind' => 'required|in:deposit,withdraw', 'business_date' => 'required|date', 'amount' => 'required|numeric|min:0.01', 'reference' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:1000'];
    }
    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $customer = Customer::where('company_id', $company->id)->findOrFail($params['customer_id']);
        $record = app(AmanatService::class)->{$params['kind']}($customer, $params);
        return ['id' => $record->id];
    }
}
