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

    public function test_is_member_and_role_for_user(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $svc = new CompanyLookupService();

        $this->assertTrue($svc->isMember($company->id, $user->id));
        $this->assertSame('admin', $svc->userRole($company->id, $user->id));
        $this->assertFalse($svc->isMember($company->id, \Ramsey\Uuid\Uuid::uuid4()->toString()));
    }

    public function test_memberships_for_user_returns_companies(): void
    {
        $user = User::factory()->create();
        $c1 = Company::factory()->create(['name' => 'Acme']);
        $c2 = Company::factory()->create(['name' => 'Beta']);
        $user->companies()->attach($c1->id, ['role' => 'owner']);
        $user->companies()->attach($c2->id, ['role' => 'viewer']);

        $svc = new CompanyLookupService();
        $memberships = $svc->membershipsForUser($user->id);

        $this->assertCount(2, $memberships);
        $this->assertEqualsCanonicalizing([
            $c1->id,
            $c2->id,
        ], $memberships->pluck('id')->all());
    }

    public function test_upsert_member_inserts_and_updates(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $svc = new CompanyLookupService();

        $svc->upsertMember($company->id, $user->id, ['role' => 'owner']);

        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $svc->upsertMember($company->id, $user->id, ['role' => 'admin']);

        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }
}
