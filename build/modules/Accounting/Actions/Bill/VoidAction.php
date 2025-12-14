<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use Illuminate\Support\Facades\Auth;

class VoidAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'reason' => 'nullable|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_VOID;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);

        if (in_array($bill->status, ['void', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Bill already void/cancelled');
        }

        $bill->status = 'void';
        $bill->voided_at = now();
        $bill->internal_notes = trim(($bill->internal_notes ?? '') . PHP_EOL . ($params['reason'] ?? ''));
        $bill->updated_by_user_id = Auth::id();
        $bill->save();

        return ['message' => "Bill {$bill->bill_number} voided"];
    }
}
