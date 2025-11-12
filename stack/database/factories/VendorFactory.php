<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
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
            'company_id' => Company::factory(),
            'vendor_code' => 'V'.$this->faker->unique()->numberBetween(1000, 9999),
            'legal_name' => $this->faker->company(),
            'display_name' => $this->faker->company(),
            'tax_id' => $this->faker->optional(0.7)->numerify('##-#######'),
            'vendor_type' => $this->faker->randomElement(['individual', 'company', 'other']),
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'website' => $this->faker->optional(0.6)->url(),
            'notes' => $this->faker->optional(0.4)->sentence(),
        ];
    }
}
