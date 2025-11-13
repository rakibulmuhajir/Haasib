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
        $events = ['created', 'updated', 'deleted', 'restored', 'password_changed'];
        $modelTypes = [
            'App\Models\User',
            'App\Models\Company',
            'App\Models\Invoice',
            'App\Models\Payment',
            'App\Models\Customer',
            'App\Models\Vendor',
            'App\Models\Bill',
            'App\Models\Expense',
            'App\Models\JournalEntry',
            'App\Models\Account',
            'App\Models\Acct\PurchaseOrder',
        ];

        return [
            'event' => $this->faker->randomElement($events),
            'model_type' => $this->faker->randomElement($modelTypes),
            'model_id' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'old_values' => $this->faker->randomElement([
                null,
                ['name' => $this->faker->name(), 'email' => $this->faker->email(), 'amount' => $this->faker->randomFloat(2, 0, 10000)],
            ]),
            'new_values' => $this->faker->randomElement([
                null,
                ['name' => $this->faker->name(), 'email' => $this->faker->email(), 'amount' => $this->faker->randomFloat(2, 0, 10000)],
            ]),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'tags' => $this->faker->randomElements([
                'financial', 'security', 'customer', 'vendor', 'invoice', 'payment',
                'created', 'updated', 'deleted', 'access_control',
            ], $this->faker->numberBetween(1, 3)),
            'metadata' => $this->faker->randomElement([
                null,
                [
                    'session_id' => $this->faker->sha256(),
                    'request_id' => $this->faker->uuid(),
                    'user_context' => [
                        'id' => $this->faker->uuid(),
                        'name' => $this->faker->name(),
                    ],
                ],
            ]),
        ];
    }

    /**
     * Indicate that the audit entry is a creation event.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'created',
            'old_values' => null,
        ]);
    }

    /**
     * Indicate that the audit entry is an update event.
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'updated',
        ]);
    }

    /**
     * Indicate that the audit entry is a deletion event.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'deleted',
            'new_values' => null,
        ]);
    }

    /**
     * Indicate that the audit entry is for a specific entity.
     */
    public function forEntity(string $modelType, string $modelId): static
    {
        return $this->state(fn (array $attributes) => [
            'model_type' => $modelType,
            'model_id' => $modelId,
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
