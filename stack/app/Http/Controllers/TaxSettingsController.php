<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use App\Models\TaxSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TaxSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the tax settings.
     */
    public function index()
    {
        $taxSettings = TaxSettings::getOrCreateForCompany(Auth::user()->current_company_id);

        // Load relationships
        $taxSettings->load([
            'defaultSalesTaxRate',
            'defaultPurchaseTaxRate',
            'createdBy',
            'updatedBy',
        ]);

        // Get available tax rates for dropdowns
        $salesTaxRates = TaxRate::where('company_id', Auth::user()->current_company_id)
            ->where(function ($query) {
                $query->where('tax_type', 'sales')
                    ->orWhere('tax_type', 'both');
            })
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'code']);

        $purchaseTaxRates = TaxRate::where('company_id', Auth::user()->current_company_id)
            ->where(function ($query) {
                $query->where('tax_type', 'purchase')
                    ->orWhere('tax_type', 'both');
            })
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'code']);

        return Inertia::render('TaxSettings/Index', [
            'taxSettings' => $taxSettings,
            'salesTaxRates' => $salesTaxRates,
            'purchaseTaxRates' => $purchaseTaxRates,
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
     * Show the form for editing tax settings.
     */
    public function edit()
    {
        $taxSettings = TaxSettings::getOrCreateForCompany(Auth::user()->current_company_id);

        // Get available tax rates for dropdowns
        $salesTaxRates = TaxRate::where('company_id', Auth::user()->current_company_id)
            ->where(function ($query) {
                $query->where('tax_type', 'sales')
                    ->orWhere('tax_type', 'both');
            })
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'code']);

        $purchaseTaxRates = TaxRate::where('company_id', Auth::user()->current_company_id)
            ->where(function ($query) {
                $query->where('tax_type', 'purchase')
                    ->orWhere('tax_type', 'both');
            })
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'code']);

        return Inertia::render('TaxSettings/Edit', [
            'taxSettings' => $taxSettings,
            'salesTaxRates' => $salesTaxRates,
            'purchaseTaxRates' => $purchaseTaxRates,
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
     * Update the tax settings.
     */
    public function update(Request $request)
    {
        $taxSettings = TaxSettings::getOrCreateForCompany(Auth::user()->current_company_id);

        $validated = $request->validate([
            // Tax calculation settings
            'tax_inclusive_pricing' => 'boolean',
            'round_tax_per_line' => 'boolean',
            'allow_compound_tax' => 'boolean',
            'rounding_precision' => 'integer|min:0|max:4',

            // Tax registration and identification
            'tax_registration_number' => 'nullable|string|max:100',
            'vat_number' => 'nullable|string|max:100',
            'tax_country_code' => 'required|string|size:2',

            // Reporting and filing settings
            'default_reporting_frequency' => 'required|in:monthly,quarterly,annually',
            'auto_file_tax_returns' => 'boolean',
            'tax_year_end_month' => 'required|integer|min:1|max:12',
            'tax_year_end_day' => 'required|integer|min:1|max:31',

            // Sales tax settings
            'calculate_sales_tax' => 'boolean',
            'charge_tax_on_shipping' => 'boolean',
            'tax_exempt_customers' => 'boolean',
            'default_sales_tax_rate_id' => 'nullable|exists:acct.tax_rates,id',

            // Purchase tax settings
            'calculate_purchase_tax' => 'boolean',
            'track_input_tax' => 'boolean',
            'default_purchase_tax_rate_id' => 'nullable|exists:acct.tax_rates,id',

            // Integration settings
            'auto_calculate_tax' => 'boolean',
            'validate_tax_rates' => 'boolean',
            'track_tax_by_jurisdiction' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $taxSettings->update($validated);

            DB::commit();

            return redirect()->route('tax.settings.index')
                ->with('success', 'Tax settings updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Tax Settings: '.$e->getMessage())
                ->withInput();
        }
    }
}
