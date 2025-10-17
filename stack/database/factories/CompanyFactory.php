<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'industry' => $this->faker->randomElement([
                'hospitality',
                'retail',
                'professional_services',
                'technology',
                'manufacturing',
            ]),
            'country' => $this->faker->countryCode(),
            'base_currency' => $this->faker->currencyCode(),
            'language' => $this->faker->languageCode(),
            'locale' => $this->faker->locale(),
            'settings' => [
                'timezone' => $this->faker->timezone(),
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i:s',
            ],
            'created_by_user_id' => User::factory(),
        ];
    }
}
