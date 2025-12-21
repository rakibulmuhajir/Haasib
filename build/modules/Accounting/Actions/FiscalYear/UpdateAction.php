<?php

namespace App\Modules\Accounting\Actions\FiscalYear;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\FiscalYear;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        /** @var FiscalYear $fiscalYear */
        $fiscalYear = FiscalYear::where('company_id', $company->id)->findOrFail($params['id']);

        if (($params['start_date'] ?? null) !== null || ($params['end_date'] ?? null) !== null) {
            if ($fiscalYear->periods()->exists()) {
                throw new \InvalidArgumentException('Cannot change fiscal year dates after periods have been created.');
            }
        }

        $update = [];
        foreach (['name', 'start_date', 'end_date', 'is_current'] as $field) {
            if (array_key_exists($field, $params)) {
                $update[$field] = $params[$field];
            }
        }

        if (($update['is_current'] ?? false) === true) {
            FiscalYear::where('company_id', $company->id)
                ->where('id', '!=', $fiscalYear->id)
                ->update(['is_current' => false]);
        }

        $fiscalYear->update($update);

        return [
            'message' => 'Fiscal year updated successfully.',
            'data' => [
                'id' => $fiscalYear->id,
            ],
        ];
    }
}

