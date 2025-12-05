<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Domain\Customers\Models\Customer;

class RestoreAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CUSTOMER_DELETE; // Use delete permission for restore
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        // Search including inactive
        $customer = Customer::where('company_id', $company->id)
            ->where('is_active', false)
            ->where(function ($q) use ($params) {
                $q->where('id', $params['id'])
                  ->orWhere('customer_number', $params['id'])
                  ->orWhere('email', $params['id'])
                  ->orWhereRaw('LOWER(name) = ?', [strtolower($params['id'])]);
            })
            ->firstOrFail();

        $customer->update(['is_active' => true]);

        return [
            'message' => "Customer restored: {$customer->name}",
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
        ];
    }
}
