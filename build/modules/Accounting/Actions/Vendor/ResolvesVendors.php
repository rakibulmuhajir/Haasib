<?php

namespace App\Modules\Accounting\Actions\Vendor;

use App\Modules\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * These actions are reachable from the command palette, where someone types
 * "acme fuels" rather than a UUID. Identifiers are therefore resolved the same
 * way the customer actions resolve theirs -- exact matches first, fuzzy name
 * last -- so the palette and the HTTP controllers can share one handler.
 *
 * A trait rather than a copy in each action: the customer equivalents repeat
 * this method three times, and a resolution rule that differs between view and
 * delete is how you delete the wrong record.
 */
trait ResolvesVendors
{
    protected function resolveVendor(string $identifier, string $companyId): Vendor
    {
        if (Str::isUuid($identifier)) {
            $vendor = Vendor::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($vendor) {
                return $vendor;
            }
        }

        $vendor = Vendor::where('company_id', $companyId)
            ->where('vendor_number', $identifier)
            ->first();
        if ($vendor) {
            return $vendor;
        }

        $vendor = Vendor::where('company_id', $companyId)
            ->where('email', $identifier)
            ->first();
        if ($vendor) {
            return $vendor;
        }

        $vendor = Vendor::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->first();
        if ($vendor) {
            return $vendor;
        }

        // Fuzzy match, last. Requires pg_trgm, as the customer actions do.
        $vendor = Vendor::where('company_id', $companyId)
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
        if ($vendor) {
            return $vendor;
        }

        throw new ModelNotFoundException("Vendor not found: {$identifier}");
    }
}
