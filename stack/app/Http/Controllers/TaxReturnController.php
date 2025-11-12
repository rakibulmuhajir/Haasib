<?php

namespace App\Http\Controllers;

use App\Models\TaxAgency;
use App\Models\TaxReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TaxReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = TaxReturn::where('company_id', Auth::user()->current_company_id)
            ->with(['taxAgency', 'preparedBy', 'filedBy']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'ILIKE', "%{$search}%")
                    ->orWhere('confirmation_number', 'ILIKE', "%{$search}%")
                    ->orWhere('payment_reference', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by tax agency
        if ($request->filled('tax_agency_id')) {
            $query->where('tax_agency_id', $request->input('tax_agency_id'));
        }

        // Filter by return type
        if ($request->filled('return_type')) {
            $query->where('return_type', $request->input('return_type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by period
        if ($request->filled('period_start')) {
            $query->where('filing_period_start', '>=', $request->input('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->where('filing_period_end', '<=', $request->input('period_end'));
        }

        $taxReturns = $query->orderBy('filing_period_start', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Get dropdown data
        $taxAgencies = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('TaxReturns/Index', [
            'taxReturns' => $taxReturns,
            'taxAgencies' => $taxAgencies,
            'filters' => $request->only(['search', 'tax_agency_id', 'return_type', 'status', 'period_start', 'period_end']),
            'returnTypes' => [
                'sales_tax' => 'Sales Tax',
                'purchase_tax' => 'Purchase Tax',
                'vat' => 'VAT',
                'income_tax' => 'Income Tax',
            ],
            'statuses' => [
                'draft' => 'Draft',
                'prepared' => 'Prepared',
                'filed' => 'Filed',
                'paid' => 'Paid',
                'overdue' => 'Overdue',
                'cancelled' => 'Cancelled',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $taxAgencies = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'reporting_frequency']);

        return Inertia::render('TaxReturns/Create', [
            'taxAgencies' => $taxAgencies,
            'returnTypes' => [
                'sales_tax' => 'Sales Tax',
                'purchase_tax' => 'Purchase Tax',
                'vat' => 'VAT',
                'income_tax' => 'Income Tax',
            ],
            'filingFrequencies' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
            ],
            'filingMethods' => [
                'paper' => 'Paper',
                'electronic' => 'Electronic',
                'auto' => 'Automatic',
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_agency_id' => 'required|exists:acct.tax_agencies,id',
            'return_type' => 'required|in:sales_tax,purchase_tax,vat,income_tax',
            'filing_frequency' => 'required|in:monthly,quarterly,annually',
            'filing_period_start' => 'required|date',
            'filing_period_end' => 'required|date|after:filing_period_start',
            'due_date' => 'required|date|after_or_equal:filing_period_end',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            DB::beginTransaction();

            $taxReturn = TaxReturn::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'tax_agency_id' => $validated['tax_agency_id'],
                'return_type' => $validated['return_type'],
                'filing_frequency' => $validated['filing_frequency'],
                'filing_period_start' => $validated['filing_period_start'],
                'filing_period_end' => $validated['filing_period_end'],
                'due_date' => $validated['due_date'],
                'filing_method' => 'electronic',
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Auto-calculate totals for the period
            $taxReturn->calculateTotals();

            DB::commit();

            return redirect()->route('tax-returns.show', $taxReturn)
                ->with('success', 'Tax Return created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Tax Return: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxReturn $taxReturn)
    {
        // Ensure user can only view tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $taxReturn->load([
            'taxAgency',
            'taxComponents' => function ($query) {
                $query->with('taxRate')->orderBy('created_at');
            },
            'preparedBy',
            'filedBy',
            'createdBy',
        ]);

        return Inertia::render('TaxReturns/Show', [
            'taxReturn' => $taxReturn,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxReturn $taxReturn)
    {
        // Ensure user can only edit tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $taxReturn->canBeEdited()) {
            abort(403, 'This Tax Return cannot be edited in its current status.');
        }

        $taxAgencies = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'reporting_frequency']);

        return Inertia::render('TaxReturns/Edit', [
            'taxReturn' => $taxReturn,
            'taxAgencies' => $taxAgencies,
            'returnTypes' => [
                'sales_tax' => 'Sales Tax',
                'purchase_tax' => 'Purchase Tax',
                'vat' => 'VAT',
                'income_tax' => 'Income Tax',
            ],
            'filingFrequencies' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
            ],
            'filingMethods' => [
                'paper' => 'Paper',
                'electronic' => 'Electronic',
                'auto' => 'Automatic',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxReturn $taxReturn)
    {
        // Ensure user can only update tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $taxReturn->canBeEdited()) {
            abort(403, 'This Tax Return cannot be edited in its current status.');
        }

        $validated = $request->validate([
            'tax_agency_id' => 'required|exists:acct.tax_agencies,id',
            'return_type' => 'required|in:sales_tax,purchase_tax,vat,income_tax',
            'filing_frequency' => 'required|in:monthly,quarterly,annually',
            'filing_period_start' => 'required|date',
            'filing_period_end' => 'required|date|after:filing_period_start',
            'due_date' => 'required|date|after_or_equal:filing_period_end',
            'penalty' => 'numeric|min:0',
            'interest' => 'numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            DB::beginTransaction();

            $taxReturn->update([
                'tax_agency_id' => $validated['tax_agency_id'],
                'return_type' => $validated['return_type'],
                'filing_frequency' => $validated['filing_frequency'],
                'filing_period_start' => $validated['filing_period_start'],
                'filing_period_end' => $validated['filing_period_end'],
                'due_date' => $validated['due_date'],
                'penalty' => $validated['penalty'] ?? 0,
                'interest' => $validated['interest'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Recalculate totals
            $taxReturn->calculateTotals();

            DB::commit();

            return redirect()->route('tax-returns.show', $taxReturn)
                ->with('success', 'Tax Return updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Tax Return: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxReturn $taxReturn)
    {
        // Ensure user can only delete tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $taxReturn->canBeEdited()) {
            return back()->with('error', 'This Tax Return cannot be deleted in its current status.');
        }

        try {
            DB::beginTransaction();

            $taxReturn->delete();

            DB::commit();

            return redirect()->route('tax-returns.index')
                ->with('success', 'Tax Return deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete Tax Return: '.$e->getMessage());
        }
    }

    /**
     * Prepare the tax return for filing.
     */
    public function prepare(TaxReturn $taxReturn)
    {
        // Ensure user can only prepare tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $taxReturn->prepare()) {
            return back()->with('error', 'This Tax Return cannot be prepared in its current status.');
        }

        return back()->with('success', 'Tax Return prepared successfully.');
    }

    /**
     * File the tax return.
     */
    public function file(Request $request, TaxReturn $taxReturn)
    {
        // Ensure user can only file tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'filing_method' => 'required|in:paper,electronic,auto',
            'confirmation_number' => 'nullable|string|max:255',
        ]);

        if (! $taxReturn->file($validated['filing_method'], $validated['confirmation_number'])) {
            return back()->with('error', 'This Tax Return cannot be filed in its current status.');
        }

        return back()->with('success', 'Tax Return filed successfully.');
    }

    /**
     * Mark tax return as paid.
     */
    public function markAsPaid(Request $request, TaxReturn $taxReturn)
    {
        // Ensure user can only mark tax returns from their company as paid
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        if (! $taxReturn->markAsPaid($validated['amount'], $validated['payment_reference'])) {
            return back()->with('error', 'This Tax Return cannot be marked as paid in its current status.');
        }

        return back()->with('success', 'Tax Return marked as paid successfully.');
    }

    /**
     * Generate tax return PDF.
     */
    public function generatePdf(TaxReturn $taxReturn)
    {
        // Ensure user can only generate PDF for tax returns from their company
        if ($taxReturn->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // TODO: Implement PDF generation
        return back()->with('info', 'PDF generation will be implemented in a future update.');
    }
}
