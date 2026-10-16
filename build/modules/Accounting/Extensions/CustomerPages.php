<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Support\Extensions\HasHooks;

/** Where a saved customer returns to, when a module keeps its own customer page. */
class CustomerPages
{
    use HasHooks;

    public function afterSaveUrl(Company $company, Customer $customer): ?string
    {
        foreach (self::hooks() as $hook) {
            if (($url = $hook($company, $customer)) !== null) {
                return $url;
            }
        }

        return null;
    }
}
