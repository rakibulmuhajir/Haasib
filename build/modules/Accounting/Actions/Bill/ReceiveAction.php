<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use Illuminate\Support\Facades\Auth;

class ReceiveAction implements PaletteAction
{
    public function rules(): array
    {
        return ['id' => 'required|string'];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);

        if ($bill->status === 'void' || $bill->status === 'cancelled') {
            throw new \InvalidArgumentException('Cannot receive a void/cancelled bill');
        }

        $bill->status = 'received';
        $bill->received_at = now();
        $bill->updated_by_user_id = Auth::id();
        $bill->save();

        return ['message' => "Bill {$bill->bill_number} marked received"];
    }
}
