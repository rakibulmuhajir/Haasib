<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Domain\Customers\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CUSTOMER_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $customer = $this->resolveCustomer($params['id'], $company->id);

        // Check for unpaid invoices
        $unpaidCount = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent', 'viewed', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->count();

        if ($unpaidCount > 0) {
            throw new \Exception(
                "Cannot delete customer with {$unpaidCount} unpaid invoice(s). " .
                "Void or collect payment first."
            );
        }

        // Check for active credit notes
        $creditNoteCount = DB::table('acct.credit_notes')
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->count();

        if ($creditNoteCount > 0) {
            throw new \Exception(
                "Cannot delete customer with {$creditNoteCount} active credit note(s). " .
                "Void credit notes first."
            );
        }

        // Soft delete (deactivate)
        $customer->update(['is_active' => false]);

        return [
            'message' => "Customer deleted: {$customer->name}",
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
        ];
    }

    private function resolveCustomer(string $identifier, string $companyId): Customer
    {
        // Try UUID
        if (\Illuminate\Support\Str::isUuid($identifier)) {
            $customer = Customer::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($customer) return $customer;
        }

        // Try exact customer number
        $customer = Customer::where('company_id', $companyId)
            ->where('customer_number', $identifier)
            ->first();
        if ($customer) return $customer;

        // Try exact email
        $customer = Customer::where('company_id', $companyId)
            ->where('email', $identifier)
            ->first();
        if ($customer) return $customer;

        // Try exact name (case-insensitive)
        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->first();
        if ($customer) return $customer;

        // Try fuzzy name match (requires pg_trgm extension)
        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
        if ($customer) return $customer;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer not found: {$identifier}");
    }
}
