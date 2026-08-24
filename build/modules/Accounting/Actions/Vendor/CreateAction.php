<?php

namespace App\Modules\Accounting\Actions\Vendor;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAction implements PaletteAction
{
    /*
     * CommandBus::dispatch() replaces the incoming params with the result of
     * Validator::validate(), which returns only the keys these rules name. A
     * field missing from here is not merely unvalidated -- it is dropped on the
     * way to handle(). So these must cover everything StoreVendorRequest
     * accepts, or saving a vendor would silently discard half the form.
     */
    public function rules(): array
    {
        return [
            'vendor_number' => 'nullable|string|max:50',
            'name' => 'required|string|min:1|max:255',
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
        return Permissions::VENDOR_CREATE;
    }

    public function handle(array $params): array
    {
        // Taken from context rather than params: the bus strips company_id on
        // its way through, and a company id supplied by a caller is not a
        // company the caller has been authorised for.
        $company = CompanyContext::requireCompany();

        if (! empty($params['email'])) {
            $existing = Vendor::where('company_id', $company->id)
                ->where('email', $params['email'])
                ->exists();

            if ($existing) {
                // A ValidationException rather than a bare one: this handler is
                // reached from the vendor form as well as from the palette, and
                // the form needs the message on the email field rather than an
                // error page. StoreVendorRequest checks this first; this stays
                // as the backstop for the palette, which has no form request.
                throw ValidationException::withMessages([
                    'email' => "A vendor with the email {$params['email']} already exists.",
                ]);
            }
        }

        $vendor = Vendor::create([
            'company_id' => $company->id,
            'vendor_number' => $params['vendor_number'] ?? $this->generateVendorNumber($company->id),
            'name' => trim($params['name']),
            'email' => $params['email'] ?? null,
            'phone' => $params['phone'] ?? null,
            'vendor_type' => $params['vendor_type'] ?? Vendor::TYPE_GENERAL,
            'address' => $params['address'] ?? null,
            'tax_id' => $params['tax_id'] ?? null,
            'base_currency' => strtoupper($params['base_currency'] ?? $company->base_currency ?? 'USD'),
            'payment_terms' => $params['payment_terms'] ?? 30,
            'account_number' => $params['account_number'] ?? null,
            'notes' => $params['notes'] ?? null,
            'website' => $params['website'] ?? null,
            'logo_url' => $params['logo_url'] ?? null,
            'ap_account_id' => $params['ap_account_id'] ?? null,
            'is_active' => $params['is_active'] ?? true,
            'created_by_user_id' => Auth::id(),
        ]);

        return [
            'message' => "Vendor created: {$vendor->name}",
            'data' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'email' => $vendor->email,
                'currency' => $vendor->base_currency,
            ],
        ];
    }

    /**
     * Same scheme and the same locking as customer numbers: the row lock is
     * what stops two vendors created in the same instant claiming VEND-00001.
     */
    private function generateVendorNumber(string $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $lastNumber = Vendor::where('company_id', $companyId)
                ->whereNotNull('vendor_number')
                ->lockForUpdate()
                ->orderByDesc('vendor_number')
                ->value('vendor_number');

            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            } else {
                $sequence = 1;
            }

            return 'VEND-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
