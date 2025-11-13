<?php

namespace App\Services;

use App\Models\TaxComponent;
use App\Models\TaxRate;
use App\Models\TaxSettings;
use App\Traits\AuditLogging;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaxCalculationService
{
    use AuditLogging;

    protected $taxSettings;

    private ServiceContext $context;

    public function __construct(ServiceContext $context)
    {
        $this->context = $context;
        $this->taxSettings = null;
    }

    /**
     * Calculate tax for a transaction
     */
    public function calculateTax($transaction, $items = null, $currency = 'USD')
    {
        // Validate company access
        $this->validateCompanyAccess($transaction->company_id);

        // Set RLS context
        $this->setRlsContext($transaction->company_id);

        $company = $transaction->company;
        $this->taxSettings = TaxSettings::getOrCreateForCompany($company->id);

        if (! $this->taxSettings || ! $this->taxSettings->auto_calculate_tax) {
            return [];
        }

        $taxComponents = [];
        $items = $items ?? $this->getTransactionItems($transaction);
        $location = $this->getTransactionLocation($transaction);
        $transactionType = $this->getTransactionType($transaction);
        $totalTaxableAmount = 0;
        $totalTaxAmount = 0;

        foreach ($items as $item) {
            $applicableTaxRates = $this->getApplicableTaxRates($item, $location, $transactionType);

            foreach ($applicableTaxRates as $taxRate) {
                $taxAmount = $this->calculateTaxForItem($item, $taxRate, $taxComponents);

                if ($taxAmount > 0) {
                    $taxComponent = TaxComponent::createFromTransaction(
                        $transaction,
                        $taxRate,
                        $item['amount'],
                        $currency,
                        $item['id'] ?? null
                    );

                    $taxComponents[] = $taxComponent;
                    $totalTaxableAmount += $item['amount'];
                    $totalTaxAmount += $taxAmount;
                }
            }
        }

        // Add tax for shipping if applicable
        if ($this->taxSettings->charge_tax_on_shipping && isset($transaction->shipping_amount)) {
            $shippingTaxComponents = $this->calculateTaxForShipping($transaction);
            $taxComponents = array_merge($taxComponents, $shippingTaxComponents);

            // Add shipping tax to totals
            foreach ($shippingTaxComponents as $component) {
                $totalTaxAmount += $component->tax_amount;
            }
        }

        // Create audit log entry
        $this->audit('tax.calculated', [
            'transaction_type' => get_class($transaction),
            'transaction_id' => $transaction->id,
            'company_id' => $transaction->company_id,
            'currency' => $currency,
            'total_taxable_amount' => $totalTaxableAmount,
            'total_tax_amount' => $totalTaxAmount,
            'tax_components_count' => count($taxComponents),
            'calculated_by_user_id' => $this->context->getUserId(),
            'location' => $location,
            'transaction_type_classification' => $transactionType,
        ]);

        Log::info('Tax calculation completed', [
            'transaction_id' => $transaction->id,
            'company_id' => $transaction->company_id,
            'user_id' => $this->context->getUserId(),
            'total_tax' => $totalTaxAmount,
            'ip' => $this->context->getIpAddress(),
        ]);

        return $taxComponents;
    }

    /**
     * Update existing tax components for a transaction
     */
    public function updateTransactionTax($transaction)
    {
        // Remove existing tax components
        TaxComponent::where('transaction_type', get_class($transaction))
            ->where('transaction_id', $transaction->id)
            ->delete();

        // Recalculate tax
        return $this->calculateTax($transaction);
    }

    /**
     * Get default tax rate for a transaction type
     */
    public function getDefaultTaxRate($transactionType = 'sales')
    {
        $companyId = $this->context->getCompanyId();

        if (! $companyId) {
            throw new \InvalidArgumentException('Company context is required for tax rate lookup');
        }

        $this->taxSettings = TaxSettings::getOrCreateForCompany($companyId);

        if ($transactionType === 'sales') {
            return $this->taxSettings->defaultSalesTaxRate;
        } else {
            return $this->taxSettings->defaultPurchaseTaxRate;
        }
    }

    /**
     * Get applicable tax rates for an item
     */
    protected function getApplicableTaxRates($item, $location, $transactionType)
    {
        $companyId = $this->context->getCompanyId();

        if (! $companyId) {
            throw new \InvalidArgumentException('Company context is required for tax rate lookup');
        }

        $query = TaxRate::where('company_id', $companyId)
            ->active()
            ->where(function ($q) use ($transactionType) {
                $q->where('tax_type', $transactionType)
                    ->orWhere('tax_type', 'both');
            });

        // Apply location-based filtering if enabled
        if ($this->taxSettings->track_tax_by_jurisdiction) {
            $query->where(function ($q) use ($location) {
                $q->whereNull('country_code')
                    ->orWhere('country_code', $location['country_code'])
                    ->when($location['state_province'], function ($q, $state) {
                        return $q->orWhere('state_province', $state);
                    })
                    ->when($location['city'], function ($q, $city) {
                        return $q->orWhere('city', $city);
                    });
            });
        }

        return $query->get();
    }

    /**
     * Calculate tax for a single item
     */
    protected function calculateTaxForItem($item, $taxRate, $existingComponents)
    {
        $baseAmount = $item['amount'];
        $taxAmount = 0;

        // Handle compound taxes
        if ($taxRate->is_compound) {
            $compoundBase = $baseAmount;

            // Add tax from previous components if this is compound
            foreach ($existingComponents as $component) {
                if ($component->taxRate->is_compound) {
                    $compoundBase += $component->tax_amount;
                }
            }

            $taxAmount = $taxRate->calculateTax($compoundBase, $baseAmount);
        } else {
            $taxAmount = $taxRate->calculateTax($baseAmount);
        }

        // Apply rounding based on settings
        if ($this->taxSettings->round_tax_per_line) {
            $taxAmount = $this->taxSettings->roundTaxAmount($taxAmount);
        }

        return $taxAmount;
    }

    /**
     * Calculate tax for shipping
     */
    protected function calculateTaxForShipping($transaction)
    {
        if (! $this->taxSettings->charge_tax_on_shipping || ! $transaction->shipping_amount) {
            return [];
        }

        $shippingItem = [
            'amount' => $transaction->shipping_amount,
            'id' => 'shipping',
        ];

        $location = $this->getTransactionLocation($transaction);
        $transactionType = $this->getTransactionType($transaction);
        $applicableTaxRates = $this->getApplicableTaxRates($shippingItem, $location, $transactionType);

        $taxComponents = [];
        foreach ($applicableTaxRates as $taxRate) {
            $taxAmount = $this->calculateTaxForItem($shippingItem, $taxRate, []);

            if ($taxAmount > 0) {
                $taxComponents[] = TaxComponent::createFromTransaction(
                    $transaction,
                    $taxRate,
                    $shippingItem['amount'],
                    $transaction->currency,
                    'shipping'
                );
            }
        }

        return $taxComponents;
    }

    /**
     * Get transaction items
     */
    protected function getTransactionItems($transaction)
    {
        $items = [];

        if (method_exists($transaction, 'lines') && $transaction->lines) {
            foreach ($transaction->lines as $line) {
                $items[] = [
                    'id' => $line->id,
                    'amount' => $line->subtotal ?? $line->amount ?? 0,
                    'description' => $line->description ?? '',
                    'tax_exempt' => $line->tax_exempt ?? false,
                ];
            }
        } else {
            // Fallback to single item
            $items[] = [
                'id' => null,
                'amount' => $transaction->subtotal ?? $transaction->amount ?? 0,
                'description' => '',
                'tax_exempt' => false,
            ];
        }

        return array_filter($items, function ($item) {
            return $item['amount'] > 0 && ! $item['tax_exempt'];
        });
    }

    /**
     * Get transaction location
     */
    protected function getTransactionLocation($transaction)
    {
        // Try to get location from customer/vendor if available
        if (method_exists($transaction, 'customer') && $transaction->customer) {
            return [
                'country_code' => $transaction->customer->country_code ?? 'US',
                'state_province' => $transaction->customer->state_province,
                'city' => $transaction->customer->city,
                'postal_code' => $transaction->customer->postal_code,
            ];
        }

        if (method_exists($transaction, 'vendor') && $transaction->vendor) {
            return [
                'country_code' => $transaction->vendor->country_code ?? 'US',
                'state_province' => $transaction->vendor->state_province,
                'city' => $transaction->vendor->city,
                'postal_code' => $transaction->vendor->postal_code,
            ];
        }

        // Fallback to company's default tax country
        return [
            'country_code' => $this->taxSettings->tax_country_code ?? 'US',
            'state_province' => null,
            'city' => null,
            'postal_code' => null,
        ];
    }

    /**
     * Get transaction type
     */
    protected function getTransactionType($transaction)
    {
        $className = class_basename($transaction);

        if (in_array($className, ['Invoice', 'SalesInvoice', 'CustomerInvoice'])) {
            return 'sales';
        }

        if (in_array($className, ['Bill', 'PurchaseBill', 'VendorBill'])) {
            return 'purchase';
        }

        return 'sales'; // Default to sales
    }

    /**
     * Calculate tax summary for an array of tax components
     */
    public function calculateTaxSummary($taxComponents)
    {
        $summary = [
            'total_taxable_amount' => 0,
            'total_tax_amount' => 0,
            'tax_rates' => [],
        ];

        foreach ($taxComponents as $component) {
            $summary['total_taxable_amount'] += $component->taxable_amount;
            $summary['total_tax_amount'] += $component->tax_amount;

            $rateId = $component->tax_rate_id;
            if (! isset($summary['tax_rates'][$rateId])) {
                $summary['tax_rates'][$rateId] = [
                    'tax_rate' => $component->taxRate,
                    'taxable_amount' => 0,
                    'tax_amount' => 0,
                ];
            }

            $summary['tax_rates'][$rateId]['taxable_amount'] += $component->taxable_amount;
            $summary['tax_rates'][$rateId]['tax_amount'] += $component->tax_amount;
        }

        return $summary;
    }

    /**
     * Validate user can access the company
     */
    private function validateCompanyAccess(string $companyId): void
    {
        $user = $this->context->getUser();

        if (! $user) {
            throw new \InvalidArgumentException('User context is required');
        }

        // Check if user belongs to this company
        if (! $user->companies()->where('company_id', $companyId)->exists()) {
            throw new \InvalidArgumentException('User does not have access to this company');
        }

        // Additional validation for active company membership
        $companyMembership = $user->companies()
            ->where('company_id', $companyId)
            ->wherePivot('is_active', true)
            ->first();

        if (! $companyMembership) {
            throw new \InvalidArgumentException('User access to this company is not active');
        }
    }

    /**
     * Set RLS context for database operations
     */
    private function setRlsContext(string $companyId): void
    {
        DB::statement('SET app.current_company_id = ?', [$companyId]);
        DB::statement('SET app.current_user_id = ?', [$this->context->getUserId()]);
    }
}
