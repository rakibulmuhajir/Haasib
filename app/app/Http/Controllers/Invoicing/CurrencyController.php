<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    /**
     * Display a listing of currencies.
     */
    public function index(Request $request)
    {
        $query = Currency::query();

        // Apply filters
        if ($request->filled('status')) {
            if ($request->status === 'enabled') {
                $query->whereHas('companies', function ($q) use ($request) {
                    $q->where('company_id', $request->user()->current_company_id);
                });
            } elseif ($request->status === 'disabled') {
                $query->whereDoesntHave('companies', function ($q) use ($request) {
                    $q->where('company_id', $request->user()->current_company_id);
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');

        if (in_array($sortBy, ['name', 'code', 'created_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $currencies = $query->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get company currencies
        $companyCurrencies = Currency::whereHas('companies', function ($q) use ($request) {
            $q->where('company_id', $request->user()->current_company_id);
        })->get(['id', 'code', 'name', 'symbol']);

        return Inertia::render('Invoicing/Currencies/Index', [
            'currencies' => $currencies,
            'companyCurrencies' => $companyCurrencies,
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Display exchange rates.
     */
    public function exchangeRates(Request $request)
    {
        $company = $request->user()->current_company;

        // Get company base currency
        $baseCurrency = $company->baseCurrency;

        // Get exchange rates for company currencies
        $exchangeRates = ExchangeRate::where('from_currency_id', $baseCurrency->id)
            ->whereIn('to_currency_id', $company->currencies->pluck('id'))
            ->with(['fromCurrency', 'toCurrency'])
            ->orderBy('date', 'desc')
            ->paginate($request->input('per_page', 15));

        // Get all currencies for the exchange rate selector
        $availableCurrencies = Currency::orderBy('name')
            ->get(['id', 'code', 'name', 'symbol']);

        return Inertia::render('Invoicing/Currencies/ExchangeRates', [
            'exchangeRates' => $exchangeRates,
            'baseCurrency' => $baseCurrency,
            'companyCurrencies' => $company->currencies,
            'availableCurrencies' => $availableCurrencies,
        ]);
    }

    /**
     * Enable currency for company.
     */
    public function enable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_id' => 'required|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $currency = Currency::findOrFail($request->currency_id);
            $company = $request->user()->current_company;

            $this->currencyService->enableCurrencyForCompany($company, $currency);

            return back()->with('success', 'Currency enabled successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to enable currency', [
                'error' => $e->getMessage(),
                'currency_id' => $request->currency_id,
                'company_id' => $request->user()->current_company_id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to enable currency. '.$e->getMessage());
        }
    }

    /**
     * Disable currency for company.
     */
    public function disable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_id' => 'required|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $currency = Currency::findOrFail($request->currency_id);
            $company = $request->user()->current_company;

            $this->currencyService->disableCurrencyForCompany($company, $currency);

            return back()->with('success', 'Currency disabled successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to disable currency', [
                'error' => $e->getMessage(),
                'currency_id' => $request->currency_id,
                'company_id' => $request->user()->current_company_id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to disable currency. '.$e->getMessage());
        }
    }

    /**
     * Update exchange rate.
     */
    public function updateRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency_id' => 'required|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id|different:from_currency_id',
            'rate' => 'required|numeric|min:0.000001|max:999999.999999',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->currencyService->updateExchangeRate(
                fromCurrencyId: $request->from_currency_id,
                toCurrencyId: $request->to_currency_id,
                rate: $request->rate,
                date: $request->date,
                companyId: $request->user()->current_company_id,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return back()->with('success', 'Exchange rate updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update exchange rate', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to update exchange rate. '.$e->getMessage());
        }
    }

    /**
     * Sync exchange rates from external source.
     */
    public function syncRates(Request $request)
    {
        try {
            $company = $request->user()->current_company;
            $result = $this->currencyService->syncExchangeRates($company);

            if ($result['success']) {
                return back()->with('success',
                    "Exchange rates synchronized successfully. Updated {$result['updated_rates']} rates."
                );
            } else {
                return back()->with('error', $result['message']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync exchange rates', [
                'error' => $e->getMessage(),
                'company_id' => $request->user()->current_company_id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to sync exchange rates. '.$e->getMessage());
        }
    }
}
