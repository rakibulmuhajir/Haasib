<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TaxCalculator
{
    private array $taxPresets = [
        'ae_vat' => [
            'name' => 'UAE VAT',
            'rate' => 0.05,
            'description' => 'United Arab Emirates Value Added Tax',
            'country' => 'AE',
            'is_recoverable' => true,
        ],
        'pk_gst' => [
            'name' => 'Pakistan GST',
            'rate' => 0.18,
            'description' => 'Pakistan General Sales Tax',
            'country' => 'PK',
            'is_recoverable' => true,
        ],
        'pk_standard' => [
            'name' => 'Pakistan Standard Sales Tax',
            'rate' => 0.17,
            'description' => 'Pakistan Standard Sales Tax',
            'country' => 'PK',
            'is_recoverable' => true,
        ],
        'zero_rated' => [
            'name' => 'Zero Rated',
            'rate' => 0.00,
            'description' => 'Zero Rated Tax',
            'is_recoverable' => true,
        ],
        'exempt' => [
            'name' => 'Exempt',
            'rate' => 0.00,
            'description' => 'Tax Exempt',
            'is_recoverable' => false,
        ],
    ];

    private function logAudit(string $action, array $params, ?User $user = null, ?string $companyId = null, ?string $idempotencyKey = null, ?array $result = null): void
    {
        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('audit.audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function calculateItemTax(
        InvoiceItem $item,
        array $taxRules,
        bool $taxInclusive = false
    ): array {
        $itemAmount = Money::of($item->quantity * $item->unit_price, $item->invoice->currency->code);
        $discountAmount = Money::of($item->discount_amount ?? 0, $item->invoice->currency->code);
        $taxableAmount = $itemAmount->minus($discountAmount);

        $taxCalculations = [];
        $totalTaxAmount = Money::of(0, $item->invoice->currency->code);

        foreach ($taxRules as $taxRule) {
            $taxRate = $this->getTaxRate($taxRule, $item->invoice);
            $taxAmount = $this->calculateTaxAmount($taxableAmount, $taxRate, $taxInclusive);

            if ($taxAmount->isGreaterThan(Money::of(0, $item->invoice->currency->code))) {
                $taxCalculations[] = [
                    'tax_name' => $taxRule['name'] ?? 'Tax',
                    'rate' => $taxRate,
                    'tax_amount' => $taxAmount->getAmount()->toFloat(),
                    'is_recoverable' => $taxRule['is_recoverable'] ?? true,
                ];
                $totalTaxAmount = $totalTaxAmount->plus($taxAmount);
            }
        }

        return [
            'item_id' => $item->id,
            'taxable_amount' => $taxableAmount->getAmount()->toFloat(),
            'tax_inclusive' => $taxInclusive,
            'tax_breakdown' => $taxCalculations,
            'total_tax_amount' => $totalTaxAmount->getAmount()->toFloat(),
        ];
    }

    public function calculateInvoiceTaxes(Invoice $invoice): array
    {
        $taxSummary = [];
        $totalInvoiceTax = Money::of(0, $invoice->currency->code);

        foreach ($invoice->items as $item) {
            $applicableTaxRules = $this->getApplicableTaxRules($invoice->company, $item);

            if (! empty($applicableTaxRules)) {
                $itemTaxCalculation = $this->calculateItemTax(
                    $item,
                    $applicableTaxRules,
                    $item->tax_inclusive ?? false
                );

                foreach ($itemTaxCalculation['tax_breakdown'] as $taxBreakdown) {
                    $taxKey = $taxBreakdown['tax_name'].'_'.($taxBreakdown['rate'] * 100);

                    if (! isset($taxSummary[$taxKey])) {
                        $taxSummary[$taxKey] = [
                            'tax_name' => $taxBreakdown['tax_name'],
                            'rate' => $taxBreakdown['rate'],
                            'total_taxable_amount' => 0,
                            'total_tax_amount' => 0,
                            'is_recoverable' => $taxBreakdown['is_recoverable'],
                        ];
                    }

                    $taxSummary[$taxKey]['total_taxable_amount'] += $itemTaxCalculation['taxable_amount'];
                    $taxSummary[$taxKey]['total_tax_amount'] += $taxBreakdown['tax_amount'];
                }

                $totalInvoiceTax = $totalInvoiceTax->plus(
                    Money::of($itemTaxCalculation['total_tax_amount'], $invoice->currency->code)
                );
            }
        }

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'currency' => $invoice->currency->code,
            'total_taxable_amount' => $invoice->subtotal,
            'total_tax_amount' => $totalInvoiceTax->getAmount()->toFloat(),
            'tax_breakdown' => array_values($taxSummary),
            'tax_inclusive_total' => $invoice->subtotal + $totalInvoiceTax->getAmount()->toFloat(),
        ];
    }

    public function createTaxRule(
        Company $company,
        string $name,
        float $rate,
        ?string $description = null,
        ?string $country = null,
        bool $isRecoverable = true,
        ?string $preset = null,
        ?array $conditions = null
    ): TaxRule {
        $result = DB::transaction(function () use ($company, $name, $rate, $description, $country, $isRecoverable, $preset, $conditions) {
            $taxRule = new TaxRule([
                'company_id' => $company->id,
                'name' => $name,
                'rate' => $rate,
                'description' => $description,
                'country' => $country,
                'is_recoverable' => $isRecoverable,
                'preset' => $preset,
                'conditions' => $conditions,
            ]);

            $taxRule->save();

            return $taxRule;
        });

        $this->logAudit('tax.rule.create', [
            'company_id' => $company->id,
            'name' => $name,
            'rate' => $rate,
            'country' => $country,
            'preset' => $preset,
        ], auth()->user(), $company->id, result: ['tax_rule_id' => $result->id]);

        return $result;
    }

    public function applyTaxPreset(Company $company, string $presetCode): ?TaxRule
    {
        if (! isset($this->taxPresets[$presetCode])) {
            throw new \InvalidArgumentException("Invalid tax preset: {$presetCode}");
        }

        $preset = $this->taxPresets[$presetCode];

        $existingRule = TaxRule::where('company_id', $company->id)
            ->where('preset', $presetCode)
            ->first();

        if ($existingRule) {
            return $existingRule;
        }

        return $this->createTaxRule(
            $company,
            $preset['name'],
            $preset['rate'],
            $preset['description'],
            $preset['country'],
            $preset['is_recoverable'],
            $presetCode
        );
    }

    public function getTaxRate(array $taxRule, Invoice $invoice): float
    {
        if (isset($taxRule['conditions'])) {
            foreach ($taxRule['conditions'] as $condition) {
                if (! $this->evaluateTaxCondition($condition, $invoice)) {
                    return 0.0;
                }
            }
        }

        return $taxRule['rate'] ?? 0.0;
    }

    public function calculateTaxAmount(Money $taxableAmount, float $taxRate, bool $taxInclusive): Money
    {
        if ($taxInclusive) {
            return $taxableAmount->minus($taxableAmount->dividedBy(1 + $taxRate));
        }

        return $taxableAmount->multipliedBy($taxRate);
    }

    public function getApplicableTaxRules(Company $company, InvoiceItem $item): array
    {
        $companyTaxRules = TaxRule::where('company_id', $company->id)
            ->where('active', true)
            ->get();

        $applicableRules = [];

        foreach ($companyTaxRules as $rule) {
            if ($this->isTaxRuleApplicable($rule, $item)) {
                $applicableRules[] = [
                    'name' => $rule->name,
                    'rate' => $rule->rate,
                    'is_recoverable' => $rule->is_recoverable,
                    'conditions' => $rule->conditions,
                ];
            }
        }

        if (empty($applicableRules)) {
            $defaultPreset = $company->settings['default_tax_preset'] ?? null;
            if ($defaultPreset && isset($this->taxPresets[$defaultPreset])) {
                $preset = $this->taxPresets[$defaultPreset];
                $applicableRules[] = [
                    'name' => $preset['name'],
                    'rate' => $preset['rate'],
                    'is_recoverable' => $preset['is_recoverable'],
                ];
            }
        }

        return $applicableRules;
    }

    public function generateTaxReport(Company $company, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'posted', 'partial', 'paid']);

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        $invoices = $query->with(['items.taxes', 'currency'])->get();

        $taxReport = [
            'total_invoices' => $invoices->count(),
            'total_taxable_amount' => 0,
            'total_tax_amount' => 0,
            'total_recoverable_tax' => 0,
            'tax_by_country' => [],
            'tax_by_rate' => [],
        ];

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $itemTax = $this->calculateItemTax($item, $this->getApplicableTaxRules($company, $item), $item->tax_inclusive ?? false);

                $taxReport['total_taxable_amount'] += $itemTax['taxable_amount'];
                $taxReport['total_tax_amount'] += $itemTax['total_tax_amount'];

                foreach ($itemTax['tax_breakdown'] as $taxBreakdown) {
                    if ($taxBreakdown['is_recoverable']) {
                        $taxReport['total_recoverable_tax'] += $taxBreakdown['tax_amount'];
                    }

                    $rateKey = ($taxBreakdown['rate'] * 100).'%';
                    if (! isset($taxReport['tax_by_rate'][$rateKey])) {
                        $taxReport['tax_by_rate'][$rateKey] = 0;
                    }
                    $taxReport['tax_by_rate'][$rateKey] += $taxBreakdown['tax_amount'];
                }
            }
        }

        return $taxReport;
    }

    public function getTaxPresets(): array
    {
        return $this->taxPresets;
    }

    public function getTaxPresetsByCountry(string $countryCode): array
    {
        return array_filter($this->taxPresets, fn ($preset) => ($preset['country'] ?? null) === $countryCode);
    }

    public function validateTaxRuleData(array $data): void
    {
        if (! isset($data['name']) || empty(trim($data['name']))) {
            throw new \InvalidArgumentException('Tax rule name is required');
        }

        if (! isset($data['rate']) || $data['rate'] < 0 || $data['rate'] > 1) {
            throw new \InvalidArgumentException('Tax rate must be between 0 and 1 (0-100%)');
        }

        if (isset($data['preset']) && ! isset($this->taxPresets[$data['preset']])) {
            throw new \InvalidArgumentException('Invalid tax preset');
        }
    }

    public function reverseCalculateTaxAmount(Money $totalAmount, float $taxRate): array
    {
        $taxAmount = $totalAmount->minus($totalAmount->dividedBy(1 + $taxRate));
        $baseAmount = $totalAmount->minus($taxAmount);

        return [
            'base_amount' => $baseAmount->getAmount()->toFloat(),
            'tax_amount' => $taxAmount->getAmount()->toFloat(),
            'total_amount' => $totalAmount->getAmount()->toFloat(),
        ];
    }

    public function getTaxDeadlines(string $countryCode): array
    {
        $deadlines = [
            'AE' => [
                'vat' => [
                    'name' => 'VAT Return',
                    'frequency' => 'quarterly',
                    'due_day' => 28,
                    'description' => 'UAE VAT returns are due on the 28th day following the end of each tax period',
                ],
            ],
            'PK' => [
                'gst' => [
                    'name' => 'GST Return',
                    'frequency' => 'monthly',
                    'due_day' => 15,
                    'description' => 'Pakistan GST returns are due on the 15th of each month for the previous month',
                ],
            ],
        ];

        return $deadlines[$countryCode] ?? [];
    }

    public function getNextTaxDeadline(Company $company): ?array
    {
        $countryCode = $company->country_code ?? 'AE';
        $deadlines = $this->getTaxDeadlines($countryCode);

        if (empty($deadlines)) {
            return null;
        }

        $nextDeadline = null;
        $nextDeadlineDate = null;

        foreach ($deadlines as $deadline) {
            $deadlineDate = $this->calculateNextDeadlineDate($deadline);

            if (! $nextDeadlineDate || $deadlineDate < $nextDeadlineDate) {
                $nextDeadline = $deadline;
                $nextDeadlineDate = $deadlineDate;
            }
        }

        if ($nextDeadline) {
            $nextDeadline['next_deadline'] = $nextDeadlineDate->format('Y-m-d');
            $nextDeadline['days_until_deadline'] = now()->diffInDays($nextDeadlineDate);
        }

        return $nextDeadline;
    }

    private function evaluateTaxCondition(array $condition, Invoice $invoice): bool
    {
        switch ($condition['type'] ?? null) {
            case 'invoice_amount':
                $amount = $invoice->total_amount;

                return $this->evaluateNumericCondition($amount, $condition);

            case 'customer_type':
                $customerType = $invoice->customer->customer_type ?? 'standard';

                return $customerType === $condition['value'];

            case 'country':
                $country = $invoice->customer->country ?? $invoice->company->country_code;

                return $country === $condition['value'];

            case 'date_range':
                $invoiceDate = $invoice->invoice_date;

                return $invoiceDate >= $condition['start_date'] && $invoiceDate <= $condition['end_date'];

            default:
                return true;
        }
    }

    private function evaluateNumericCondition(float $value, array $condition): bool
    {
        $operator = $condition['operator'] ?? '>=';
        $compareValue = $condition['value'] ?? 0;

        return match ($operator) {
            '>' => $value > $compareValue,
            '>=' => $value >= $compareValue,
            '<' => $value < $compareValue,
            '<=' => $value <= $compareValue,
            '==' => $value == $compareValue,
            '!=' => $value != $compareValue,
            default => true,
        };
    }

    private function isTaxRuleApplicable(TaxRule $rule, InvoiceItem $item): bool
    {
        if (! $rule->active) {
            return false;
        }

        if ($rule->conditions) {
            foreach ($rule->conditions as $condition) {
                if (! $this->evaluateTaxCondition($condition, $item->invoice)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function calculateNextDeadlineDate(array $deadline): \Illuminate\Support\Carbon
    {
        $now = now();
        $dueDay = $deadline['due_day'] ?? 28;

        switch ($deadline['frequency']) {
            case 'monthly':
                $nextDate = $now->startOfMonth()->addMonth()->day($dueDay);
                if ($nextDate->isPast()) {
                    $nextDate = $nextDate->addMonth();
                }
                break;

            case 'quarterly':
                $currentQuarter = ceil($now->month / 3);
                $nextQuarter = $currentQuarter === 4 ? 1 : $currentQuarter + 1;
                $nextYear = $currentQuarter === 4 ? $now->year + 1 : $now->year;
                $nextDate = \Illuminate\Support\Carbon::create($nextYear, ($nextQuarter - 1) * 3 + 1, $dueDay);
                if ($nextDate->isPast()) {
                    $nextDate = $nextDate->addMonths(3);
                }
                break;

            default:
                $nextDate = $now->addMonth()->day($dueDay);
        }

        return $nextDate;
    }
}
