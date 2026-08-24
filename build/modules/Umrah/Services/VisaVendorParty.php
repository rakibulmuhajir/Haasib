<?php

namespace App\Modules\Umrah\Services;

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CommandBus;
use Illuminate\Support\Str;

/**
 * The one place an umrah vendor's supplier record is created or changed.
 *
 * An umrah vendor is a supplier with a visa-desk profile attached, so "the
 * vendor's name" is the supplier's name and there is nowhere else to put it.
 * Every controller that accepts a name, email or phone for one hands them here,
 * and they land on acct.vendors -- once.
 */
class VisaVendorParty
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * The supplier for a new umrah vendor: the one that already answers to this
     * name in this company, or a new one.
     *
     * Matched on the name rather than the email because these are mostly
     * government offices and visa desks entered without one. Reuse rather than
     * create, because a second row would split one supplier's balance across
     * two ledgers -- the exact failure the extension is meant to make
     * impossible.
     */
    public function createFor(Company $company, array $data): Vendor
    {
        $existing = $this->findByName($company, $data['name'] ?? null);

        if ($existing) {
            return $existing;
        }

        /*
         * Through the bus so supplier numbering, auditing and events stay in
         * the one handler that owns them -- but with the permission check
         * skipped: the caller has already been authorised to create an umrah
         * vendor, and an umrah vendor IS a supplier. Under withContext because
         * the handler reads its company from ambient context, and one of these
         * can be created where none is set -- a seeder, a test, a command.
         */
        $result = CompanyContext::withContext($company, fn () => $this->bus->dispatch('vendor.create', [
            'name' => $data['name'],
            'vendor_type' => Vendor::TYPE_SERVICE_PROVIDER,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'base_currency' => $company->base_currency,
        ], null, true));

        return Vendor::findOrFail($result['data']['id']);
    }

    /**
     * Push edited supplier details onto the vendor.
     *
     * Not a sync in the sense of reconciling two copies -- there is only one
     * copy. This is the write the umrah vendor form's fields were always meant
     * to perform.
     */
    public function updateFrom(VisaVendor $umrahVendor, array $data): void
    {
        $vendor = $umrahVendor->vendor;

        if (! $vendor) {
            return;
        }

        $changes = [];

        // array_key_exists, not isset: clearing an email to null is an edit,
        // and isset() would read it as "not submitted" and keep the old address
        // forever.
        foreach (['name', 'email', 'phone', 'logo_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field];
            }
        }

        if ($changes) {
            $vendor->update($changes);
        }
    }

    private function findByName(Company $company, ?string $name): ?Vendor
    {
        if (! $name) {
            return null;
        }

        return Vendor::where('company_id', $company->id)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();
    }
}
