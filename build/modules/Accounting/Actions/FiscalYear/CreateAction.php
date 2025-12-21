<?php

namespace App\Modules\Accounting\Actions\FiscalYear;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Services\FiscalYearService;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'period_type' => ['required', 'in:monthly,quarterly,yearly'],
            'auto_create_periods' => ['sometimes', 'boolean'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $fiscalYear = app(FiscalYearService::class)->createFiscalYear($company->id, [
            'name' => $params['name'],
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
            'period_type' => $params['period_type'],
            'auto_create_periods' => (bool) ($params['auto_create_periods'] ?? false),
        ]);

        return [
            'message' => 'Fiscal year created successfully.',
            'data' => [
                'id' => $fiscalYear->id,
            ],
        ];
    }
}

