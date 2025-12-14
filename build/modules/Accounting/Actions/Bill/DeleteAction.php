<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return ['id' => 'required|string'];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);

        if (($bill->paid_amount ?? 0) > 0) {
            throw new \InvalidArgumentException('Cannot delete bill with payments');
        }

        $bill->delete();

        return ['message' => "Bill {$bill->bill_number} deleted"];
    }
}
