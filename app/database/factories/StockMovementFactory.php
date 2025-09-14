<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $company = Company::inRandomOrder()->first() ?? Company::factory()->create();
        $item = Item::factory()->create(['company_id' => $company->id, 'track_inventory' => true]);

        $movementTypes = [
            'purchase', 'sale', 'return', 'adjustment', 'transfer_in', 'transfer_out',
            'damage', 'loss', 'theft', 'expiration', 'recount',
        ];

        $quantity = fake()->randomFloat(2, 1, 100);
        $movementType = fake()->randomElement($movementTypes);

        $previousQuantity = $item->stock_quantity;
        $newQuantity = match ($movementType) {
            'purchase', 'transfer_in', 'return' => $previousQuantity + $quantity,
            'sale', 'transfer_out', 'damage', 'loss', 'theft', 'expiration' => max(0, $previousQuantity - $quantity),
            'adjustment', 'recount' => fake()->randomFloat(2, 0, 1000),
            default => $previousQuantity,
        };

        return [
            'id' => fake()->uuid(),
            'item_id' => $item->id,
            'company_id' => $company->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reference' => fake()->optional()->numerify('REF########'),
            'notes' => fake()->optional()->sentence(),
            'metadata' => [
                'created_by' => 'factory',
                'source' => 'inventory_management',
                'movement_reason' => fake()->randomElement([
                    'regular_purchase', 'customer_order', 'return_from_customer', 'inventory_correction',
                    'stock_take', 'damage_report', 'theft_report', 'expiration_check',
                ]),
            ],
        ];
    }

    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'purchase',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'regular_purchase',
                'supplier' => fake()->company(),
                'purchase_order' => 'PO'.fake()->numerify('######'),
            ]),
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'sale',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'customer_order',
                'customer' => fake()->company(),
                'invoice_number' => 'INV'.fake()->numerify('######'),
            ]),
        ]);
    }

    public function return(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'return',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'return_from_customer',
                'customer' => fake()->company(),
                'return_reason' => fake()->randomElement(['defective', 'wrong_item', 'not_needed', 'late_delivery']),
            ]),
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'adjustment',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'inventory_correction',
                'adjustment_reason' => fake()->randomElement(['system_error', 'manual_correction', 'data_entry_mistake']),
            ]),
        ]);
    }

    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'transfer_in',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'warehouse_transfer',
                'from_warehouse' => fake()->word().' Warehouse',
                'transfer_reference' => 'TRF'.fake()->numerify('######'),
            ]),
        ]);
    }

    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'transfer_out',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'warehouse_transfer',
                'to_warehouse' => fake()->word().' Warehouse',
                'transfer_reference' => 'TRF'.fake()->numerify('######'),
            ]),
        ]);
    }

    public function damage(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'damage',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'damage_report',
                'damage_type' => fake()->randomElement(['physical_damage', 'water_damage', 'fire_damage', 'handling_damage']),
                'reported_by' => fake()->name(),
            ]),
        ]);
    }

    public function loss(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'loss',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'inventory_loss',
                'loss_type' => fake()->randomElement(['misplaced', 'administrative_error', 'shipping_loss']),
                'reported_by' => fake()->name(),
            ]),
        ]);
    }

    public function theft(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'theft',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'theft_report',
                'reported_to_authorities' => fake()->boolean(),
                'incident_date' => now()->subDays(fake()->numberBetween(1, 30))->toISOString(),
            ]),
        ]);
    }

    public function expiration(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'expiration',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'expiration_check',
                'expiration_date' => now()->subDays(fake()->numberBetween(1, 365))->toISOString(),
                'batch_number' => 'BATCH'.fake()->numerify('######'),
            ]),
        ]);
    }

    public function recount(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'recount',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'movement_reason' => 'stock_take',
                'counted_by' => fake()->name(),
                'count_date' => now()->toISOString(),
                'discrepancy_reason' => fake()->optional()->randomElement(['counting_error', 'system_error', 'theft', 'unrecorded_movements']),
            ]),
        ]);
    }

    public function forItem(Item $item): static
    {
        return $this->state(fn (array $attributes) => [
            'item_id' => $item->id,
            'company_id' => $item->company_id,
        ]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'item_id' => Item::factory()->create(['company_id' => $company->id, 'track_inventory' => true])->id,
        ]);
    }

    public function positiveMovement(): static
    {
        $movementTypes = ['purchase', 'transfer_in', 'return'];

        return $this->state(fn (array $attributes) => [
            'movement_type' => fake()->randomElement($movementTypes),
            'quantity' => fake()->randomFloat(2, 1, 100),
        ]);
    }

    public function negativeMovement(): static
    {
        $movementTypes = ['sale', 'transfer_out', 'damage', 'loss', 'theft', 'expiration'];

        return $this->state(fn (array $attributes) => [
            'movement_type' => fake()->randomElement($movementTypes),
            'quantity' => fake()->randomFloat(2, 1, 50),
        ]);
    }

    public function withReference(string $reference): static
    {
        return $this->state(fn (array $attributes) => [
            'reference' => $reference,
        ]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'created_at' => now()->subHours(fake()->numberBetween(1, 72)),
            ]),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'created_at' => now()->subDays(fake()->numberBetween(30, 365)),
            ]),
        ]);
    }

    public function highQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->randomFloat(2, 100, 1000),
        ]);
    }

    public function lowQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->randomFloat(2, 0.1, 5),
        ]);
    }

    public function withBatchNumber(string $batchNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['batch_number' => $batchNumber]),
        ]);
    }

    public function withExpiryDate(string $expiryDate): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['expiry_date' => $expiryDate]),
        ]);
    }

    public function withSerialNumber(string $serialNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['serial_number' => $serialNumber]),
        ]);
    }
}
