<?php

namespace App\Modules\Accounting\Actions\Vendor;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Vendor;
use Illuminate\Support\Facades\Auth;

class UpdateAction implements PaletteAction
{
    use ResolvesVendors;

    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'vendor_number' => 'nullable|string|max:50',
            'name' => 'nullable|string|min:1|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'vendor_type' => 'nullable|string|in:'.implode(',', array_keys(Vendor::TYPES)),
            'address' => 'nullable|array',
            'address.street' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.zip' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:2',
            'tax_id' => 'nullable|string|max:100',
            'base_currency' => 'nullable|string|size:3|uppercase',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'account_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'website' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'ap_account_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::VENDOR_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $vendor = $this->resolveVendor($params['id'], $company->id);

        $updates = [];
        $changes = [];

        if (isset($params['name']) && $params['name'] !== $vendor->name) {
            $updates['name'] = trim($params['name']);
            $changes[] = "name → {$updates['name']}";
        }

        if (isset($params['email']) && $params['email'] !== $vendor->email) {
            if ($params['email']) {
                $existing = Vendor::where('company_id', $company->id)
                    ->where('email', $params['email'])
                    ->where('id', '!=', $vendor->id)
                    ->exists();

                if ($existing) {
                    throw new \Exception("Email {$params['email']} is already used by another vendor");
                }
            }

            $updates['email'] = $params['email'] ?: null;
            $changes[] = 'email → '.($params['email'] ?: 'removed');
        }

        // Each of these is array_key_exists rather than isset, because clearing
        // a field sends null and isset() would read that as "not supplied" and
        // silently keep the old value.
        foreach (['phone', 'tax_id', 'account_number', 'notes', 'website', 'logo_url'] as $field) {
            if (array_key_exists($field, $params)) {
                $updates[$field] = $params[$field] ?: null;
                $changes[] = "{$field} → ".($params[$field] ?: 'removed');
            }
        }

        if (array_key_exists('vendor_number', $params) && $params['vendor_number']) {
            $updates['vendor_number'] = $params['vendor_number'];
            $changes[] = "vendor_number → {$params['vendor_number']}";
        }

        if (isset($params['vendor_type'])) {
            $updates['vendor_type'] = $params['vendor_type'];
            $changes[] = "type → {$params['vendor_type']}";
        }

        if (isset($params['base_currency'])) {
            $updates['base_currency'] = strtoupper($params['base_currency']);
            $changes[] = "base_currency → {$updates['base_currency']}";
        }

        if (isset($params['payment_terms'])) {
            $updates['payment_terms'] = (int) $params['payment_terms'];
            $changes[] = "payment terms → {$updates['payment_terms']} days";
        }

        if (array_key_exists('address', $params)) {
            $updates['address'] = $params['address'] ?? null;
            $changes[] = 'address updated';
        }

        if (array_key_exists('ap_account_id', $params)) {
            $updates['ap_account_id'] = $params['ap_account_id'] ?: null;
            $changes[] = 'payables account updated';
        }

        if (array_key_exists('is_active', $params)) {
            $updates['is_active'] = (bool) $params['is_active'];
            $changes[] = 'status → '.($updates['is_active'] ? 'active' : 'inactive');
        }

        if (empty($updates)) {
            throw new \Exception('No changes specified');
        }

        $updates['updated_by_user_id'] = Auth::id();
        $vendor->update($updates);

        return [
            'message' => "Vendor updated: {$vendor->name}",
            'data' => [
                'id' => $vendor->id,
                'changes' => $changes,
            ],
        ];
    }
}
