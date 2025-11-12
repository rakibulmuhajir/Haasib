<?php

namespace App\Http\Controllers;

use App\Models\TaxAgency;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TaxRateController extends Controller
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
        $query = TaxRate::where('company_id', Auth::user()->current_company_id)
            ->with(['taxAgency', 'taxComponents']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('code', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by tax agency
        if ($request->filled('tax_agency_id')) {
            $query->where('tax_agency_id', $request->input('tax_agency_id'));
        }

        // Filter by tax type
        if ($request->filled('tax_type')) {
            $query->where('tax_type', $request->input('tax_type'));
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by effective date
        if ($request->filled('effective_date')) {
            $date = $request->input('effective_date');
            $query->effectiveOn($date);
        } else {
            $query->active(); // Default to currently active rates
        }

        $taxRates = $query->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Get dropdown data
        $taxAgencies = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('TaxRates/Index', [
            'taxRates' => $taxRates,
            'taxAgencies' => $taxAgencies,
            'filters' => $request->only(['search', 'tax_agency_id', 'tax_type', 'is_active', 'effective_date']),
            'taxTypes' => [
                'sales' => 'Sales Tax',
                'purchase' => 'Purchase Tax',
                'both' => 'Sales & Purchase Tax',
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
            ->get(['id', 'name', 'country_code']);

        return Inertia::render('TaxRates/Create', [
            'taxAgencies' => $taxAgencies,
            'countries' => [
                'US' => 'United States',
                'CA' => 'Canada',
                'GB' => 'United Kingdom',
                'AU' => 'Australia',
                'DE' => 'Germany',
                'FR' => 'France',
                'IT' => 'Italy',
                'ES' => 'Spain',
                'MX' => 'Mexico',
                'JP' => 'Japan',
                'CN' => 'China',
                'IN' => 'India',
            ],
            'taxTypes' => [
                'sales' => 'Sales Tax',
                'purchase' => 'Purchase Tax',
                'both' => 'Sales & Purchase Tax',
            ],
            'calculationMethods' => [
                'percentage' => 'Percentage',
                'fixed_amount' => 'Fixed Amount',
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
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:1000',
            'rate' => 'required_unless:calculation_method,fixed_amount|numeric|min:0|max:100',
            'calculation_method' => 'required|in:percentage,fixed_amount',
            'fixed_amount' => 'required_if:calculation_method,fixed_amount|numeric|min:0',
            'tax_type' => 'required|in:sales,purchase,both',
            'is_compound' => 'boolean',
            'is_reverse_charge' => 'boolean',
            'is_inclusive' => 'boolean',
            'country_code' => 'nullable|string|size:2',
            'state_province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postal_code_pattern' => 'nullable|string|max:255',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Ensure unique code within company
        if (TaxRate::where('company_id', Auth::user()->current_company_id)
            ->where('code', $validated['code'])
            ->exists()) {
            return back()->with('error', 'Tax code already exists in your company.')
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $taxRate = TaxRate::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'tax_agency_id' => $validated['tax_agency_id'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'rate' => $validated['calculation_method'] === 'percentage' ? $validated['rate'] : 0,
                'calculation_method' => $validated['calculation_method'],
                'fixed_amount' => $validated['calculation_method'] === 'fixed_amount' ? $validated['fixed_amount'] : null,
                'tax_type' => $validated['tax_type'],
                'is_compound' => $validated['is_compound'] ?? false,
                'is_reverse_charge' => $validated['is_reverse_charge'] ?? false,
                'is_inclusive' => $validated['is_inclusive'] ?? false,
                'country_code' => $validated['country_code'] ?? null,
                'state_province' => $validated['state_province'] ?? null,
                'city' => $validated['city'] ?? null,
                'postal_code_pattern' => $validated['postal_code_pattern'] ?? null,
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('tax-rates.show', $taxRate)
                ->with('success', 'Tax Rate created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Tax Rate: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxRate $taxRate)
    {
        // Ensure user can only view tax rates from their company
        if ($taxRate->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $taxRate->load([
            'taxAgency',
            'taxComponents',
            'createdBy',
        ]);

        return Inertia::render('TaxRates/Show', [
            'taxRate' => $taxRate,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxRate $taxRate)
    {
        // Ensure user can only edit tax rates from their company
        if ($taxRate->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $taxAgencies = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'country_code']);

        return Inertia::render('TaxRates/Edit', [
            'taxRate' => $taxRate,
            'taxAgencies' => $taxAgencies,
            'countries' => [
                'US' => 'United States',
                'CA' => 'Canada',
                'GB' => 'United Kingdom',
                'AU' => 'Australia',
                'DE' => 'Germany',
                'FR' => 'France',
                'IT' => 'Italy',
                'ES' => 'Spain',
                'MX' => 'Mexico',
                'JP' => 'Japan',
                'CN' => 'China',
                'IN' => 'India',
            ],
            'taxTypes' => [
                'sales' => 'Sales Tax',
                'purchase' => 'Purchase Tax',
                'both' => 'Sales & Purchase Tax',
            ],
            'calculationMethods' => [
                'percentage' => 'Percentage',
                'fixed_amount' => 'Fixed Amount',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxRate $taxRate)
    {
        // Ensure user can only update tax rates from their company
        if ($taxRate->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'tax_agency_id' => 'required|exists:acct.tax_agencies,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:1000',
            'rate' => 'required_unless:calculation_method,fixed_amount|numeric|min:0|max:100',
            'calculation_method' => 'required|in:percentage,fixed_amount',
            'fixed_amount' => 'required_if:calculation_method,fixed_amount|numeric|min:0',
            'tax_type' => 'required|in:sales,purchase,both',
            'is_compound' => 'boolean',
            'is_reverse_charge' => 'boolean',
            'is_inclusive' => 'boolean',
            'country_code' => 'nullable|string|size:2',
            'state_province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postal_code_pattern' => 'nullable|string|max:255',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Ensure unique code within company (excluding current record)
        if (TaxRate::where('company_id', Auth::user()->current_company_id)
            ->where('code', $validated['code'])
            ->where('id', '!=', $taxRate->id)
            ->exists()) {
            return back()->with('error', 'Tax code already exists in your company.')
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $taxRate->update([
                'tax_agency_id' => $validated['tax_agency_id'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'rate' => $validated['calculation_method'] === 'percentage' ? $validated['rate'] : 0,
                'calculation_method' => $validated['calculation_method'],
                'fixed_amount' => $validated['calculation_method'] === 'fixed_amount' ? $validated['fixed_amount'] : null,
                'tax_type' => $validated['tax_type'],
                'is_compound' => $validated['is_compound'] ?? false,
                'is_reverse_charge' => $validated['is_reverse_charge'] ?? false,
                'is_inclusive' => $validated['is_inclusive'] ?? false,
                'country_code' => $validated['country_code'] ?? null,
                'state_province' => $validated['state_province'] ?? null,
                'city' => $validated['city'] ?? null,
                'postal_code_pattern' => $validated['postal_code_pattern'] ?? null,
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            DB::commit();

            return redirect()->route('tax-rates.show', $taxRate)
                ->with('success', 'Tax Rate updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Tax Rate: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxRate $taxRate)
    {
        // Ensure user can only delete tax rates from their company
        if ($taxRate->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $taxRate->canBeDeleted()) {
            return back()->with('error', 'This Tax Rate cannot be deleted as it is being used in transactions.');
        }

        try {
            DB::beginTransaction();

            $taxRate->delete();

            DB::commit();

            return redirect()->route('tax-rates.index')
                ->with('success', 'Tax Rate deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete Tax Rate: '.$e->getMessage());
        }
    }
}
