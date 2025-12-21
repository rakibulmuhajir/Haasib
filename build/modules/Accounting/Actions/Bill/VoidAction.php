<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\PostingService;
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

        if (round((float) $bill->paid_amount, 6) > 0.000001) {
            throw new \InvalidArgumentException('Cannot void a bill with payments. Void bill payments first.');
        }

        $transaction = null;
        if ($bill->transaction_id) {
            $transaction = Transaction::where('company_id', $company->id)
                ->where('id', $bill->transaction_id)
                ->whereNull('deleted_at')
                ->first();
        }

        if (! $transaction) {
            $transaction = Transaction::where('company_id', $company->id)
                ->where('reference_type', 'acct.bills')
                ->where('reference_id', $bill->id)
                ->whereNull('reversal_of_id')
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->first();

            if ($transaction && ! $bill->transaction_id) {
                $bill->transaction_id = $transaction->id;
            }
        }

        if ($transaction) {
            app(PostingService::class)->reverseTransaction($transaction, $params['reason'] ?? null);
        }

        // Reverse stock movements for inventory items (if module enabled)
        $this->reverseInventory($bill);

        $bill->status = 'void';
        $bill->voided_at = now();
        $bill->paid_at = null;
        $bill->balance = 0;
        $bill->internal_notes = trim(($bill->internal_notes ?? '') . PHP_EOL . ($params['reason'] ?? ''));
        $bill->updated_by_user_id = Auth::id();
        $bill->save();

        return ['message' => "Bill {$bill->bill_number} voided"];
    }

    /**
     * Reverse inventory movements if the inventory module is enabled.
     */
    protected function reverseInventory(Bill $bill): void
    {
        $company = $bill->company;
        if (! $company || ! $company->isModuleEnabled('inventory')) {
            return;
        }

        // Only load the service if the module is enabled
        $serviceClass = 'App\\Modules\\Inventory\\Services\\InventoryService';
        if (! class_exists($serviceClass)) {
            return;
        }

        app($serviceClass)->reverseFromBill($bill);
    }
}
