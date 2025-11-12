<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorContact>
 */
class VendorContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'vendor_id' => Vendor::factory(),
            'contact_type' => $this->faker->randomElement(['primary', 'billing', 'technical', 'other']),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->optional(0.8)->companyEmail(),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'mobile' => $this->faker->optional(0.6)->phoneNumber(),
            'is_primary' => false,
        ];
    }
}
