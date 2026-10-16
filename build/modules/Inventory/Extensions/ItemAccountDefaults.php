<?php

namespace App\Modules\Inventory\Extensions;

use App\Models\Company;
use App\Support\Extensions\HasHooks;

/**
 * Default stock / sales / cost accounts for a new stocked product, when a module
 * has its own (first answer wins). A hook is fn (Company, array $data, ?string $userId)
 * and returns ['asset_account_id' => …, 'income_account_id' => …, 'expense_account_id' => …] or null.
 */
class ItemAccountDefaults
{
    use HasHooks;

    public static function for(Company $company, array $data, ?string $userId): ?array
    {
        foreach (self::hooks() as $hook) {
            if (($accounts = $hook($company, $data, $userId)) !== null) {
                return $accounts;
            }
        }

        return null;
    }
}
