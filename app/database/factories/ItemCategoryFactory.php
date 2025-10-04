<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItemCategory>
 */
class ItemCategoryFactory extends Factory
{
    protected $model = \App\Models\ItemCategory::class;

    public function definition(): array
    {
        $company = Company::inRandomOrder()->first() ?? Company::factory()->create();

        $categories = [
            'Software & IT Services',
            'Hardware & Equipment',
            'Office Supplies',
            'Professional Services',
            'Consulting',
            'Marketing & Advertising',
            'Training & Education',
            'Maintenance & Support',
            'Travel & Expenses',
            'Materials & Supplies',
            'Other Services',
        ];

        return [
            'id' => fake()->uuid(),
            'company_id' => $company->id,
            'name' => fake()->randomElement($categories),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'metadata' => [
                'created_by' => 'factory',
                'category_type' => fake()->randomElement(['service', 'product', 'mixed']),
            ],
        ];
    }

    public function software(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Software & IT Services',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['category_type' => 'service']),
        ]);
    }

    public function hardware(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Hardware & Equipment',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['category_type' => 'product']),
        ]);
    }

    public function services(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Professional Services',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['category_type' => 'service']),
        ]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }
}
