<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Company;
use App\Services\CompanyLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_membership_and_role_for_user(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $lookup = new CompanyLookupService();

        $this->assertTrue($lookup->isMember($company->id, $user->id));
        $this->assertSame('admin', $lookup->userRole($company->id, $user->id));
        $this->assertFalse($lookup->isMember(\Ramsey\Uuid\Uuid::uuid4()->toString(), $user->id));
    }

    public function test_memberships_returns_user_companies(): void
    {
        $user = User::factory()->create();
        $c1 = Company::factory()->create(['name' => 'Acme']);
        $c2 = Company::factory()->create(['name' => 'Beta']);
        $user->companies()->attach($c1->id, ['role' => 'owner']);
        $user->companies()->attach($c2->id, ['role' => 'viewer']);

        $lookup = new CompanyLookupService();
        $memberships = $lookup->membershipsForUser($user->id);

        $this->assertCount(2, $memberships);
        $this->assertEqualsCanonicalizing([
            $c1->id,
            $c2->id,
        ], $memberships->pluck('id')->all());
        $this->assertNotNull($memberships->first()->created_at);
        $this->assertNotNull($memberships->first()->updated_at);
    }

    public function test_upsert_membership_inserts_and_updates(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $lookup = new CompanyLookupService();

        $lookup->upsertMember($company->id, $user->id, ['role' => 'owner']);

        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $lookup->upsertMember($company->id, $user->id, ['role' => 'admin']);

        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }
}

