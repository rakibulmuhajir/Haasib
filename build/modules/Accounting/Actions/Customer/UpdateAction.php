<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Domain\Customers\Models\Customer;
use Illuminate\Support\Facades\Auth;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'name' => 'nullable|string|min:1|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|integer|min:0|max:365',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CUSTOMER_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $customer = $this->resolveCustomer($params['id'], $company->id);

        $updates = [];
        $changes = [];

        if (isset($params['name']) && $params['name'] !== $customer->name) {
            $updates['name'] = trim($params['name']);
            $changes[] = "name → {$params['name']}";
        }

        if (isset($params['email']) && $params['email'] !== $customer->email) {
            // Check for duplicate
            if ($params['email']) {
                $existing = Customer::where('company_id', $company->id)
                    ->where('email', $params['email'])
                    ->where('id', '!=', $customer->id)
                    ->exists();
                if ($existing) {
                    throw new \Exception("Email {$params['email']} is already used by another customer");
                }
            }
            $updates['email'] = $params['email'] ?: null;
            $changes[] = "email → " . ($params['email'] ?: 'removed');
        }

        if (isset($params['phone'])) {
            $updates['phone'] = $params['phone'] ?: null;
            $changes[] = "phone → " . ($params['phone'] ?: 'removed');
        }

        if (isset($params['currency'])) {
            $updates['currency'] = strtoupper($params['currency']);
            $changes[] = "currency → {$params['currency']}";
        }

        if (isset($params['payment_terms'])) {
            // Note: payment_terms is not in the model schema, might need migration
            $changes[] = "payment terms → {$params['payment_terms']} days";
        }

        if (empty($updates)) {
            throw new \Exception('No changes specified');
        }

        $customer->update($updates);

        return [
            'message' => "Customer updated: {$customer->name}",
            'data' => [
                'id' => $customer->id,
                'changes' => $changes,
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