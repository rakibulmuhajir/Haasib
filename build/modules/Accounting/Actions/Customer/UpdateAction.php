<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use Illuminate\Support\Facades\Auth;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'name' => 'nullable|string|min:1|max:255',
            'customer_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Customer::TYPES))],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'billing_contact' => 'nullable|string|max:150',
            'parent_customer_id' => 'nullable|uuid',
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
            'category_id' => 'nullable|uuid',
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

        if (isset($params['customer_type']) && $params['customer_type'] !== $customer->customer_type) {
            $updates['customer_type'] = $params['customer_type'];
            $changes[] = 'type → '.Customer::TYPES[$params['customer_type']];
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

        // Who invoices are addressed to at this customer (a person or an office); can be cleared.
        if (array_key_exists('billing_contact', $params)) {
            $updates['billing_contact'] = trim((string) $params['billing_contact']) ?: null;
            $changes[] = 'billing contact → '.($updates['billing_contact'] ?? 'removed');
        }

        // Part of a group (one level: a group is not part of another, a member has no members).
        if (array_key_exists('parent_customer_id', $params) && ($params['parent_customer_id'] ?: null) !== $customer->parent_customer_id) {
            $parentId = $params['parent_customer_id'] ?: null;
            if ($parentId) {
                $parent = Customer::where('company_id', $company->id)->find($parentId);
                if (! $parent || $parent->id === $customer->id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_customer_id' => 'Choose another customer of this company.']);
                }
                if ($parent->parent_customer_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_customer_id' => "{$parent->name} is itself part of a group."]);
                }
                if (Customer::where('company_id', $company->id)->where('parent_customer_id', $customer->id)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_customer_id' => 'This customer has its own members.']);
                }
            }
            $updates['parent_customer_id'] = $parentId;
            $changes[] = 'group → '.($parentId ? $parent->name : 'none');
        }

        if (array_key_exists('category_id', $params) && ($params['category_id'] ?: null) !== $customer->category_id) {
            $categoryId = $params['category_id'] ?: null;
            $category = $categoryId
                ? \App\Modules\Accounting\Models\CustomerCategory::where('company_id', $company->id)->find($categoryId)
                : null;
            if ($categoryId && ! $category) {
                throw \Illuminate\Validation\ValidationException::withMessages(['category_id' => 'Choose a category of this company.']);
            }
            $updates['category_id'] = $categoryId;
            $changes[] = 'category → '.($category?->name ?? 'none');
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
