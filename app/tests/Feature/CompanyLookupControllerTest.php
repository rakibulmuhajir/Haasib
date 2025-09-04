<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_companies(): void
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create(['name' => 'Alpha Corp']);
        $companyB = Company::factory()->create(['name' => 'Beta LLC']);
        $user->companies()->attach($companyA->id, ['role' => 'member']);

        $this->actingAs($user);
        $res = $this->getJson('/api/v1/companies');
        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['name' => 'Alpha Corp']);
    }

    public function test_superadmin_can_filter_by_query_and_user_email(): void
    {
        $super = User::factory()->create(['system_role' => 'superadmin']);
        $user = User::factory()->create();
        $companyA = Company::factory()->create(['name' => 'Acme Widgets']);
        $companyB = Company::factory()->create(['name' => 'Beta Stuff']);
        $user->companies()->attach($companyB->id, ['role' => 'member']);

        $this->actingAs($super);
        $res = $this->getJson('/api/v1/companies?q=Acme');
        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['name' => 'Acme Widgets']);

        $res = $this->getJson('/api/v1/companies?user_email='.$user->email);
        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['name' => 'Beta Stuff']);
    }

    public function test_non_member_cannot_access_company_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $company = Company::factory()->create();
        $other->companies()->attach($company->id, ['role' => 'member']);

        $this->actingAs($user);
        $this->getJson('/api/v1/companies/'.$company->id.'/users')
            ->assertStatus(403);
    }

    public function test_member_can_filter_company_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Zed Person', 'email' => 'zed@example.com']);
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $other->companies()->attach($company->id, ['role' => 'member']);

        $this->actingAs($user);
        $res = $this->getJson('/api/v1/companies/'.$company->id.'/users?q=zed');
        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['email' => 'zed@example.com']);
    }
}
