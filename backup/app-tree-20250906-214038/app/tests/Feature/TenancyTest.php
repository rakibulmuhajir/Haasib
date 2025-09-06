<?php
// tests/Feature/TenancyTest.php (PHPUnit version)
namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_companies_for_current_user(): void
    {
        $user = User::factory()->create();
        $c1 = Company::factory()->create();
        $c2 = Company::factory()->create();
        $user->companies()->attach([$c1->id, $c2->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/me/companies')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_prevents_switching_to_other_company(): void
    {
        $user = User::factory()->create();
        $alien = Company::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/me/companies/switch', ['company_id' => $alien->id])
            ->assertStatus(403);
    }
}
