<?php

namespace App\Modules\Accounting\Actions\FiscalYear;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\FiscalYear;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
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

        if ($fiscalYear->transactions()->exists()) {
            throw new \InvalidArgumentException('Cannot delete fiscal year with existing transactions.');
        }

        $fiscalYear->delete();

        return ['message' => 'Fiscal year deleted successfully.'];
    }
}

