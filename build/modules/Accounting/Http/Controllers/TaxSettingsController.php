<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\CreateTaxExemptionRequest;
use App\Modules\Accounting\Http\Requests\CreateTaxGroupRequest;
use App\Modules\Accounting\Http\Requests\CreateTaxRateRequest;
use App\Modules\Accounting\Http\Requests\CreateTaxRegistrationRequest;
use App\Modules\Accounting\Http\Requests\UpdateTaxSettingsRequest;
use App\Modules\Accounting\Http\Requests\UpdateTaxRateRequest;
use App\Modules\Accounting\Http\Requests\DeleteTaxRateRequest;
use App\Modules\Accounting\Http\Requests\CalculateTaxRequest;
use App\Modules\Accounting\Models\CompanyTaxRegistration;
use App\Modules\Accounting\Models\CompanyTaxSettings;
use App\Modules\Accounting\Models\Jurisdiction;
use App\Modules\Accounting\Models\TaxExemption;
use App\Modules\Accounting\Models\TaxGroup;
use App\Modules\Accounting\Models\TaxRate;
use App\Services\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxSettingsController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->getOrFail();

        $taxSettings = CompanyTaxSettings::forCompany($company);

        $jurisdictions = Jurisdiction::active()->orderBy('name')->get();
        $taxRates = TaxRate::forCompany($company->id)->active()->with('jurisdiction')->orderBy('code')->get();
        $taxGroups = TaxGroup::forCompany($company->id)->active()->with(['jurisdiction', 'taxRates'])->orderBy('code')->get();
        $taxRegistrations = CompanyTaxRegistration::forCompany($company->id)->active()->with('jurisdiction')->orderBy('registration_type')->get();
        $taxExemptions = TaxExemption::forCompany($company->id)->active()->orderBy('code')->get();

        return Inertia::render('accounting/tax/Settings', [
            'company' => [
                'id' => $company->id,
                'slug' => $company->slug,
            ],
            'taxSettings' => $taxSettings,
            'jurisdictions' => $jurisdictions,
            'taxRates' => $taxRates,
            'taxGroups' => $taxGroups,
            'taxRegistrations' => $taxRegistrations,
            'taxExemptions' => $taxExemptions,
            'canManageTax' => auth()->user()?->hasCompanyPermission('tax.settings.update') ?? false,
        ]);
    }

    public function updateSettings(UpdateTaxSettingsRequest $request)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxSettings = CompanyTaxSettings::forCompany($company);

        $taxSettings->update($request->validated());

        return redirect()->back()->with('success', 'Tax settings updated successfully.');
    }

    public function createTaxRate(CreateTaxRateRequest $request)
    {
        $company = app(CurrentCompany::class)->getOrFail();

        TaxRate::create(array_merge($request->validated(), [
            'company_id' => $company->id,
            'created_by_user_id' => auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ]));

        return redirect()->back()->with('success', 'Tax rate created successfully.');
    }

    public function updateTaxRate(UpdateTaxRateRequest $request, $id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxRate = TaxRate::forCompany($company->id)->findOrFail($id);

        $taxRate->update(array_merge($request->validated(), [
            'updated_by_user_id' => auth()->id(),
        ]));

        return redirect()->back()->with('success', 'Tax rate updated successfully.');
    }

    public function deleteTaxRate(DeleteTaxRateRequest $request, $id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxRate = TaxRate::forCompany($company->id)->findOrFail($id);

        if ($taxRate->taxGroups()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete tax rate that is used in tax groups.');
        }

        $taxRate->delete();

        return redirect()->back()->with('success', 'Tax rate deleted successfully.');
    }

    public function createTaxGroup(CreateTaxGroupRequest $request)
    {
        $company = app(CurrentCompany::class)->getOrFail();

        $taxGroup = TaxGroup::create(array_merge($request->validated(), [
            'company_id' => $company->id,
            'created_by_user_id' => auth()->id(),
        ]));

        if ($request->filled('components')) {
            foreach ($request->components as $index => $component) {
                $taxGroup->taxGroupComponents()->create([
                    'tax_rate_id' => $component['tax_rate_id'],
                    'priority' => $component['priority'] ?? $index + 1,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Tax group created successfully.');
    }

    public function updateTaxGroup(CreateTaxGroupRequest $request, $id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxGroup = TaxGroup::forCompany($company->id)->findOrFail($id);

        $taxGroup->update($request->only([
            'code', 'name', 'is_default', 'is_active', 'description', 'jurisdiction_id'
        ]));

        if ($request->filled('components')) {
            $taxGroup->taxGroupComponents()->delete();
            foreach ($request->components as $index => $component) {
                $taxGroup->taxGroupComponents()->create([
                    'tax_rate_id' => $component['tax_rate_id'],
                    'priority' => $component['priority'] ?? $index + 1,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Tax group updated successfully.');
    }

    public function deleteTaxGroup($id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxGroup = TaxGroup::forCompany($company->id)->findOrFail($id);

        $taxGroup->delete();

        return redirect()->back()->with('success', 'Tax group deleted successfully.');
    }

    public function createTaxRegistration(CreateTaxRegistrationRequest $request)
    {
        $company = app(CurrentCompany::class)->getOrFail();

        CompanyTaxRegistration::create(array_merge($request->validated(), [
            'company_id' => $company->id,
            'created_by_user_id' => auth()->id(),
        ]));

        return redirect()->back()->with('success', 'Tax registration created successfully.');
    }

    public function updateTaxRegistration(CreateTaxRegistrationRequest $request, $id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $registration = CompanyTaxRegistration::forCompany($company->id)->findOrFail($id);

        $registration->update($request->validated());

        return redirect()->back()->with('success', 'Tax registration updated successfully.');
    }

    public function deleteTaxRegistration($id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $registration = CompanyTaxRegistration::forCompany($company->id)->findOrFail($id);
        $registration->delete();

        return redirect()->back()->with('success', 'Tax registration deleted successfully.');
    }

    public function createTaxExemption(CreateTaxExemptionRequest $request)
    {
        $company = app(CurrentCompany::class)->getOrFail();

        TaxExemption::create(array_merge($request->validated(), [
            'company_id' => $company->id,
        ]));

        return redirect()->back()->with('success', 'Tax exemption created successfully.');
    }

    public function updateTaxExemption(CreateTaxExemptionRequest $request, $id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $exemption = TaxExemption::forCompany($company->id)->findOrFail($id);

        $exemption->update($request->validated());

        return redirect()->back()->with('success', 'Tax exemption updated successfully.');
    }

    public function deleteTaxExemption($id)
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $exemption = TaxExemption::forCompany($company->id)->findOrFail($id);
        $exemption->delete();

        return redirect()->back()->with('success', 'Tax exemption deleted successfully.');
    }

    public function enableSaudiVAT()
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxSettings = CompanyTaxSettings::forCompany($company);

        $saudiJurisdiction = Jurisdiction::getSaudiArabia();

        $saudiVATRate = TaxRate::forCompany($company->id)->where('code', 'VAT-SA')->first();
        if (!$saudiVATRate) {
            $saudiVATRate = TaxRate::createSaudiVAT($company);
        }

        $saudiVATGroup = TaxGroup::forCompany($company->id)->where('code', 'VAT-SA-GROUP')->first();
        if (!$saudiVATGroup) {
            $saudiVATGroup = TaxGroup::createSaudiVATGroup($company);
            $saudiVATGroup->taxGroupComponents()->create([
                'tax_rate_id' => $saudiVATRate->id,
                'priority' => 1,
            ]);
        }

        $taxSettings->update([
            'tax_enabled' => true,
            'default_jurisdiction_id' => $saudiJurisdiction->id,
            'default_sales_tax_rate_id' => $saudiVATRate->id,
            'default_purchase_tax_rate_id' => $saudiVATRate->id,
            'tax_number_label' => 'VAT Number',
        ]);

        TaxExemption::createSaudiExemptions($company);

        return redirect()->back()->with('success', 'Saudi VAT configuration enabled successfully.');
    }

    public function getTaxRates(CalculateTaxRequest $request): JsonResponse
    {
        $company = app(CurrentCompany::class)->getOrFail();

        $taxRates = TaxRate::forCompany($company->id)
            ->active()
            ->when($request->tax_type, fn ($query, $type) => $query->ofType($type))
            ->when($request->jurisdiction_id, fn ($query, $jurisdictionId) => $query->where('jurisdiction_id', $jurisdictionId))
            ->get();

        return response()->json($taxRates);
    }

    public function getTaxGroups(CalculateTaxRequest $request): JsonResponse
    {
        $company = app(CurrentCompany::class)->getOrFail();

        $taxGroups = TaxGroup::forCompany($company->id)
            ->active()
            ->with(['taxRates'])
            ->get();

        return response()->json($taxGroups);
    }

    public function calculateTax(CalculateTaxRequest $request): JsonResponse
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $taxSettings = CompanyTaxSettings::forCompany($company);

        if (!$taxSettings || !$taxSettings->tax_enabled) {
            return response()->json(['tax_amount' => 0, 'total_amount' => $request->amount]);
        }

        $amount = $request->amount;
        $taxAmount = 0;

        if ($request->tax_rate_id) {
            $taxRate = TaxRate::forCompany($company->id)->findOrFail($request->tax_rate_id);
            $taxAmount = $taxSettings->calculateTaxAmount($amount, $taxRate->rate);
        } elseif ($request->tax_group_id) {
            $taxGroup = TaxGroup::forCompany($company->id)->findOrFail($request->tax_group_id);
            $taxAmount = $taxGroup->getTotalTax($amount, $taxSettings);
        }

        return response()->json([
            'tax_amount' => $taxAmount,
            'total_amount' => $amount + $taxAmount,
        ]);
    }
}
