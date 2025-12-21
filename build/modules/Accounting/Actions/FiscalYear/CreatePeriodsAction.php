<?php

namespace App\Modules\Accounting\Actions\FiscalYear;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Services\FiscalYearService;

class CreatePeriodsAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'period_type' => ['required', 'in:monthly,quarterly,yearly'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $fiscalYear = FiscalYear::where('company_id', $company->id)->findOrFail($params['id']);

        if ($fiscalYear->periods()->exists()) {
            throw new \InvalidArgumentException('Periods already exist for this fiscal year.');
        }

        $periods = app(FiscalYearService::class)->createPeriods($fiscalYear, $params['period_type']);

        return [
            'message' => 'Accounting periods created successfully.',
            'data' => [
                'count' => $periods->count(),
            ],
        ];
    }
}

