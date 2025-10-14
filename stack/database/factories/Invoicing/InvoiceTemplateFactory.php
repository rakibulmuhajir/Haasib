<?php

namespace Database\Factories\Invoicing;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory as BaseFactory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceTemplate>
 */
class InvoiceTemplateFactory extends BaseFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = InvoiceTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->words(3, true).' Template',
            'description' => $this->faker->sentence(),
            'customer_id' => Customer::factory(),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'template_data' => [
                'notes' => $this->faker->sentence(10),
                'terms' => $this->faker->sentence(15),
                'payment_terms' => $this->faker->numberBetween(15, 90),
                'line_items' => [
                    [
                        'id' => 'item-'.$this->faker->uuid(),
                        'description' => $this->faker->sentence(3),
                        'quantity' => $this->faker->numberBetween(1, 10),
                        'unit_price' => $this->faker->randomFloat(2, 50, 500),
                        'tax_rate' => $this->faker->randomElement([0, 5, 10, 20]),
                        'discount_amount' => $this->faker->optional(0.3)->randomFloat(2, 0, 50),
                    ],
                    [
                        'id' => 'item-'.$this->faker->uuid(),
                        'description' => $this->faker->sentence(3),
                        'quantity' => $this->faker->numberBetween(1, 5),
                        'unit_price' => $this->faker->randomFloat(2, 25, 200),
                        'tax_rate' => $this->faker->randomElement([0, 5, 10, 20]),
                        'discount_amount' => $this->faker->optional(0.2)->randomFloat(2, 0, 25),
                    ],
                ],
            ],
            'settings' => [
                'auto_number' => true,
                'number_prefix' => 'TPL-',
                'send_email' => false,
                'generate_pdf' => false,
                'reminder_settings' => [
                    'enabled' => false,
                    'days_before_due' => 7,
                    'days_overdue' => 14,
                ],
            ],
            'is_active' => true,
            'created_by_user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is for a general customer (not specific).
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => null,
        ]);
    }

    /**
     * Indicate that the template has a single line item.
     */
    public function singleItem(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_data' => array_merge($attributes['template_data'], [
                'line_items' => [
                    [
                        'id' => 'item-'.$this->faker->uuid(),
                        'description' => $this->faker->sentence(3),
                        'quantity' => $this->faker->numberBetween(1, 10),
                        'unit_price' => $this->faker->randomFloat(2, 100, 1000),
                        'tax_rate' => $this->faker->randomElement([0, 5, 10, 20]),
                        'discount_amount' => 0,
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the template has complex line items.
     */
    public function complexItems(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_data' => array_merge($attributes['template_data'], [
                'line_items' => [
                    [
                        'id' => 'item-'.$this->faker->uuid(),
                        'description' => $this->faker->sentence(4),
                        'quantity' => $this->faker->numberBetween(1, 20),
                        'unit_price' => $this->faker->randomFloat(2, 10, 100),
                        'tax_rate' => 20,
                        'discount_amount' => $this->faker->randomFloat(2, 0, 20),
                    ],
                    [
                        'id' => 'item-'.$this->faker->uuid(),
                        'description' => $this->faker->sentence(3),
                        'quantity' => $this->faker->numberBetween(5, 50),
                        'unit_price' => $this->faker->randomFloat(2, 25, 75),
                        'tax_rate' => 10,
                        'discount_amount' => $this->faker->randomFloat(2, 0, 15),
                    ],
                    [
                        'id' => 'item-'.$this->faker->uuid(),
                        'description' => $this->faker->sentence(2),
                        'quantity' => 1,
                        'unit_price' => $this->faker->randomFloat(2, 500, 2000),
                        'tax_rate' => 0,
                        'discount_amount' => 0,
                    ],
                ],
            ]),
        ]);
    }
}
