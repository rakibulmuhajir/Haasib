<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditNoteItem>
 */
class CreditNoteItemFactory extends Factory
{
    protected $model = CreditNoteItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creditNote = CreditNote::factory()->create();
        $quantity = $this->faker->randomFloat(1, 1, 10);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $taxRate = $this->faker->randomFloat(1, 0, 20);
        $discountAmount = $this->faker->randomFloat(2, 0, 50);

        $subtotal = $quantity * $unitPrice;
        $discountedSubtotal = $subtotal - $discountAmount;
        $taxAmount = $discountedSubtotal * ($taxRate / 100);
        $total = $discountedSubtotal + $taxAmount;

        return [
            'credit_note_id' => $creditNote->id,
            'description' => $this->faker->randomElement([
                'Product return',
                'Service credit',
                'Discount applied',
                'Price adjustment',
                'Shipping refund',
                'Tax adjustment',
            ]),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
        ];
    }

    /**
     * Create an item for a specific credit note.
     */
    public function forCreditNote(CreditNote $creditNote): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_note_id' => $creditNote->id,
        ]);
    }

    /**
     * Create an item with a specific description.
     */
    public function withDescription(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description,
        ]);
    }

    /**
     * Create an item with a specific amount.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
        ]);
    }
}
