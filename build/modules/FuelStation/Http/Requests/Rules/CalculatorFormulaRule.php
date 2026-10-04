<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use App\Modules\FuelStation\Services\Calculator\FormulaEvaluator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A Calculator formula tree is well formed, small enough, and only names metrics the catalog has. */
class CalculatorFormulaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = app(FormulaEvaluator::class)->problem($value);
        if ($problem !== null) {
            $fail($problem);
        }
    }
}
