<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => $this->faker->uuid(),
            'invoice_number' => 'INV-'.$this->faker->year().'-'.$this->faker->unique()->numerify('####'),
            'issue_date' => $this->faker->date(),
            'due_date' => $this->faker->dateBetween('+1 week', '+1 month'),
            'status' => $this->faker->randomElement(['draft', 'sent', 'paid', 'overdue', 'cancelled']),
            'currency' => $this->faker->currencyCode(),
            'subtotal' => $this->faker->randomFloat(2, 100, 10000),
            'tax_amount' => $this->faker->randomFloat(2, 0, 1000),
            'discount_amount' => $this->faker->randomFloat(2, 0, 500),
            'total_amount' => $this->faker->randomFloat(2, 100, 12000),
            'balance_due' => $this->faker->randomFloat(2, 0, 12000),
            'notes' => $this->faker->sentence(),
            'terms' => $this->faker->sentence(10),
            'payment_status' => $this->faker->randomElement(['unpaid', 'partially_paid', 'paid']),
            'created_by_user_id' => User::factory(),
        ];
    }
}
