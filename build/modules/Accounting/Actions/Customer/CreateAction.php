<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Support\PaletteFormatter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:255',
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
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CUSTOMER_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        // Check for duplicate email within company (if email provided)
        if (!empty($params['email'])) {
            $existing = Customer::where('company_id', $company->id)
                ->where('email', $params['email'])
                ->exists();

            if ($existing) {
                throw new \Exception("Customer with email {$params['email']} already exists");
            }
        }

        $customer = Customer::create([
            'company_id' => $company->id,
            'customer_number' => $this->generateCustomerNumber($company->id),
            'name' => trim($params['name']),
            'email' => $params['email'] ?? null,
            'phone' => $params['phone'] ?? null,
            'billing_address' => $params['billing_address'] ?? null,
            'shipping_address' => $params['shipping_address'] ?? null,
            'tax_id' => $params['tax_id'] ?? null,
            'base_currency' => strtoupper($params['base_currency'] ?? $company->base_currency ?? 'USD'),
            'payment_terms' => $params['payment_terms'] ?? 30,
            'credit_limit' => $params['credit_limit'] ?? null,
            'notes' => $params['notes'] ?? null,
            'logo_url' => $params['logo_url'] ?? null,
            'is_active' => true,
            'created_by_user_id' => Auth::id(),
        ]);

        return [
            'message' => "Customer created: {$customer->name}",
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'currency' => $customer->base_currency,
            ],
        ];
    }

    private function generateCustomerNumber(string $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $lastNumber = Customer::where('company_id', $companyId)
                ->whereNotNull('customer_number')
                ->lockForUpdate()
                ->orderByDesc('customer_number')
                ->value('customer_number');

            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            } else {
                $sequence = 1;
            }

            return 'CUST-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
