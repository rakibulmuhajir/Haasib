<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\DestroyCompanyCurrencyRequest;
use App\Http\Requests\Company\StoreCompanyCurrencyRequest;
use App\Http\Requests\Company\UpdateCompanyCurrencyRequest;
use App\Models\CompanyCurrency;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CompanyCurrencyController extends Controller
{
    public function store(StoreCompanyCurrencyRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        CompanyCurrency::create([
            'company_id' => $company->id,
            'currency_code' => $request->validated('currency_code'),
            'exchange_rate' => $request->validated('exchange_rate'),
            'enabled_at' => now(),
        ]);

        return back()->with('success', 'Currency enabled successfully.');
    }

    public function update(UpdateCompanyCurrencyRequest $request, string $companySlug, string $currency): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = CompanyCurrency::where('company_id', $company->id)->findOrFail($currency);
        $record->update(['exchange_rate' => $request->validated('exchange_rate')]);

        return back()->with('success', 'Exchange rate updated successfully.');
    }

    public function destroy(DestroyCompanyCurrencyRequest $request, string $companySlug, string $currency): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = CompanyCurrency::where('company_id', $company->id)->findOrFail($currency);
        $inUse = DB::table('acct.accounts')->where('company_id', $company->id)->where('currency', $record->currency_code)->exists()
            || DB::table('acct.transactions')->where('company_id', $company->id)->where('currency', $record->currency_code)->exists();
        if ($inUse) {
            throw ValidationException::withMessages(['currency_code' => 'This currency is already used by an account or transaction.']);
        }
        $record->delete();

        return back()->with('success', 'Currency disabled successfully.');
    }
}
