<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyModule>
 */
class CompanyModuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CompanyModule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'company_id' => Company::factory(),
            'module_id' => Module::factory(),
            'is_active' => true,
            'settings' => [
                'auto_sync' => true,
                'notifications_enabled' => true,
            ],
            'enabled_by_user_id' => User::factory(),
            'enabled_at' => now(),
            'disabled_by_user_id' => null,
            'disabled_at' => null,
        ];
    }

    /**
     * Create an inactive company module.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_by_user_id' => User::factory(),
        ]);
    }

    /**
     * Create a company module with custom settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => json_encode($settings),
        ]);
    }

    /**
     * Create a company module for a specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }

    /**
     * Create a company module for a specific module.
     */
    public function forModule(Module $module): static
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
        ]);
    }

    /**
     * Create a company module enabled by a specific user.
     */
    public function enabledBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled_by_user_id' => $user->id,
        ]);
    }
}
