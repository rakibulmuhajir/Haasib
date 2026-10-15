<?php

namespace App\Modules\FuelStation\Actions;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Services\AmanatService;

class AmanatMovementAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function permission(): ?string { return Permissions::DAILY_CLOSE_CREATE; }
    public function rules(): array { return static::ruleSet(); }

    public static function ruleSet(): array
    {
        return ['customer_id' => 'required|uuid', 'kind' => 'required|in:deposit,withdraw', 'business_date' => 'required|date', 'amount' => 'required|numeric|min:0.01', 'payment_account_id' => 'nullable|string', 'reference' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:1000'];
    }
    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $customer = Customer::where('company_id', $company->id)->findOrFail($params['customer_id']);
        $record = app(AmanatService::class)->{$params['kind']}($customer, $params);
        return ['id' => $record->id];
    }

    public function palette(): array
    {
        return [
            'description' => 'Amanat deposit or withdrawal',
            'fields' => [
                'customer_id' => ['inputType' => 'search', 'searchEntity' => 'customer', 'hint' => 'Customer'],
                'business_date' => ['hint' => 'Date'],
                'amount' => ['hint' => 'Amount'],
                'payment_account_id' => ['inputType' => 'search', 'searchEntity' => 'account', 'hint' => 'Cash or bank account'],
            ],
        ];
    }
}
