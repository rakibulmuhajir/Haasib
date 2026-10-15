<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;
use App\Support\Extensions\HasHooks;

/** The bill form's default line account, when a module has one (first answer wins). */
class BillDefaults
{
    use HasHooks;

    public function lineAccountId(Company $company): ?string
    {
        foreach (self::hooks() as $hook) {
            if (($id = $hook($company)) !== null) {
                return $id;
            }
        }

        return null;
    }
}
