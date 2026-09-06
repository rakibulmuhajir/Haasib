<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\OperationsIndexRequest;
use App\Modules\Umrah\Services\MovementReportService;
use App\Modules\Umrah\Services\OperationalEventTimelineService;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function __construct(
        private OperationalEventTimelineService $operations,
        private MovementReportService $reports,
    ) {}

    public function index(OperationsIndexRequest $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Umrah/Operations/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'operationsData' => $this->operations->build($company, $request->user(), $request->validated()),
        ]);
    }

    public function report(OperationsIndexRequest $request): View
    {
        $company = app(CurrentCompany::class)->get();

        return view('umrah::operations.report', [
            'company' => $company,
            'report' => $this->reports->build($company, $request->user(), $request->validated()),
            'logoSource' => $this->logoSource($company->logo_url),
            'forPdf' => false,
        ]);
    }

    public function pdf(OperationsIndexRequest $request): HttpResponse
    {
        $company = app(CurrentCompany::class)->get();
        $report = $this->reports->build($company, $request->user(), $request->validated());

        return Pdf::loadView('umrah::operations.report', [
            'company' => $company,
            'report' => $report,
            'logoSource' => $this->logoSource($company->logo_url),
            'forPdf' => true,
        ])->setPaper('a4', 'portrait')
            ->download($this->reports->filename($report));
    }

    private function logoSource(?string $logoUrl): ?string
    {
        if (! $logoUrl) {
            return null;
        }

        return str_starts_with($logoUrl, 'http')
            ? $logoUrl
            : public_path(ltrim($logoUrl, '/'));
    }
}
