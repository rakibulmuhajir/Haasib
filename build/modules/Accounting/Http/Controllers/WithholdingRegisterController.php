<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\WithholdingRegisterRequest;
use App\Modules\Accounting\Services\WithholdingRegisterService;
use App\Modules\Accounting\Services\WithholdingTax;
use Inertia\Inertia;

/** The withholding tax register: deducted by us and from us, by period, section and party. */
class WithholdingRegisterController extends Controller
{
    public function index(WithholdingRegisterRequest $request)
    {
        $company = CompanyContext::getCompany();
        $filters = $request->safe()->only(['from', 'to', 'side']);
        $report = app(WithholdingRegisterService::class)->run($company->id, $company->slug, $filters['from'], $filters['to'], $filters['side']);

        if ($request->validated('format') === 'csv') {
            return response()->streamDownload(
                fn () => print (app(WithholdingRegisterService::class)->csv($report)),
                "withholding-tax-{$filters['from']}-{$filters['to']}.csv",
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        return Inertia::render('accounting/reports/WithholdingRegister', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => $filters,
            'taxOn' => app(WithholdingTax::class)->enabled($company),
            'report' => $report,
        ]);
    }
}
