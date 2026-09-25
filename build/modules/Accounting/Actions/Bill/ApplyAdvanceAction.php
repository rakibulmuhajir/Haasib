<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Services\VendorAdvanceService;
use App\Support\PaletteFormatter;

/**
 * Manual "Apply advance" from a bill's page: pulls from the vendor's existing unapplied
 * bill-payment balance (see VendorAdvanceService) for a bill that already existed before
 * the advance was recorded -- Bill\CreateAction and Bill\ReceiveAction already do this
 * automatically the moment a new bill becomes payable, so this is only needed for the
 * older-bill case.
 */
class ApplyAdvanceAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'amount' => 'nullable|numeric|min:0.01',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_PAY;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);

        if (in_array($bill->status, ['void', 'cancelled', 'draft'], true)) {
            throw new \InvalidArgumentException('Cannot apply an advance to this bill.');
        }

        $result = app(VendorAdvanceService::class)->applyToBill($bill, isset($params['amount']) ? (float) $params['amount'] : null);

        if ($result['applied'] <= 0.000001) {
            throw new \InvalidArgumentException('This vendor has no advance available to apply.');
        }

        return [
            'message' => 'Applied ' . PaletteFormatter::money($result['applied'], $bill->currency) . " of advance to {$bill->bill_number}",
            'data' => ['id' => $bill->id, 'applied' => $result['applied']],
        ];
    }
}
