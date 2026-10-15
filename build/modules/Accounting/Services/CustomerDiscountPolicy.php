<?php

namespace App\Modules\Accounting\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use Illuminate\Validation\ValidationException;

class CustomerDiscountPolicy
{
    public function describe(Customer $customer, User $user): array
    {
        return ['override' => $customer->discount_timing, 'effective' => $this->timing($customer),
            'company_default' => Company::whereKey($customer->company_id)->value('settings')['customer_discount_timing'] ?? 'invoice',
            'can_update' => $user->hasCompanyPermission(Permissions::CUSTOMER_UPDATE),
            'can_create' => $user->hasCompanyPermission(Permissions::CREDIT_NOTE_CREATE) && $user->hasCompanyPermission(Permissions::CREDIT_NOTE_APPLY)];
    }

    public function timing(Customer $customer): string
    {
        return $customer->discount_timing
            ?? (Company::whereKey($customer->company_id)->value('settings')['customer_discount_timing'] ?? 'invoice');
    }

    /** Filter the fuel rates sent to previews by the same policy used at posting. */
    public function invoiceCustomerIds(string $companyId): array
    {
        $default = Company::whereKey($companyId)->value('settings')['customer_discount_timing'] ?? 'invoice';

        return Customer::where('company_id', $companyId)
            ->where(fn ($q) => $q->where('discount_timing', 'invoice')
                ->when($default === 'invoice', fn ($q) => $q->orWhereNull('discount_timing')))
            ->pluck('id')->all();
    }

    public function assertInvoiceNotCovered(string $companyId, string $invoiceId): void
    {
        $credit = CreditNote::where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->whereNotNull('period_discount')
            ->whereJsonContains('period_discount->invoice_ids', $invoiceId)->first();
        if ($credit) {
            throw ValidationException::withMessages(['invoice' => "This sale is covered by period discount {$credit->credit_note_number}. Void that discount first."]);
        }
    }
}
