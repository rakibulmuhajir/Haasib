<?php

namespace Database\Factories\Invoicing;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory as BaseFactory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends BaseFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_number' => 'CUST-'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->company(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => $this->faker->countryCode(),
            'tax_id' => $this->faker->optional()->numerify('##-########'),
            'website' => $this->faker->optional()->url(),
            'notes' => $this->faker->optional()->sentence(),
            'credit_limit' => $this->faker->optional()->randomFloat(2, 1000, 50000),
            'payment_terms' => $this->faker->randomElement(['net15', 'net30', 'net60', 'net90']),
            'is_active' => true,
            'created_by_user_id' => User::factory(),
        ];
    }
}
