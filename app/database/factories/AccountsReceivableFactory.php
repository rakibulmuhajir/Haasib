<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountsReceivable>
 */
class AccountsReceivableFactory extends Factory
{
    protected $model = \App\Models\$1::class;

    public function definition(): array
    {
        $company = Company::inRandomOrder()->first() ?? Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $currency = Currency::where('code', 'USD')->first() ?? Currency::factory()->create(['code' => 'USD']);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
        ]);

        $amountDue = fake()->randomFloat(2, 50, 10000);
        $dueDate = fake()->dateTimeBetween('-60 days', '+30 days');

        return [
            'ar_id' => fake()->uuid(),
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount_due' => $amountDue,
            'original_amount' => $invoice->total_amount,
            'currency_id' => $currency->id,
            'due_date' => $dueDate,
            'days_overdue' => 0,
            'aging_category' => 'current',
            'last_calculated_at' => now(),
            'metadata' => [
                'created_by' => 'factory',
                'source' => 'invoice_sync',
                'invoice_status' => $invoice->status,
            ],
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('tomorrow', '+30 days'),
            'days_overdue' => 0,
            'aging_category' => 'current',
        ]);
    }

    public function overdue(): static
    {
        $daysOverdue = fake()->numberBetween(1, 365);

        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($daysOverdue),
            'days_overdue' => $daysOverdue,
            'aging_category' => $this->determineAgingCategory($daysOverdue),
        ]);
    }

    public function oneToThirtyDays(): static
    {
        $daysOverdue = fake()->numberBetween(1, 30);

        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($daysOverdue),
            'days_overdue' => $daysOverdue,
            'aging_category' => '1-30',
        ]);
    }

    public function thirtyOneToSixtyDays(): static
    {
        $daysOverdue = fake()->numberBetween(31, 60);

        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($daysOverdue),
            'days_overdue' => $daysOverdue,
            'aging_category' => '31-60',
        ]);
    }

    public function sixtyOneToNinetyDays(): static
    {
        $daysOverdue = fake()->numberBetween(61, 90);

        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($daysOverdue),
            'days_overdue' => $daysOverdue,
            'aging_category' => '61-90',
        ]);
    }

    public function ninetyPlusDays(): static
    {
        $daysOverdue = fake()->numberBetween(91, 365);

        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($daysOverdue),
            'days_overdue' => $daysOverdue,
            'aging_category' => '90+',
        ]);
    }

    public function critical(): static
    {
        $daysOverdue = fake()->numberBetween(180, 365);

        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($daysOverdue),
            'days_overdue' => $daysOverdue,
            'aging_category' => '90+',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'risk_level' => 'critical',
                'collection_action_required' => true,
            ]),
        ]);
    }

    public function highRisk(): static
    {
        return $this->overdue()->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'risk_level' => 'high',
                'follow_up_required' => true,
            ]),
        ]);
    }

    public function mediumRisk(): static
    {
        return $this->overdue()->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'risk_level' => 'medium',
                'monitor_closely' => true,
            ]),
        ]);
    }

    public function lowRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'risk_level' => 'low',
            ]),
        ]);
    }

    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_due' => fake()->randomFloat(2, 10000, 100000),
            'original_amount' => fn ($attributes) => $attributes['amount_due'] * 1.2,
        ]);
    }

    public function mediumValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_due' => fake()->randomFloat(2, 1000, 10000),
            'original_amount' => fn ($attributes) => $attributes['amount_due'] * 1.15,
        ]);
    }

    public function lowValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_due' => fake()->randomFloat(2, 50, 1000),
            'original_amount' => fn ($attributes) => $attributes['amount_due'] * 1.1,
        ]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'customer_id' => Customer::factory()->create(['company_id' => $company->id])->id,
            'invoice_id' => Invoice::factory()->create(['company_id' => $company->id])->id,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'invoice_id' => Invoice::factory()->create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
            ])->id,
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $invoice->company_id,
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'original_amount' => $invoice->total_amount,
            'currency_id' => $invoice->currency_id,
            'due_date' => $invoice->due_date,
        ]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_due' => fn ($attributes) => $attributes['original_amount'] * fake()->randomFloat(2, 0.1, 0.9),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'payment_status' => 'partial',
                'payment_percentage' => fake()->randomFloat(2, 10, 90),
            ]),
        ]);
    }

    public function nearlyPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_due' => fn ($attributes) => $attributes['original_amount'] * fake()->randomFloat(2, 0.01, 0.1),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'payment_status' => 'nearly_paid',
                'payment_percentage' => fake()->randomFloat(2, 90, 99),
            ]),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_due' => fn ($attributes) => $attributes['original_amount'],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'payment_status' => 'unpaid',
                'payment_percentage' => 0,
            ]),
        ]);
    }

    public function writeOffCandidate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays(fake()->numberBetween(180, 365)),
            'days_overdue' => fake()->numberBetween(180, 365),
            'aging_category' => '90+',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'write_off_candidate' => true,
                'write_off_recommendation' => 'bad_debt',
                'collection_attempts' => fake()->numberBetween(3, 10),
            ]),
        ]);
    }

    public function inCollections(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays(fake()->numberBetween(90, 365)),
            'days_overdue' => fake()->numberBetween(90, 365),
            'aging_category' => '90+',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'in_collections' => true,
                'collections_agency' => fake()->company().' Collections',
                'collections_date' => now()->subDays(fake()->numberBetween(1, 30))->toISOString(),
            ]),
        ]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'disputed' => true,
                'dispute_reason' => fake()->randomElement([
                    'goods_not_received',
                    'services_not_rendered',
                    'quality_issues',
                    'pricing_discrepancy',
                    'contract_dispute',
                ]),
                'dispute_date' => now()->subDays(fake()->numberBetween(1, 60))->toISOString(),
                'dispute_status' => fake()->randomElement(['under_review', 'investigating', 'resolved']),
            ]),
        ]);
    }

    public function withPaymentPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'payment_plan' => true,
                'payment_plan_amount' => fake()->randomFloat(2, 100, 1000),
                'payment_plan_frequency' => fake()->randomElement(['weekly', 'bi_weekly', 'monthly']),
                'payment_plan_start_date' => now()->addDays(fake()->numberBetween(1, 30))->toISOString(),
            ]),
        ]);
    }

    private function determineAgingCategory(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'current';
        }

        if ($daysOverdue <= 30) {
            return '1-30';
        }

        if ($daysOverdue <= 60) {
            return '31-60';
        }

        if ($daysOverdue <= 90) {
            return '61-90';
        }

        return '90+';
    }
}
