<?php

namespace App\Modules\FuelStation\Actions\Calculator;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Modules\FuelStation\Http\Requests\Rules\CalculatorFormulaRule;
use App\Modules\FuelStation\Models\CalculatorFormula;
use App\Services\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * calculator.save -- save a formula as the user's own (personal unless shared), or replace one
 * they made. Only the creator may change it; nobody can reach another person's personal formula.
 */
class SaveCalculatorFormulaAction implements PaletteAction
{
    public function permission(): ?string
    {
        return Permissions::REPORT_VIEW;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'user_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'formula' => ['required', 'array', new CalculatorFormulaRule],
            'is_shared' => ['sometimes', 'boolean'],
        ];
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $values = [
            'name' => trim($params['name']),
            'formula' => $params['formula'],
            'is_shared' => (bool) ($params['is_shared'] ?? false),
        ];

        if (! empty($params['id'])) {
            $formula = CalculatorFormula::where('company_id', $company->id)
                ->where(fn ($q) => $q->where('user_id', $params['user_id'])->orWhere('is_shared', true))
                ->findOrFail($params['id']);
            if ($formula->user_id !== $params['user_id']) {
                throw new AuthorizationException('Only the person who made a formula can change it.');
            }
            $formula->update($values);
        } else {
            $formula = CalculatorFormula::create($values + ['company_id' => $company->id, 'user_id' => $params['user_id']]);
        }

        return ['message' => 'Formula saved.', 'data' => ['id' => $formula->id]];
    }
}
