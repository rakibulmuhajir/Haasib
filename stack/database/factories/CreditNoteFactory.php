<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['company_id' => $company->id]);

        return [
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($company->id),
            'reason' => $this->faker->randomElement([
                'Returned goods',
                'Service discount',
                'Price adjustment',
                'Billing error correction',
                'Customer credit',
            ]),
            'amount' => $this->faker->randomFloat(2, 50, 2000),
            'tax_amount' => $this->faker->randomFloat(2, 5, 200),
            'total_amount' => fn (array $attributes) => $attributes['amount'] + ($attributes['tax_amount'] ?? 0),
            'currency' => $invoice->currency,
            'status' => $this->faker->randomElement(['draft', 'posted']),
            'notes' => $this->faker->optional(0.7)->sentence(),
            'terms' => $this->faker->optional(0.5)->sentence(),
            'created_by_user_id' => $user->id,
        ];
    }

    /**
     * Indicate that the credit note is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'posted_at' => null,
        ]);
    }

    /**
     * Indicate that the credit note is posted.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }

    /**
     * Indicate that the credit note is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $this->faker->randomElement([
                'Customer request',
                'Duplicate entry',
                'Processing error',
                'Invoice corrected',
            ]),
        ]);
    }

    /**
     * Indicate that the credit note has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now(),
        ]);
    }

    /**
     * Create a credit note for a specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($company->id),
        ]);
    }

    /**
     * Create a credit note for a specific invoice.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'currency' => $invoice->currency,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($invoice->company_id),
        ]);
    }

    /**
     * Create a credit note with a specific amount.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'tax_amount' => $amount * 0.1, // 10% tax
            'total_amount' => $amount * 1.1,
        ]);
    }

    /**
     * Create a credit note with items.
     */
    public function withItems(int $count = 1): static
    {
        return $this->afterCreating(function (CreditNote $creditNote) use ($count) {
            $items = [];
            $totalAmount = 0;

            for ($i = 0; $i < $count; $i++) {
                $quantity = $this->faker->randomFloat(1, 1, 10);
                $unitPrice = $this->faker->randomFloat(2, 10, 500);
                $taxRate = $this->faker->randomFloat(1, 0, 20);
                $discountAmount = $this->faker->randomFloat(2, 0, 50);

                $subtotal = $quantity * $unitPrice;
                $discountedSubtotal = $subtotal - $discountAmount;
                $taxAmount = $discountedSubtotal * ($taxRate / 100);
                $total = $discountedSubtotal + $taxAmount;

                $items[] = [
                    'credit_note_id' => $creditNote->id,
                    'description' => $this->faker->randomElement([
                        'Product return',
                        'Service credit',
                        'Discount applied',
                        'Price adjustment',
                    ]),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $totalAmount += $total;
            }

            // Update credit note totals
            $creditNote->update([
                'amount' => array_sum(array_column($items, 'quantity') * array_column($items, 'unit_price')),
                'tax_amount' => array_sum(array_column($items, 'total_amount')) - array_sum(array_column($items, 'quantity') * array_column($items, 'unit_price')),
                'total_amount' => $totalAmount,
            ]);

            // Insert items
            DB::table('invoicing.credit_note_items')->insert($items);
        });
    }
}
