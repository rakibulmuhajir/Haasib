<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyLookupService;
use App\Services\LookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_respects_membership(): void
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create(['name' => 'Alpha']);
        $companyB = Company::factory()->create(['name' => 'Beta']);
        $user->companies()->attach($companyA->id, ['role' => 'member']);

        $service = new LookupService(new CompanyLookupService());
        $results = $service->companies($user);

        $this->assertCount(1, $results);
        $this->assertEquals($companyA->id, $results[0]->id);
    }

    public function test_service_allows_superadmin_to_filter_by_query(): void
    {
        $super = User::factory()->create(['system_role' => 'superadmin']);
        Company::factory()->create(['name' => 'Acme Co']);
        Company::factory()->create(['name' => 'Beta Co']);

        $service = new LookupService(new CompanyLookupService());
        $results = $service->companies($super, 'Acme');

        $this->assertCount(1, $results);
        $this->assertEquals('Acme Co', $results[0]->name);
    }

    public function test_service_filters_by_user_email_for_superadmin(): void
    {
        $super = User::factory()->create(['system_role' => 'superadmin']);
        $member = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Gamma']);
        $member->companies()->attach($company->id, ['role' => 'member']);

        $service = new LookupService(new CompanyLookupService());
        $results = $service->companies($super, '', 10, ['user_email' => $member->email]);

        $this->assertCount(1, $results);
        $this->assertEquals('Gamma', $results[0]->name);
    }
}
