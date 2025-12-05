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
            'base_currency' => 'nullable|string|size:3|uppercase',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'tax_id' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'billing_address' => 'nullable|array',
            'billing_address.street' => 'nullable|string|max:255',
            'billing_address.city' => 'nullable|string|max:100',
            'billing_address.state' => 'nullable|string|max:100',
            'billing_address.zip' => 'nullable|string|max:20',
            'billing_address.country' => 'nullable|string|max:2',
            'shipping_address' => 'nullable|array',
            'shipping_address.street' => 'nullable|string|max:255',
            'shipping_address.city' => 'nullable|string|max:100',
            'shipping_address.state' => 'nullable|string|max:100',
            'shipping_address.zip' => 'nullable|string|max:20',
            'shipping_address.country' => 'nullable|string|max:2',
            'logo_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
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

        if (isset($params['base_currency'])) {
            $updates['base_currency'] = strtoupper($params['base_currency']);
            $changes[] = "base_currency → {$updates['base_currency']}";
        }

        if (isset($params['payment_terms'])) {
            $updates['payment_terms'] = (int) $params['payment_terms'];
            $changes[] = "payment terms → {$params['payment_terms']} days";
        }

        if (array_key_exists('tax_id', $params)) {
            $updates['tax_id'] = $params['tax_id'] ?: null;
            $changes[] = "tax_id → " . ($params['tax_id'] ?: 'removed');
        }

        if (array_key_exists('credit_limit', $params)) {
            $updates['credit_limit'] = $params['credit_limit'] === null ? null : $params['credit_limit'];
            $changes[] = "credit_limit → " . ($params['credit_limit'] ?? 'removed');
        }

        if (array_key_exists('notes', $params)) {
            $updates['notes'] = $params['notes'] ?? null;
            $changes[] = "notes → " . ($params['notes'] ?? 'removed');
        }

        if (array_key_exists('billing_address', $params)) {
            $updates['billing_address'] = $params['billing_address'] ?? null;
            $changes[] = "billing_address updated";
        }

        if (array_key_exists('shipping_address', $params)) {
            $updates['shipping_address'] = $params['shipping_address'] ?? null;
            $changes[] = "shipping_address updated";
        }

        if (array_key_exists('logo_url', $params)) {
            $updates['logo_url'] = $params['logo_url'] ?? null;
            $changes[] = "logo_url updated";
        }

        if (array_key_exists('is_active', $params)) {
            $updates['is_active'] = (bool) $params['is_active'];
            $changes[] = "status → " . ($updates['is_active'] ? 'active' : 'inactive');
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
