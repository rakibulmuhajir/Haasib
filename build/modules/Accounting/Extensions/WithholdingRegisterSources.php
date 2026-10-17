<?php

namespace App\Modules\Accounting\Extensions;

use App\Support\Extensions\HasHooks;
use Illuminate\Support\Collection;

/**
 * Extra lines for the withholding tax register from a module's own records (e.g. tax a card
 * company deducted from a fuel station's card sales). A hook takes ($companyId, $slug, $from,
 * $to, $side) and returns register lines shaped like WithholdingRegisterService's own.
 */
class WithholdingRegisterSources
{
    use HasHooks;

    public function lines(string $companyId, string $slug, string $from, string $to, string $side): Collection
    {
        $lines = collect();
        foreach (self::hooks() as $hook) {
            $lines = $lines->merge($hook($companyId, $slug, $from, $to, $side));
        }

        return $lines;
    }
}
