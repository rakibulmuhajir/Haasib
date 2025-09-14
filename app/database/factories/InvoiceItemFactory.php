<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 10, 1000);
        $quantity = fake()->randomFloat(2, 0.5, 100);
        $discountPercentage = fake()->optional(0.3)->randomFloat(2, 0, 50);
        $discountAmount = $discountPercentage ? 0 : fake()->optional(0.2)->randomFloat(2, 0, $unitPrice * 0.5);

        $subtotalBeforeDiscount = $unitPrice * $quantity;
        $discount = $discountPercentage
            ? $subtotalBeforeDiscount * ($discountPercentage / 100)
            : $discountAmount;
        $subtotalAfterDiscount = $subtotalBeforeDiscount - $discount;

        return [
            'id' => fake()->uuid(),
            'invoice_id' => Invoice::factory(),
            'item_id' => fake()->optional(0.8)->randomElement([Item::factory()->create()->id]),
            'description' => fake()->optional()->sentence(8),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount ?? 0,
            'discount_percentage' => $discountPercentage ?? 0,
            'subtotal' => $subtotalAfterDiscount,
            'total_tax' => 0,
            'total_amount' => $subtotalAfterDiscount,
            'tax_inclusive' => fake()->boolean(20),
            'metadata' => [
                'created_by' => 'factory',
                'category' => fake()->randomElement(['service', 'product', 'consulting']),
            ],
        ];
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function withItem(Item $item): static
    {
        return $this->state(fn (array $attributes) => [
            'item_id' => $item->id,
            'description' => fake()->optional(0.3)->sentence(),
            'unit_price' => $item->unit_price,
        ]);
    }

    public function service(): static
    {
        $services = [
            'Consulting Services',
            'Web Development',
            'Design Services',
            'Marketing Services',
            'Support Services',
            'Training Services',
            'Installation Services',
            'Maintenance Services',
        ];

        return $this->state(fn (array $attributes) => [
            'description' => fake()->randomElement($services),
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['category' => 'service']),
        ]);
    }

    public function product(): static
    {
        $products = [
            'Software License',
            'Hardware Components',
            'Office Supplies',
            'Equipment',
            'Materials',
            'Products',
            'Goods',
            'Inventory Items',
        ];

        return $this->state(fn (array $attributes) => [
            'description' => fake()->randomElement($products),
            'unit_price' => fake()->randomFloat(2, 10, 1000),
            'quantity' => fake()->randomFloat(2, 1, 1000),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['category' => 'product']),
        ]);
    }

    public function consulting(): static
    {
        $consultingServices = [
            'Business Consulting',
            'Technical Consulting',
            'Financial Consulting',
            'Strategic Planning',
            'Process Improvement',
            'Risk Assessment',
            'Compliance Review',
            'Project Management',
        ];

        return $this->state(fn (array $attributes) => [
            'description' => fake()->randomElement($consultingServices),
            'unit_price' => fake()->randomFloat(2, 100, 1000),
            'quantity' => fake()->randomFloat(2, 1, 40),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['category' => 'consulting']),
        ]);
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->randomFloat(2, 0.5, 80),
            'description' => fake()->randomElement(['Hours', 'Consulting Hours', 'Development Hours', 'Design Hours']),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['billing_type' => 'hourly']),
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->randomFloat(0, 1, 30),
            'description' => fake()->randomElement(['Days', 'Consulting Days', 'Development Days']),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['billing_type' => 'daily']),
        ]);
    }

    public function withDiscount(float $percentage = 10.0): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percentage' => $percentage,
            'discount_amount' => 0,
        ]);
    }

    public function withFixedDiscount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_amount' => $amount,
            'discount_percentage' => 0,
        ]);
    }

    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => fake()->randomFloat(2, 500, 5000),
            'quantity' => fake()->randomFloat(2, 5, 100),
        ]);
    }

    public function lowValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => fake()->randomFloat(2, 1, 50),
            'quantity' => fake()->randomFloat(2, 1, 10),
        ]);
    }

    public function taxable(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['taxable' => true]),
        ]);
    }

    public function taxExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['taxable' => false]),
        ]);
    }

    public function taxInclusive(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_inclusive' => true,
        ]);
    }

    public function taxExclusive(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_inclusive' => false,
        ]);
    }

    public function withTaxes(array $taxes = []): static
    {
        return $this->afterCreating(function ($invoiceItem) use ($taxes) {
            if (empty($taxes)) {
                $taxes = [
                    ['name' => 'Sales Tax', 'rate' => fake()->randomFloat(2, 5, 15)],
                    ['name' => 'VAT', 'rate' => fake()->randomFloat(2, 10, 25)],
                ];
            }

            foreach ($taxes as $tax) {
                InvoiceItemTax::factory()->create([
                    'invoice_item_id' => $invoiceItem->id,
                    'tax_name' => $tax['name'],
                    'rate' => $tax['rate'],
                ]);
            }

            $invoiceItem->calculateTotals();
            $invoiceItem->save();
        });
    }
}
