<?php

namespace App\Http\Controllers;

use App\Models\TaxAgency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TaxAgencyController extends Controller
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
        $query = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->with(['taxRates' => function ($query) {
                $query->where('is_active', true);
            }]);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('tax_id', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by country
        if ($request->filled('country_code')) {
            $query->where('country_code', $request->input('country_code'));
        }

        // Filter by reporting frequency
        if ($request->filled('reporting_frequency')) {
            $query->where('reporting_frequency', $request->input('reporting_frequency'));
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $agencies = $query->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('TaxAgencies/Index', [
            'agencies' => $agencies,
            'filters' => $request->only(['search', 'country_code', 'reporting_frequency', 'is_active']),
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
            'reportingFrequencies' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('TaxAgencies/Create', [
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
            'reportingFrequencies' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
            ],
            'filingMethods' => [
                'electronic' => 'Electronic',
                'paper' => 'Paper',
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
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:100',
            'country_code' => 'required|string|size:2',
            'state_province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:500',
            'address_line_1' => 'nullable|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'reporting_frequency' => 'required|in:monthly,quarterly,annually',
            'filing_method' => 'required|in:electronic,paper,auto',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $agency = TaxAgency::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'name' => $validated['name'],
                'tax_id' => $validated['tax_id'] ?? null,
                'country_code' => $validated['country_code'],
                'state_province' => $validated['state_province'] ?? null,
                'city' => $validated['city'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'website' => $validated['website'] ?? null,
                'address_line_1' => $validated['address_line_1'] ?? null,
                'address_line_2' => $validated['address_line_2'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'reporting_frequency' => $validated['reporting_frequency'],
                'filing_method' => $validated['filing_method'],
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('tax-agencies.show', $agency)
                ->with('success', 'Tax Agency created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Tax Agency: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxAgency $taxAgency)
    {
        // Ensure user can only view agencies from their company
        if ($taxAgency->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $taxAgency->load([
            'taxRates' => function ($query) {
                $query->with('taxComponents')->orderBy('name');
            },
            'taxReturns' => function ($query) {
                $query->orderBy('filing_period_start', 'desc')->limit(10);
            },
            'createdBy',
        ]);

        return Inertia::render('TaxAgencies/Show', [
            'agency' => $taxAgency,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxAgency $taxAgency)
    {
        // Ensure user can only edit agencies from their company
        if ($taxAgency->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        return Inertia::render('TaxAgencies/Edit', [
            'agency' => $taxAgency,
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
            'reportingFrequencies' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
            ],
            'filingMethods' => [
                'electronic' => 'Electronic',
                'paper' => 'Paper',
                'auto' => 'Automatic',
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxAgency $taxAgency)
    {
        // Ensure user can only update agencies from their company
        if ($taxAgency->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:100',
            'country_code' => 'required|string|size:2',
            'state_province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:500',
            'address_line_1' => 'nullable|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'reporting_frequency' => 'required|in:monthly,quarterly,annually',
            'filing_method' => 'required|in:electronic,paper,auto',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $taxAgency->update([
                'name' => $validated['name'],
                'tax_id' => $validated['tax_id'] ?? null,
                'country_code' => $validated['country_code'],
                'state_province' => $validated['state_province'] ?? null,
                'city' => $validated['city'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'website' => $validated['website'] ?? null,
                'address_line_1' => $validated['address_line_1'] ?? null,
                'address_line_2' => $validated['address_line_2'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'reporting_frequency' => $validated['reporting_frequency'],
                'filing_method' => $validated['filing_method'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            DB::commit();

            return redirect()->route('tax-agencies.show', $taxAgency)
                ->with('success', 'Tax Agency updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Tax Agency: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxAgency $taxAgency)
    {
        // Ensure user can only delete agencies from their company
        if ($taxAgency->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $taxAgency->canBeDeleted()) {
            return back()->with('error', 'This Tax Agency cannot be deleted as it has associated tax rates or tax returns.');
        }

        try {
            DB::beginTransaction();

            $taxAgency->delete();

            DB::commit();

            return redirect()->route('tax-agencies.index')
                ->with('success', 'Tax Agency deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete Tax Agency: '.$e->getMessage());
        }
    }
}
