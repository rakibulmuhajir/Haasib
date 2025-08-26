<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_members_in_current_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'owner']);

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($user)->allows('company.manageMembers'));
    }

    public function test_admin_can_manage_members_in_current_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($user)->allows('company.manageMembers'));

    }

    public function test_viewer_cannot_manage_members(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'viewer']);

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertFalse(Gate::allows('company.manageMembers'));
    }

    public function test_accountant_can_post_journal(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'accountant']);

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertTrue(Gate::allows('ledger.postJournal'));
    }

    public function test_viewer_cannot_post_journal(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'viewer']);

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertFalse(Gate::allows('ledger.postJournal'));
    }

    public function test_member_can_view_ledger(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'viewer']);

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertTrue(Gate::allows('ledger.view'));
    }

    public function test_non_member_cannot_view_ledger(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user);
        $this->setTenant($company->id);

        $this->assertFalse(Gate::allows('ledger.view'));
    }
}
