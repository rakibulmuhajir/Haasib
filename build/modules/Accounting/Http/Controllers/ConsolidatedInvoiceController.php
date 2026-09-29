<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreConsolidatedInvoiceRequest;
use App\Modules\Accounting\Services\ConsolidatedInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Consolidated invoices: saved once from a customer statement, then only viewed, printed, downloaded. */
class ConsolidatedInvoiceController extends Controller
{
    public function __construct(private readonly ConsolidatedInvoiceService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasCompanyPermission(Permissions::INVOICE_VIEW), 403);
        $company = CompanyContext::getCompany();

        $documents = DB::table('acct.consolidated_invoices as c')
            ->join('acct.customers as cu', 'cu.id', '=', 'c.customer_id')
            ->where('c.company_id', $company->id)
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('c.customer_id', $id))
            ->orderByDesc('c.created_at')
            ->select(['c.id', 'c.number', 'c.title', 'c.period_from', 'c.period_to', 'c.total', 'c.created_at', 'cu.name as customer_name',
                DB::raw('jsonb_array_length(c.lines) as line_count')])
            ->paginate(25)->withQueryString();

        return Inertia::render('accounting/consolidated-invoices/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'documents' => $documents,
        ]);
    }

    public function store(StoreConsolidatedInvoiceRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $data = $request->validated();

        $id = $this->service->create($company, $data, $request->user()?->id);

        return redirect()->route('consolidated-invoices.show', ['company' => $company->slug, 'document' => $id])
            ->with('success', 'Saved');
    }

    public function show(Request $request, string $company, string $document): Response
    {
        abort_unless($request->user()?->hasCompanyPermission(Permissions::INVOICE_VIEW), 403);
        $companyModel = CompanyContext::getCompany();

        return Inertia::render('accounting/consolidated-invoices/Show', [
            'company' => ['id' => $companyModel->id, 'name' => $companyModel->name, 'slug' => $companyModel->slug, 'base_currency' => $companyModel->base_currency],
            'document' => $this->service->document($companyModel, $document),
        ]);
    }

    public function pdf(Request $request, string $company, string $document)
    {
        abort_unless($request->user()?->hasCompanyPermission(Permissions::INVOICE_VIEW), 403);
        $doc = $this->service->document(CompanyContext::getCompany(), $document);
        $name = Str::slug("{$doc['number']} {$doc['customer_name']}").'.pdf';

        return response($this->service->pdf($doc), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }
}
