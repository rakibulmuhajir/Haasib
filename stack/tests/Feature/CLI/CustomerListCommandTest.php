<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListCommandTest extends TestCase
{
    use RefreshDatabase;

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
    public function it_lists_customers_with_pagination_metadata()
    {
        // Create test customers
        Customer::factory()->count(15)->create([
            'company_id' => $this->company->id,
        ]);

        $this->artisan('customer:list')
            ->assertExitCode(0);

        // The command should output JSON with pagination metadata
        $output = $this->getArtisanOutput();

        $this->assertJson($output);

        $data = json_decode($output, true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('current_page', $data['pagination']);
        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertArrayHasKey('per_page', $data['pagination']);
    }

    /** @test */
    public function it_filters_customers_by_status()
    {
        // Create customers with different statuses
        Customer::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        Customer::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'inactive',
        ]);

        $this->artisan('customer:list --status=active')
            ->assertExitCode(0);

        $output = $this->getArtisanOutput();
        $data = json_decode($output, true);

        // Should only return active customers
        foreach ($data['data'] as $customer) {
            $this->assertEquals('active', $customer['status']);
        }
    }

    /** @test */
    public function it_searches_customers_by_name_or_email()
    {
        // Create specific customers for search
        Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Acme Corporation',
            'email' => 'contact@acme.com',
        ]);

        Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Beta Industries',
            'email' => 'info@beta.com',
        ]);

        // Search by name
        $this->artisan('customer:list --search=Acme')
            ->assertExitCode(0);

        $output = $this->getArtisanOutput();
        $data = json_decode($output, true);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('Acme Corporation', $data['data'][0]['name']);

        // Search by email
        $this->artisan('customer:list --search=beta.com')
            ->assertExitCode(0);

        $output = $this->getArtisanOutput();
        $data = json_decode($output, true);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('Beta Industries', $data['data'][0]['name']);
    }

    /** @test */
    public function it_respects_company_isolation()
    {
        // Create customers for current company
        Customer::factory()->count(5)->create([
            'company_id' => $this->company->id,
        ]);

        // Create customers for another company
        $otherCompany = Company::factory()->create();
        Customer::factory()->count(3)->create([
            'company_id' => $otherCompany->id,
        ]);

        $this->artisan('customer:list')
            ->assertExitCode(0);

        $output = $this->getArtisanOutput();
        $data = json_decode($output, true);

        // Should only return customers from current company
        $this->assertCount(5, $data['data']);

        foreach ($data['data'] as $customer) {
            $this->assertEquals($this->company->id, $customer['company_id']);
        }
    }

    /** @test */
    public function it_handles_empty_customer_list()
    {
        $this->artisan('customer:list')
            ->assertExitCode(0);

        $output = $this->getArtisanOutput();
        $data = json_decode($output, true);

        $this->assertArrayHasKey('data', $data);
        $this->assertEmpty($data['data']);
        $this->assertEquals(0, $data['pagination']['total']);
    }

    /** @test */
    public function it_validates_pagination_parameters()
    {
        Customer::factory()->count(10)->create([
            'company_id' => $this->company->id,
        ]);

        // Test page parameter
        $this->artisan('customer:list --page=2')
            ->assertExitCode(0);

        // Test per_page parameter
        $this->artisan('customer:list --per_page=5')
            ->assertExitCode(0);

        $output = $this->getArtisanOutput();
        $data = json_decode($output, true);

        $this->assertLessThanOrEqual(5, count($data['data']));
    }

    private function getArtisanOutput(): string
    {
        return $this->app[Illuminate\Contracts\Console\Kernel::class]->output();
    }
}
