<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use App\Constants\Permissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Entering a supplier bill / fuel purchase inline inside the Daily Close uses the same
 * canonical bill-creation path as the Bills module, so it requires the same permission.
 * A user without bill.create simply cannot submit any purchases rows at all.
 */
class RequiresBillCreatePermission implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $user = auth()->user();
        if (!$user || !$user->hasCompanyPermission(Permissions::BILL_CREATE)) {
            $fail('You do not have permission to enter supplier bills or fuel purchases.');
        }
    }
}
