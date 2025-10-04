<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItemTax>
 */
class InvoiceItemTaxFactory extends Factory
{
    protected $model = \App\Models\InvoiceItemTax::class;

    public function definition(): array
    {
        $taxTypes = [
            ['name' => 'Sales Tax', 'rate' => fake()->randomFloat(2, 4, 10)],
            ['name' => 'VAT', 'rate' => fake()->randomFloat(2, 15, 25)],
            ['name' => 'GST', 'rate' => fake()->randomFloat(2, 5, 18)],
            ['name' => 'Service Tax', 'rate' => fake()->randomFloat(2, 5, 15)],
            ['name' => 'Luxury Tax', 'rate' => fake()->randomFloat(2, 10, 30)],
            ['name' => 'Excise Tax', 'rate' => fake()->randomFloat(2, 8, 20)],
        ];

        $tax = fake()->randomElement($taxTypes);

        return [
            'id' => fake()->uuid(),
            'invoice_item_id' => InvoiceItem::factory(),
            'tax_id' => fake()->uuid(),
            'tax_name' => $tax['name'],
            'rate' => $tax['rate'],
            'tax_amount' => 0,
            'taxable_amount' => 0,
            'is_compound' => fake()->boolean(10),
            'compound_order' => fake()->boolean(10) ? fake()->numberBetween(1, 5) : 0,
            'metadata' => [
                'created_by' => 'factory',
                'tax_type' => strtolower(str_replace(' ', '_', $tax['name'])),
            ],
        ];
    }

    public function forInvoiceItem(InvoiceItem $invoiceItem): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_item_id' => $invoiceItem->id,
        ]);
    }

    public function salesTax(float $rate = 7.5): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_name' => 'Sales Tax',
            'rate' => $rate,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['tax_type' => 'sales_tax']),
        ]);
    }

    public function vat(float $rate = 20.0): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_name' => 'VAT',
            'rate' => $rate,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['tax_type' => 'vat']),
        ]);
    }

    public function gst(float $rate = 15.0): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_name' => 'GST',
            'rate' => $rate,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['tax_type' => 'gst']),
        ]);
    }

    public function serviceTax(float $rate = 12.0): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_name' => 'Service Tax',
            'rate' => $rate,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['tax_type' => 'service_tax']),
        ]);
    }

    public function compound(int $order = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'is_compound' => true,
            'compound_order' => $order,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['compound' => true]),
        ]);
    }

    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_compound' => false,
            'compound_order' => 0,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['compound' => false]),
        ]);
    }

    public function lowRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => fake()->randomFloat(2, 1, 10),
        ]);
    }

    public function highRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => fake()->randomFloat(2, 15, 35),
        ]);
    }

    public function zeroRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => 0,
            'tax_name' => 'Zero Rated',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['zero_rated' => true]),
        ]);
    }

    public function exempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => 0,
            'tax_name' => 'Tax Exempt',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['exempt' => true]),
        ]);
    }

    public function regional(string $region): static
    {
        $regionalTaxes = [
            'ae' => ['name' => 'VAT', 'rate' => 5.0],
            'pk' => ['name' => 'GST', 'rate' => 18.0],
            'us' => ['name' => 'Sales Tax', 'rate' => 7.5],
            'uk' => ['name' => 'VAT', 'rate' => 20.0],
            'eu' => ['name' => 'VAT', 'rate' => 21.0],
            'in' => ['name' => 'GST', 'rate' => 18.0],
            'ca' => ['name' => 'GST', 'rate' => 5.0],
            'au' => ['name' => 'GST', 'rate' => 10.0],
        ];

        $taxData = $regionalTaxes[$region] ?? $regionalTaxes['us'];

        return $this->state(fn (array $attributes) => [
            'tax_name' => $taxData['name'],
            'rate' => $taxData['rate'],
            'metadata' => array_merge($attributes['metadata'] ?? [], ['region' => $region]),
        ]);
    }

    public function custom(string $name, float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_name' => $name,
            'rate' => $rate,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['custom' => true]),
        ]);
    }

    public function forAe(): static
    {
        return $this->regional('ae');
    }

    public function forPk(): static
    {
        return $this->regional('pk');
    }

    public function forUs(): static
    {
        return $this->regional('us');
    }

    public function forUk(): static
    {
        return $this->regional('uk');
    }

    public function forEu(): static
    {
        return $this->regional('eu');
    }

    public function withTaxId(string $taxId): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_id' => $taxId,
        ]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['notes' => $notes]),
        ]);
    }
}
