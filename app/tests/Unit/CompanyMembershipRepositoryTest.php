<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Company;
use App\Repositories\CompanyMembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyMembershipRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_membership_and_role_for_user(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $repo = new CompanyMembershipRepository();

        $this->assertTrue($repo->verifyMembership($user->id, $company->id));
        $this->assertSame('admin', $repo->roleForUser($user->id, $company->id));
        $this->assertFalse($repo->verifyMembership($user->id, \Ramsey\Uuid\Uuid::uuid4()->toString()));
    }

    public function test_memberships_returns_user_companies(): void
    {
        $user = User::factory()->create();
        $c1 = Company::factory()->create(['name' => 'Acme']);
        $c2 = Company::factory()->create(['name' => 'Beta']);
        $user->companies()->attach($c1->id, ['role' => 'owner']);
        $user->companies()->attach($c2->id, ['role' => 'viewer']);

        $repo = new CompanyMembershipRepository();
        $memberships = $repo->memberships($user->id);

        $this->assertCount(2, $memberships);
        $this->assertEqualsCanonicalizing([
            $c1->id,
            $c2->id,
        ], $memberships->pluck('id')->all());
    }

    public function test_upsert_membership_inserts_and_updates(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $repo = new CompanyMembershipRepository();

        $repo->upsertMembership($company->id, $user->id, 'owner');

        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $repo->upsertMembership($company->id, $user->id, 'admin');

        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }
}

