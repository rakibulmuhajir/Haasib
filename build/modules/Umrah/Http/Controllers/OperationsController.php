<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\OperationsIndexRequest;
use App\Modules\Umrah\Services\MovementCsvExporter;
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
        private \App\Modules\Umrah\Services\OperationViews $views,
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
            'savedViews' => $this->views->listing($company->id, $request->user()->id),
        ]);
    }

    public function saveView(\App\Modules\Umrah\Http\Requests\SaveOperationViewRequest $request, string $companySlug, ?string $view = null): \Illuminate\Http\RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        try {
            $data = $request->validated();
            if (! $request->isMethod('delete')) {
                $data = [...$this->operations->build($company, $request->user(), $data)['filters'], 'name' => $data['name']];
            }
            \Illuminate\Support\Facades\Bus::dispatch(new \App\Modules\Umrah\Commands\SaveOperationView($company->id, $request->user()->id, $data, $view));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The saved view could not be updated. Please try again.');
        }

        return back()->with('success', $request->isMethod('delete') ? 'Saved view removed.' : 'Operations view saved.');
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

    public function csv(OperationsIndexRequest $request, MovementCsvExporter $exporter): HttpResponse|\Illuminate\Http\RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        try {
            $report = $this->reports->build($company, $request->user(), $request->validated());
            $csv = $exporter->export($report, $company->name);
            $filename = str_replace('.pdf', '.csv', $this->reports->filename($report));

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('umrah.operations.index', ['company' => $company->slug, ...$request->validated()])
                ->with('error', 'The movement CSV could not be prepared. Please try again.');
        }
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
