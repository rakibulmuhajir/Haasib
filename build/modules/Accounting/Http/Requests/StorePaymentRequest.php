<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        // The UI uses a sentinel for the selectable "company default" option.
        // Convert it before UUID validation so the action can resolve the
        // customer's configured AR account.
        if ($this->input('ar_account_id') === 'company_default') {
            $this->merge(['ar_account_id' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        // Rule::exists()/'exists:' must be given the model class, never the bare
        // 'acct.xxx' table string: Laravel's exists/unique rule splits a dotted table
        // name on its FIRST dot into connection + table, and config/database.php
        // defines a connection literally called "acct" -- so 'acct.customers' was read
        // as connection "acct", table "customers", running the check on a second
        // Postgres session that carries neither this request's RLS context (current
        // company/is_super_admin) nor its transaction. Every row-scoped exists check
        // below silently found nothing and failed, invisibly, because nothing exercised
        // this FormRequest over HTTP until PaymentAllocationTest's HTTP-level tests did.
        // Passing the model class instead makes parseTable() read the table and
        // connection off it, the same fix StoreVendorRequest already applies to
        // ap_account_id.
        return [
            'customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')],
            'invoice_id' => ['nullable', 'uuid', Rule::exists(Invoice::class, 'id')],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['uuid', Rule::exists(Invoice::class, 'id')],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', 'uuid', Rule::exists(Invoice::class, 'id')],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_charge' => ['nullable', 'numeric', 'min:0'],
            // Optional: the form only shows a picker when the company uses more than one
            // currency (see CompanyCurrencyOptions). Left blank, Payment\CreateAction
            // defaults to the allocated invoice's currency, then the buyer's/company's
            // base currency - which is also what amanat always posts in (see
            // AmanatService::deposit).
            'currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            // Optional: the form only shows a picker once a deposit account is chosen, and
            // even then defaults it from that account's subtype. Left blank,
            // PaymentController::store() derives cash/bank_transfer from the deposit
            // account itself.
            'payment_method' => ['nullable', 'string', 'in:cash,bank_transfer,card,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'received_as' => ['nullable', 'in:invoices,amanat'],
            'deposit_account_id' => [
                'required',
                'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)),
            ],
            'ar_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_receivable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
