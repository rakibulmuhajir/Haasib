<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    protected $model = \App\Models\Contact::class;

    public function definition(): array
    {
        $company = Company::inRandomOrder()->first() ?? Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        return [
            'id' => fake()->uuid(),
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'vendor_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->optional()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'department' => fake()->optional()->randomElement(['Sales', 'Finance', 'IT', 'Operations', 'Management', 'HR']),
            'is_primary' => fake()->boolean(20),
            'is_active' => true,
            'metadata' => [
                'created_by' => 'factory',
                'contact_type' => fake()->randomElement(['primary', 'billing', 'technical', 'sales']),
            ],
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'vendor_id' => null,
        ]);
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $vendor->company_id,
            'customer_id' => null,
            'vendor_id' => $vendor->id,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['contact_type' => 'primary']),
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['contact_type' => 'billing']),
        ]);
    }

    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['contact_type' => 'technical']),
        ]);
    }

    public function sales(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], ['contact_type' => 'sales']),
        ]);
    }

    public function withMobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'mobile' => fake()->phoneNumber(),
        ]);
    }

    public function withoutMobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'mobile' => null,
        ]);
    }

    public function executive(): static
    {
        $executiveTitles = [
            'CEO', 'President', 'Vice President', 'Director', 'Manager',
            'CFO', 'CTO', 'COO', 'Founder', 'Owner', 'Partner',
        ];

        return $this->state(fn (array $attributes) => [
            'job_title' => fake()->randomElement($executiveTitles),
            'department' => 'Management',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['is_executive' => true]),
        ]);
    }

    public function technical(): static
    {
        $technicalTitles = [
            'Software Engineer', 'IT Manager', 'System Administrator', 'Developer',
            'Technical Lead', 'DevOps Engineer', 'Network Administrator', 'Database Administrator',
        ];

        return $this->state(fn (array $attributes) => [
            'job_title' => fake()->randomElement($technicalTitles),
            'department' => 'IT',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['is_technical' => true]),
        ]);
    }

    public function financial(): static
    {
        $financialTitles = [
            'CFO', 'Finance Manager', 'Accountant', 'Controller', 'Financial Analyst',
            'Bookkeeper', 'Finance Director', 'Treasury Manager',
        ];

        return $this->state(fn (array $attributes) => [
            'job_title' => fake()->randomElement($financialTitles),
            'department' => 'Finance',
            'metadata' => array_merge($attributes['metadata'] ?? [], ['is_financial' => true]),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'inactivation_reason' => fake()->randomElement(['left_company', 'retired', 'role_change']),
            ]),
        ]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'customer_id' => Customer::factory()->create(['company_id' => $company->id])->id,
        ]);
    }
}
