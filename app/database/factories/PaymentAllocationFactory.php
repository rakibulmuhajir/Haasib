<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = \App\Models\PaymentAllocation::class;

    public function definition(): array
    {
        $payment = Payment::factory()->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $payment->company_id,
            'customer_id' => $payment->customer_id,
            'total_amount' => fake()->randomFloat(2, 100, 5000),
            'balance_due' => fake()->randomFloat(2, 50, 2000),
        ]);

        $maxAllocation = min($payment->amount, $invoice->balance_due);
        $amount = fake()->randomFloat(2, 10, $maxAllocation);

        return [
            'id' => fake()->uuid(),
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'status' => 'active',
            'allocation_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => fake()->optional()->sentence(),
            'metadata' => [
                'created_by' => 'factory',
                'allocation_method' => fake()->randomElement(['manual', 'automatic', 'batch']),
            ],
        ];
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_id' => $payment->id,
            'invoice_id' => Invoice::factory()->create([
                'company_id' => $payment->company_id,
                'customer_id' => $payment->customer_id,
            ])->id,
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
            'payment_id' => Payment::factory()->create([
                'company_id' => $invoice->company_id,
                'customer_id' => $invoice->customer_id,
                'amount' => fake()->randomFloat(2, 100, $invoice->balance_due),
            ])->id,
        ]);
    }

    public function fullAllocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => min($attributes['payment_id'] ? Payment::find($attributes['payment_id'])->amount : 1000,
                $attributes['invoice_id'] ? Invoice::find($attributes['invoice_id'])->balance_due : 1000),
        ]);
    }

    public function partialAllocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fn ($attributes) => min(
                $attributes['payment_id'] ? Payment::find($attributes['payment_id'])->amount * 0.7 : 700,
                $attributes['invoice_id'] ? Invoice::find($attributes['invoice_id'])->balance_due * 0.7 : 700
            ),
        ]);
    }

    public function minimumAllocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 1, 50),
        ]);
    }

    public function maximumAllocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fn ($attributes) => min(
                $attributes['payment_id'] ? Payment::find($attributes['payment_id'])->amount : 5000,
                $attributes['invoice_id'] ? Invoice::find($attributes['invoice_id'])->balance_due : 5000
            ),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_date' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_date' => fake()->dateTimeBetween('-6 months', '-3 months'),
        ]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    public function void(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'void',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'voided_at' => now()->toISOString(),
                'void_reason' => $reason ?? fake()->randomElement([
                    'error_in_allocation',
                    'customer_request',
                    'duplicate_allocation',
                    'system_error',
                ]),
            ]),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'refunded_at' => now()->toISOString(),
                'refund_reason' => fake()->randomElement([
                    'customer_refund',
                    'overpayment',
                    'error_correction',
                    'service_cancellation',
                ]),
            ]),
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'allocation_method' => 'manual',
                'allocated_by' => 'user_'.fake()->numerify('####'),
            ]),
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'allocation_method' => 'automatic',
                'allocation_system' => 'auto_allocation_engine',
            ]),
        ]);
    }

    public function batch(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'allocation_method' => 'batch',
                'batch_id' => 'batch_'.fake()->numerify('######'),
            ]),
        ]);
    }

    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 1000, 50000),
        ]);
    }

    public function lowValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 1, 100),
        ]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_date' => now()->toDateString(),
        ]);
    }

    public function yesterday(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function lastWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_date' => now()->subWeek()->toDateString(),
        ]);
    }

    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_date' => now()->subMonth()->toDateString(),
        ]);
    }

    public function withAllocationReference(string $reference): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'allocation_reference' => $reference,
            ]),
        ]);
    }

    public function withAuditTrail(array $auditData): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'audit_trail' => $auditData,
            ]),
        ]);
    }
}
