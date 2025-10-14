<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'payment_number' => 'PAY-' . $this->faker->unique()->numberBetween(1000, 9999),
            'payment_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'payment_method' => $this->faker->randomElement(['cash', 'check', 'credit_card', 'bank_transfer', 'online']),
            'reference_number' => $this->faker->optional(0.7)->numerify('REF-#######'),
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'currency' => 'USD',
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'cancelled']),
            'notes' => $this->faker->optional(0.6)->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the payment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'remaining_amount' => $attributes['amount'] * 0.1, // 90% allocated
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'remaining_amount' => $attributes['amount'], // No allocations yet
        ]);
    }

    /**
     * Indicate that the payment is fully allocated.
     */
    public function fullyAllocated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'remaining_amount' => 0, // Fully allocated
        ]);
    }

    /**
     * Create a payment for a specific company and customer.
     */
    public function for(Company $company, Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);
    }
}
