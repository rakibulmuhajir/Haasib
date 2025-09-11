<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    protected $model = \App\Models\LedgerAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => $this->faker->unique()->numerify('####'),
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'normal_balance' => $this->faker->randomElement(['debit', 'credit']),
            'active' => true,
            'system_account' => false,
            'description' => $this->faker->sentence(),
            'level' => 1,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'liability',
            'normal_balance' => 'credit',
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'equity',
            'normal_balance' => 'credit',
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'revenue',
            'normal_balance' => 'credit',
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'normal_balance' => 'debit',
        ]);
    }

    public function systemAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_account' => true,
        ]);
    }

    public function childOf(LedgerAccount $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'level' => $parent->level + 1,
        ]);
    }
}
