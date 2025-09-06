<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvitationService();
    }

    public function test_owner_can_list_company_invitations(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create();
        $owner->companies()->attach($company->id, ['role' => 'owner']);

        CompanyInvitation::factory()->count(2)->create([
            'company_id' => $company->id,
        ]);

        $list = $this->service->listCompanyInvitations($owner, $company->id);
        $this->assertCount(2, $list);
    }

    public function test_viewer_cannot_list_company_invitations(): void
    {
        $viewer = User::factory()->create();
        $company = Company::factory()->create();
        $viewer->companies()->attach($company->id, ['role' => 'viewer']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $this->service->listCompanyInvitations($viewer, $company->id);
    }

    public function test_user_can_accept_their_invitation(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $inv = CompanyInvitation::factory()->create([
            'company_id' => $company->id,
            'invited_email' => $user->email,
            'role' => 'admin',
        ]);

        $companyId = $this->service->accept($user, $inv->token);

        $this->assertEquals($company->id, $companyId);
        $this->assertDatabaseHas('auth.company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('auth.company_invitations', [
            'id' => $inv->id,
            'status' => 'accepted',
        ]);
    }

    public function test_other_user_cannot_accept_invitation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $company = Company::factory()->create();
        $inv = CompanyInvitation::factory()->create([
            'company_id' => $company->id,
            'invited_email' => $user->email,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $this->service->accept($other, $inv->token);
    }

    public function test_admin_can_revoke_invitation(): void
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        $admin->companies()->attach($company->id, ['role' => 'admin']);
        $inv = CompanyInvitation::factory()->create([
            'company_id' => $company->id,
            'invited_by_user_id' => $admin->id,
        ]);

        $this->service->revoke($admin, $inv->id);

        $this->assertDatabaseHas('auth.company_invitations', [
            'id' => $inv->id,
            'status' => 'revoked',
        ]);
    }

    public function test_unauthorized_user_cannot_revoke_invitation(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $company = Company::factory()->create();
        $admin->companies()->attach($company->id, ['role' => 'admin']);
        $inv = CompanyInvitation::factory()->create([
            'company_id' => $company->id,
            'invited_by_user_id' => $admin->id,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $this->service->revoke($other, $inv->id);
    }
}
