<?php

namespace Database\Factories;

use App\Models\PaymentAllocation;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = PaymentAllocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null, // Will be set in the factory
            'payment_id' => Payment::factory(),
            'invoice_id' => Invoice::factory(),
            'allocated_amount' => $this->faker->randomFloat(2, 10, 1000),
            'allocation_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'allocation_method' => $this->faker->randomElement(['manual', 'automatic', 'fifo', 'proportional']),
            'allocation_strategy' => $this->faker->optional(0.7)->randomElement(['fifo', 'due_date', 'amount', 'custom']),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'created_by_user_id' => null, // Will be set in the factory
        ];
    }

    /**
     * Indicate that the allocation is active (not reversed).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'reversed_at' => null,
            'reversal_reason' => null,
            'reversed_by_user_id' => null,
        ]);
    }

    /**
     * Indicate that the allocation is reversed.
     */
    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => [
            'reversed_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'reversal_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Create an allocation for a specific payment.
     */
    public function forPayment(Payment $payment): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $payment->company_id,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Create an allocation for a specific invoice.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Create an allocation using FIFO strategy.
     */
    public function fifo(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_method' => 'automatic',
            'allocation_strategy' => 'fifo',
        ]);
    }

    /**
     * Create an allocation using proportional strategy.
     */
    public function proportional(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_method' => 'automatic',
            'allocation_strategy' => 'proportional',
        ]);
    }

    /**
     * Create a manual allocation.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_method' => 'manual',
            'allocation_strategy' => null,
        ]);
    }
}
