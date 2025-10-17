<?php

namespace Database\Factories;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditEntry>
 */
class AuditEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AuditEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => $this->faker->randomElement(['created', 'updated', 'deleted', 'login', 'logout']),
            'entity_type' => $this->faker->randomElement(['user', 'company', 'invoice', 'customer', 'payment']),
            'entity_id' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'old_values' => $this->faker->randomElement([
                null,
                ['name' => $this->faker->name(), 'email' => $this->faker->email()],
            ]),
            'new_values' => $this->faker->randomElement([
                null,
                ['name' => $this->faker->name(), 'email' => $this->faker->email()],
            ]),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
            'location' => $this->faker->randomElement([
                null,
                ['country' => $this->faker->country(), 'city' => $this->faker->city()],
            ]),
            'metadata' => $this->faker->randomElement([
                null,
                ['session_id' => $this->faker->sha256(), 'request_id' => $this->faker->uuid()],
            ]),
            'is_system_action' => $this->faker->boolean(20), // 20% chance of system action
        ];
    }

    /**
     * Indicate that the audit entry is a creation event.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'created',
            'old_values' => null,
        ]);
    }

    /**
     * Indicate that the audit entry is an update event.
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'updated',
        ]);
    }

    /**
     * Indicate that the audit entry is a deletion event.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'deleted',
            'new_values' => null,
        ]);
    }

    /**
     * Indicate that the audit entry is a system action.
     */
    public function systemAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system_action' => true,
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the audit entry is for a specific entity.
     */
    public function forEntity(string $entityType, string $entityId): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Indicate that the audit entry is for a specific user.
     */
    public function byUser(User|string $user): static
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Indicate that the audit entry is for a specific company.
     */
    public function forCompany(Company|string $company): static
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->state(fn (array $attributes) => [
            'company_id' => $companyId,
        ]);
    }
}
