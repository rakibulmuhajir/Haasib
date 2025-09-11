<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'base_currency' => 'USD',
            'language' => 'en',
            'locale' => 'en_US',
            'settings' => json_encode([]),
            'created_by_user_id' => null, // Default to null for seeded companies
        ];
    }

    /**
     * Indicate that the company was created by a specific user.
     */
    public function createdBy(User $creator): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $creator->id,
        ]);
    }
}
