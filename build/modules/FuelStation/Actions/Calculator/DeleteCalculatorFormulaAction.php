<?php

namespace App\Modules\FuelStation\Actions\Calculator;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Modules\FuelStation\Models\CalculatorFormula;
use App\Services\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;

/** calculator.delete -- remove a formula; only the person who made it can. */
class DeleteCalculatorFormulaAction implements PaletteAction
{
    public function permission(): ?string
    {
        return Permissions::REPORT_VIEW;
    }

    public function rules(): array
    {
        return ['id' => ['required', 'uuid'], 'user_id' => ['required', 'uuid']];
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $formula = CalculatorFormula::where('company_id', $company->id)
            ->where(fn ($q) => $q->where('user_id', $params['user_id'])->orWhere('is_shared', true))
            ->findOrFail($params['id']);
        if ($formula->user_id !== $params['user_id']) {
            throw new AuthorizationException('Only the person who made a formula can delete it.');
        }
        $formula->delete();

        return ['message' => 'Formula deleted.'];
    }
}
