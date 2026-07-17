<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\TravelReportRequest;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Modules\Umrah\Services\TravelReportService;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private TravelReportService $reports,
        private TravelAccessService $access,
    ) {}

    public function earnings(string $company): RedirectResponse
    {
        return redirect()->route('umrah.reports.show', ['company' => $company, 'report' => 'group-profitability']);
    }

    public function show(TravelReportRequest $request, string $company, string $report): Response
    {
        $currentCompany = app(CurrentCompany::class)->get();
        $data = $this->reports->build($currentCompany, $request->user(), $report, $request->validated());

        return Inertia::render('Umrah/Reports/Index', [
            'company' => [
                'id' => $currentCompany->id,
                'name' => $currentCompany->name,
                'slug' => $currentCompany->slug,
                'base_currency' => $currentCompany->base_currency,
            ],
            'report' => $data,
            'reportLinks' => $this->reportLinks($currentCompany->id, $request),
        ]);
    }

    public function pdf(TravelReportRequest $request, string $company, string $report): HttpResponse
    {
        $currentCompany = app(CurrentCompany::class)->get();
        $data = $this->reports->build($currentCompany, $request->user(), $report, $request->validated(), true);
        $logoSource = $currentCompany->logo_url && str_starts_with($currentCompany->logo_url, 'http')
            ? $currentCompany->logo_url
            : ($currentCompany->logo_url ? public_path(ltrim($currentCompany->logo_url, '/')) : null);

        return Pdf::loadView('umrah::reports.table', [
            'company' => $currentCompany,
            'report' => $data,
            'logoSource' => $logoSource,
        ])->setPaper('a4', count($data['columns']) > 8 ? 'landscape' : 'portrait')
            ->download($report.'-'.$data['filters']['start'].'-'.$data['filters']['end'].'.pdf');
    }

    private function reportLinks(string $companyId, TravelReportRequest $request): array
    {
        $isAgent = $this->access->isAgentMember($companyId, $request->user());
        $allowed = $isAgent
            ? TravelReportRequest::SELF_REPORTS
            : ($request->user()?->hasCompanyPermission(Permissions::UMRAH_REPORT_VIEW) ? array_keys(TravelReportService::REPORTS) : []);

        return collect($allowed)->map(fn (string $key) => [
            'key' => $key,
            'title' => TravelReportService::REPORTS[$key]['title'],
        ])->values()->all();
    }
}
