<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Currency;
use App\Services\CompanyLookupService;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyCurrencyController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService,
        private CompanyLookupService $companyLookup
    ) {}

    /**
     * Authorize company access - superadmin or company member
     */
    private function authorizeCompanyAccess(Request $request, Company $company): void
    {
        $user = $request->user();

        // Superadmin can access any company
        if (! $user->isSuperAdmin()) {
            abort_unless($this->companyLookup->isMember($company->id, $user->id), 403);
        }
    }

    /**
     * Get company's currencies with exchange rates
     */
    public function index(Request $request, Company $company)
    {
        $this->authorizeCompanyAccess($request, $company);

        $settings = $company->settings;
        $currencies = $settings['currencies'] ?? [
            'base' => $company->base_currency,
            'enabled' => [$company->base_currency],
            'default_rates' => [],
            'exchange_rates' => [],
        ];

        // Load currency details
        $currencyDetails = Currency::whereIn('code', $currencies['enabled'] ?? [])->get();

        $formattedCurrencies = $currencyDetails->map(function ($currency) use ($currencies) {
            $exchangeRate = collect($currencies['exchange_rates'] ?? [])->first(function ($rate) use ($currency) {
                return $rate['currency_code'] === $currency->code;
            });

            $defaultRate = $currencies['default_rates'][$currency->code] ?? null;

            return [
                'id' => $currency->id,
                'currency' => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'numeric_code' => $currency->numeric_code,
                ],
                'is_base_currency' => $currency->code === ($currencies['base'] ?? $company->base_currency),
                'default_rate' => $defaultRate,
                'exchange_rates' => $exchangeRate ? [$exchangeRate] : [],
            ];
        });

        return response()->json(['data' => $formattedCurrencies]);
    }

    /**
     * Get available currencies that company hasn't added yet
     */
    public function available(Request $request, Company $company)
    {
        $this->authorizeCompanyAccess($request, $company);

        $settings = $company->settings;
        $currencies = $settings['currencies'] ?? [
            'base' => $company->base_currency,
            'enabled' => [$company->base_currency],
            'default_rates' => [],
            'exchange_rates' => [],
        ];

        $enabledCurrencyCodes = $currencies['enabled'] ?? [$company->base_currency];

        $availableCurrencies = Currency::whereNotIn('code', $enabledCurrencyCodes)
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $availableCurrencies]);
    }

    /**
     * Add a currency to company's settings
     */
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompanyAccess($request, $company);

        $validated = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|date',
            'is_default' => 'nullable|boolean',
        ]);

        $currency = Currency::findOrFail($validated['currency_id']);
        $settings = $company->settings;

        if (! isset($settings['currencies'])) {
            $settings['currencies'] = [
                'base' => $company->base_currency,
                'enabled' => [$company->base_currency],
                'default_rates' => [],
                'exchange_rates' => [],
            ];
        }

        // Add currency if not already enabled
        if (! in_array($currency->code, $settings['currencies']['enabled'])) {
            $settings['currencies']['enabled'][] = $currency->code;
        }

        // Add exchange rate if provided
        if (isset($validated['exchange_rate'])) {
            if (! empty($validated['is_default'])) {
                // Store as default rate
                $settings['currencies']['default_rates'][$currency->code] = [
                    'currency_code' => $currency->code,
                    'exchange_rate' => $validated['exchange_rate'],
                    'notes' => $validated['notes'] ?? 'Default rate',
                ];
            } else {
                // Store as dated exchange rate
                $rateKey = collect($settings['currencies']['exchange_rates'] ?? [])
                    ->search(fn ($rate) => $rate['currency_code'] === $currency->code);

                $rateData = [
                    'currency_code' => $currency->code,
                    'exchange_rate' => $validated['exchange_rate'],
                    'effective_date' => $validated['effective_date'] ?? now()->toDateString(),
                    'cease_date' => null,
                    'notes' => null,
                ];

                if ($rateKey !== false) {
                    $settings['currencies']['exchange_rates'][$rateKey] = $rateData;
                } else {
                    $settings['currencies']['exchange_rates'][] = $rateData;
                }
            }
        }

        $company->settings = $settings;
        $company->save();

        // Load the full currency data for the response
        $currency->is_base_currency = $currency->code === ($settings['currencies']['base'] ?? $company->base_currency);
        $currency->exchange_rates = $settings['currencies']['exchange_rates'] ?? [];

        return response()->json([
            'data' => [
                'id' => $currency->id,
                'currency' => $currency,
                'is_base_currency' => $currency->code === ($settings['currencies']['base'] ?? $company->base_currency),
                'exchange_rates' => [],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Remove a currency from company's settings
     */
    public function destroy(Request $request, Company $company, string $currencyId)
    {
        $this->authorizeCompanyAccess($request, $company);

        $currency = Currency::findOrFail($currencyId);
        $settings = $company->settings;

        if (! isset($settings['currencies'])) {
            return response()->noContent();
        }

        $currencies = $settings['currencies'];

        // Cannot remove base currency
        if ($currency->code === ($currencies['base'] ?? $company->base_currency)) {
            return response()->json([
                'message' => 'Cannot remove base currency',
            ], Response::HTTP_FORBIDDEN);
        }

        // Remove from enabled currencies
        $currencies['enabled'] = array_values(array_filter($currencies['enabled'], fn ($code) => $code !== $currency->code));

        // Remove exchange rates
        $currencies['exchange_rates'] = array_values(array_filter($currencies['exchange_rates'], fn ($rate) => $rate['currency_code'] !== $currency->code));

        $settings['currencies'] = $currencies;
        $company->settings = $settings;
        $company->save();

        return response()->noContent();
    }

    /**
     * Get exchange rate for a company currency
     */
    public function getExchangeRate(Request $request, Company $company, string $currencyId)
    {
        $this->authorizeCompanyAccess($request, $company);

        $currency = Currency::findOrFail($currencyId);
        $settings = $company->settings;

        if (! isset($settings['currencies'])) {
            return response()->json([
                'data' => null,
            ]);
        }

        // Handle action_type parameter for InlineEditable
        if ($request->has('action_type') && $request->action_type === 'update_exchange_rate') {
            // Return validation requirements for the update
            return response()->json([
                'requires_additional_info' => false,
                'can_update' => true,
            ]);
        }

        $exchangeRate = collect($settings['currencies']['exchange_rates'] ?? [])
            ->first(fn ($rate) => $rate['currency_code'] === $currency->code);

        return response()->json([
            'data' => $exchangeRate,
        ]);
    }

    /**
     * Update exchange rate for a company currency
     */
    public function updateExchangeRate(Request $request, Company $company, string $currencyId)
    {
        $this->authorizeCompanyAccess($request, $company);

        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:0',
            'effective_date' => 'nullable|date',
            'cease_date' => 'nullable|date|after:effective_date',
            'notes' => 'nullable|string|max:255',
        ]);

        $currency = Currency::findOrFail($currencyId);
        $settings = $company->settings;

        if (! isset($settings['currencies'])) {
            $settings['currencies'] = [
                'base' => $company->base_currency,
                'enabled' => [$company->base_currency],
                'default_rates' => [],
                'exchange_rates' => [],
            ];
        }

        // Ensure currency is enabled
        if (! in_array($currency->code, $settings['currencies']['enabled'])) {
            $settings['currencies']['enabled'][] = $currency->code;
        }

        // Add new rate while preserving history
        $rateData = [
            'id' => uniqid('rate_', true), // Generate unique ID for the rate
            'currency_code' => $currency->code,
            'exchange_rate' => $validated['exchange_rate'],
            'effective_date' => $validated['effective_date'] ?? now()->toDateString(),
            'cease_date' => $validated['cease_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_at' => now()->toISOString(),
        ];

        // Add the new rate to the exchange rates array
        $settings['currencies']['exchange_rates'][] = $rateData;

        // Sort rates by effective date (newest first)
        usort($settings['currencies']['exchange_rates'], function ($a, $b) {
            return strcmp($b['effective_date'], $a['effective_date']);
        });

        $company->settings = $settings;
        $company->save();

        return response()->json([
            'data' => $rateData,
        ]);
    }

    /**
     * Update a specific exchange rate
     */
    public function updateSpecificRate(Request $request, Company $company, string $currencyId, string $rateId)
    {
        $this->authorizeCompanyAccess($request, $company);

        // Handle GET request for action_type validation
        if ($request->isMethod('get') && $request->has('action_type') && $request->action_type === 'update_exchange_rate') {
            return response()->json([
                'requires_additional_info' => false,
                'can_update' => true,
            ]);
        }

        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'cease_date' => 'nullable|date|after:effective_date',
            'notes' => 'nullable|string|max:255',
        ]);

        $currency = Currency::findOrFail($currencyId);
        $settings = $company->settings;

        if (! isset($settings['currencies']) || ! isset($settings['currencies']['exchange_rates'])) {
            return response()->json([
                'message' => 'No exchange rates found for this currency',
            ], Response::HTTP_NOT_FOUND);
        }

        // Find the specific rate by ID
        $rateIndex = collect($settings['currencies']['exchange_rates'])
            ->search(fn ($rate) => ($rate['id'] ?? null) === $rateId);

        if ($rateIndex === false) {
            return response()->json([
                'message' => 'Exchange rate not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Update the rate
        $settings['currencies']['exchange_rates'][$rateIndex] = array_merge(
            $settings['currencies']['exchange_rates'][$rateIndex],
            [
                'exchange_rate' => $validated['exchange_rate'],
                'effective_date' => $validated['effective_date'],
                'cease_date' => $validated['cease_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now()->toISOString(),
            ]
        );

        // Re-sort rates by effective date
        usort($settings['currencies']['exchange_rates'], function ($a, $b) {
            return strcmp($b['effective_date'], $a['effective_date']);
        });

        $company->settings = $settings;
        $company->save();

        return response()->json([
            'data' => $settings['currencies']['exchange_rates'][$rateIndex],
        ]);
    }
}
