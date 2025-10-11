<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $moduleName = $this->faker->unique()->lexify('??????');
        
        return [
            'key' => $moduleName,
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'version' => $this->faker->semver(),
            'category' => $this->faker->randomElement(['accounting', 'invoicing', 'reporting', 'inventory', 'hr', 'crm']),
            'icon' => $this->faker->randomElement(['chart-bar', 'document-text', 'calculator', 'users', 'cube']),
            'is_core' => false,
            'is_active' => true,
            'dependencies' => [],
            'permissions' => [],
            'settings_schema' => [],
            'menu_order' => $this->faker->numberBetween(1, 100),
            'developer' => $this->faker->company(),
        ];
    }

    /**
     * Create a core module.
     */
    public function core(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_core' => true,
        ]);
    }

    /**
     * Create an inactive module.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a module with dependencies.
     */
    public function withDependencies(array $dependencies): static
    {
        return $this->state(fn (array $attributes) => [
            'dependencies' => json_encode($dependencies),
        ]);
    }

    /**
     * Create an accounting module.
     */
    public function accounting(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'accounting',
            'name' => 'Core Accounting',
            'category' => 'accounting',
            'is_core' => true,
        ]);
    }

    /**
     * Create an invoicing module.
     */
    public function invoicing(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'invoicing',
            'name' => 'Invoicing',
            'category' => 'invoicing',
            'is_core' => true,
        ]);
    }
}
