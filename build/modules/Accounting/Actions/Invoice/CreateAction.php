<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Models\Invoice;
use App\Modules\Accounting\Domain\Customers\Models\Customer;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'customer' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'due' => 'nullable|date|after_or_equal:today',
            'description' => 'nullable|string|max:1000',
            'draft' => 'nullable|boolean',
            'reference' => 'nullable|string|max:100',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        // Resolve customer (UUID, email, or fuzzy name match)
        $customer = $this->resolveCustomer($params['customer'], $company->id);

        return DB::transaction(function () use ($params, $company, $customer) {
            // Calculate due date
            $issueDate = now();
            $dueDate = !empty($params['due'])
                ? Carbon::parse($params['due'])
                : $issueDate->copy()->addDays(30); // Default 30 days

            // Generate invoice number using existing model method
            $invoiceNumber = Invoice::generateInvoiceNumber($company->id);

            // Determine status
            $status = ($params['draft'] ?? false)
                ? 'draft'
                : 'sent'; // Use 'sent' instead of 'pending' to match existing flow

            // Create invoice
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'reference' => $params['reference'] ?? null,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $params['amount'],
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $params['amount'],
                'balance_due' => $params['amount'],
                'currency' => $customer->currency,
                'exchange_rate' => 1.0,
                'status' => $status,
                'payment_status' => $status === 'draft' ? 'draft' : 'unpaid',
                'notes' => $params['description'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

            // Create line item if description provided
            if (!empty($params['description'])) {
                $invoice->lineItems()->create([
                    'description' => $params['description'],
                    'quantity' => 1,
                    'unit_price' => $params['amount'],
                    'tax_rate_id' => null,
                    'discount_percent' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total' => $params['amount'],
                    'sort_order' => 0,
                ]);
            }

            $statusLabel = $status === 'draft' ? 'Draft' : 'Pending';

            return [
                'message' => "Invoice {$invoiceNumber} created ({$statusLabel}) for {$customer->name}",
                'data' => [
                    'id' => $invoice->id,
                    'number' => $invoiceNumber,
                    'customer' => $customer->name,
                    'total' => PaletteFormatter::money($invoice->total_amount, $invoice->currency),
                    'due_date' => $dueDate->format('M j, Y'),
                    'status' => $status,
                ],
                'redirect' => "/{$company->slug}/invoices/{$invoice->id}",
            ];
        });
    }

    private function resolveCustomer(string $identifier, string $companyId): Customer
    {
        // Try UUID
        if (Str::isUuid($identifier)) {
            $customer = Customer::where('id', $identifier)
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->first();
            if ($customer) return $customer;
        }

        // Try exact customer number
        $customer = Customer::where('company_id', $companyId)
            ->where('customer_number', $identifier)
            ->where('status', 'active')
            ->first();
        if ($customer) return $customer;

        // Try exact email
        $customer = Customer::where('company_id', $companyId)
            ->where('email', $identifier)
            ->where('status', 'active')
            ->first();
        if ($customer) return $customer;

        // Try exact name (case-insensitive)
        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->where('status', 'active')
            ->first();
        if ($customer) return $customer;

        // Try fuzzy match
        $customer = Customer::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
        if ($customer) return $customer;

        throw new \Exception("Customer not found: {$identifier}. Create with: customer create \"{$identifier}\"");
    }
}