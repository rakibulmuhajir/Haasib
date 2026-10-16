<?php

namespace App\Modules\Accounting\Actions\CreditNote;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteItem;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\GlPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
            'currency' => 'nullable|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'customer' => 'required|string|max:255',
            'invoice' => 'nullable|string|max:255',
            'credit_date' => 'nullable|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:draft,issued',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.line_number' => 'nullable|integer|min:1',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CREDIT_NOTE_UPDATE;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $company = CompanyContext::requireCompany();
        \App\Models\Company::whereKey($company->id)->lockForUpdate()->firstOrFail();

        // Get the credit note
        $creditNote = CreditNote::where('id', $params['id'])
            ->where('company_id', $company->id)
            ->firstOrFail();

        if ($creditNote->period_discount || $creditNote->transaction_id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['credit_note' => 'This credit is posted. Void it and create a replacement to change its value.']);
        }

        // Prevent updates to applied or voided credit notes
        if (in_array($creditNote->status, ['applied', 'void'])) {
            throw new \Exception('Cannot update '.$creditNote->status.' credit note.');
        }

        $customer = $this->resolveCustomer($params['customer'], $company->id);
        $invoice = ! empty($params['invoice'])
            ? $this->resolveInvoice($params['invoice'], $company->id)
            : null;

        if ($invoice) {
            app(\App\Modules\Accounting\Services\CustomerDiscountPolicy::class)->assertInvoiceNotCovered($company->id, $invoice->id);
        }

        if ($invoice && $invoice->customer_id !== $customer->id) {
            throw new \Exception('Invoice does not belong to the provided customer');
        }

        $creditDate = ! empty($params['credit_date'])
            ? Carbon::parse($params['credit_date'])
            : $creditNote->credit_date;

        $dateLock = app(DocumentDateLock::class);
        $oldCreditDate = $creditNote->credit_date->toDateString();
        $dateLock->assertOpen($company->id, $oldCreditDate, "Credit note {$creditNote->credit_note_number}");
        $newCreditDate = $creditDate->toDateString();
        if ($newCreditDate !== $oldCreditDate) {
            $dateLock->assertOpen($company->id, $newCreditDate, "Credit note {$creditNote->credit_note_number}");
        }

        $status = $params['status'] ?? $creditNote->status;

        $lineItems = app(\App\Modules\Accounting\Services\CompanyTax::class)->stripLines($params['line_items'], app(\App\Modules\Accounting\Services\CompanyTax::class)->allows($company, $creditNote->items()->sum('tax_amount')));
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $discountAmount = 0.0;
        foreach ($lineItems as $idx => $item) {
            $qty = (float) $item['quantity'];
            $unit = (float) $item['unit_price'];
            $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
            $discountRate = isset($item['discount_rate']) ? (float) $item['discount_rate'] : 0.0;

            $lineTotal = $qty * $unit;
            $lineDiscount = $lineTotal * ($discountRate / 100);
            $lineTaxable = $lineTotal - $lineDiscount;
            $lineTax = $lineTaxable * ($taxRate / 100);
            $lineGrand = $lineTaxable + $lineTax;

            $subtotal += $lineTotal;
            $taxAmount += $lineTax;
            $discountAmount += $lineDiscount;

            $lineItems[$idx]['_line_total'] = $lineTotal;
            $lineItems[$idx]['_tax_amount'] = $lineTax;
            $lineItems[$idx]['_total'] = $lineGrand;
        }

        $amount = $subtotal + $taxAmount - $discountAmount;
        if ($amount <= 0) {
            throw new \Exception('Credit note amount must be greater than zero');
        }

        $currencyFields = app(\App\Modules\Accounting\Services\CreditNoteCurrency::class)->resolve($company, $invoice, $params, $amount);

        return DB::transaction(function () use ($company, $creditNote, $customer, $invoice, $creditDate, $status, $amount, $currencyFields, $params, $lineItems) {
            // Force delete existing line items first to avoid any race conditions
            CreditNoteItem::where('credit_note_id', $creditNote->id)->forceDelete();

            // Update credit note
            $creditNote->update([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice?->id,
                'credit_date' => $creditDate,
                'amount' => $amount,
                ...$currencyFields,
                'reason' => $params['reason'],
                'status' => $status,
                'notes' => $params['notes'] ?? null,
                'updated_by_user_id' => Auth::id(),
            ]);

            // Create new line items with explicit sequential line numbers starting from 1
            $newItems = [];
            foreach ($lineItems as $idx => $item) {
                $lineNumber = ($idx + 1); // Force sequential line numbers starting from 1
                $newItems[] = [
                    'id' => Str::orderedUuid(),
                    'company_id' => $company->id,
                    'credit_note_id' => $creditNote->id,
                    'line_number' => $lineNumber,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'discount_rate' => $item['discount_rate'] ?? 0,
                    'line_total' => $item['_line_total'],
                    'tax_amount' => $item['_tax_amount'],
                    'total' => $item['_total'],
                    'created_by_user_id' => Auth::id(),
                    'updated_by_user_id' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert to avoid any potential issues
            if (! empty($newItems)) {
                CreditNoteItem::insert($newItems);
            }

            if ($status === 'issued' && ! $creditNote->transaction_id) {
                $transaction = app(GlPostingService::class)->postCreditNote($creditNote);
                $creditNote->transaction_id = $transaction->id;
                $creditNote->save();
            }

            return [
                'message' => "Credit note {$creditNote->credit_note_number} updated ({$status})",
                'data' => [
                    'id' => $creditNote->id,
                    'number' => $creditNote->credit_note_number,
                    'amount' => $amount,
                    'status' => $status,
                ],
                'redirect' => "/{$company->slug}/credit-notes/{$creditNote->id}",
            ];
        });
    }

    private function resolveCustomer(string $identifier, string $companyId): Customer
    {
        if (Str::isUuid($identifier)) {
            $customer = Customer::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($customer) {
                return $customer;
            }
        }

        $customer = Customer::where('company_id', $companyId)
            ->where('customer_number', $identifier)
            ->first();
        if ($customer) {
            return $customer;
        }

        $customer = Customer::where('company_id', $companyId)
            ->where('email', $identifier)
            ->first();
        if ($customer) {
            return $customer;
        }

        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->first();
        if ($customer) {
            return $customer;
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer not found: {$identifier}");
    }

    private function resolveInvoice(string $identifier, string $companyId): Invoice
    {
        if (Str::isUuid($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($invoice) {
                return $invoice;
            }
        }

        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', $identifier)
            ->first();
        if ($invoice) {
            return $invoice;
        }

        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', "%{$identifier}")
            ->first();
        if ($invoice) {
            return $invoice;
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Invoice not found: {$identifier}");
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
