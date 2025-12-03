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
            'currency' => 'nullable|string|size:3|uppercase',
            'payment_terms' => 'nullable|integer|min:0|max:365',
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
            'base_currency' => strtoupper($params['currency'] ?? $company->base_currency),
            'payment_terms' => $params['payment_terms'] ?? 30,
            'status' => 'active',
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
