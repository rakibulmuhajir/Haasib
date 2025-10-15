<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateCustomerActionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->actingAs($this->user);
        $this->setCurrentCompany($this->company);
    }

    /** @test */
    public function it_can_create_a_customer_via_command_bus()
    {
        $customerData = [
            'customer_number' => 'CUST-0001',
            'name' => $this->faker->company(),
            'legal_name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+1-555-000-0000',
            'default_currency' => 'USD',
            'payment_terms' => 'net_30',
            'credit_limit' => 10000.00,
            'tax_id' => $this->faker->taxId(),
            'website' => $this->faker->url(),
            'notes' => $this->faker->sentence(),
            'status' => 'active',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'customer_number',
                'name',
                'email',
                'status',
                'created_at',
            ]);

        $this->assertDatabaseHas('invoicing.customers', [
            'customer_number' => 'CUST-0001',
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_customer()
    {
        $response = $this->postJson('/api/customers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'customer_number', 'default_currency']);
    }

    /** @test */
    public function it_validates_unique_email_within_company()
    {
        // Create existing customer
        Customer::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'test@example.com',
        ]);

        $customerData = [
            'customer_number' => 'CUST-0002',
            'name' => $this->faker->company(),
            'email' => 'test@example.com', // Duplicate email
            'default_currency' => 'USD',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_validates_unique_customer_number_within_company()
    {
        // Create existing customer
        Customer::factory()->create([
            'company_id' => $this->company->id,
            'customer_number' => 'CUST-0001',
        ]);

        $customerData = [
            'customer_number' => 'CUST-0001', // Duplicate
            'name' => $this->faker->company(),
            'default_currency' => 'USD',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_number']);
    }

    /** @test */
    public function it_validates_currency_format()
    {
        $customerData = [
            'customer_number' => 'CUST-0003',
            'name' => $this->faker->company(),
            'default_currency' => 'INVALID', // Invalid currency
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_currency']);
    }

    /** @test */
    public function it_emits_audit_event_when_customer_is_created()
    {
        Event::fake();

        $customerData = [
            'customer_number' => 'CUST-0004',
            'name' => $this->faker->company(),
            'default_currency' => 'USD',
        ];

        $this->postJson('/api/customers', $customerData);

        Event::assertDispatched(function ($event) {
            return $event->type === 'customer.created';
        });
    }

    /** @test */
    public function it_generates_customer_number_automatically_when_not_provided()
    {
        $customerData = [
            'name' => $this->faker->company(),
            'default_currency' => 'USD',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('invoicing.customers', [
            'name' => $customerData['name'],
            'company_id' => $this->company->id,
        ]);
    }
}
