<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommandsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_create(): void
    {
        $actor = User::factory()->create(['system_role' => 'superadmin']);
        $company = Company::factory()->create();
        $res = $this->actingAs($actor)
            ->withSession(['current_company_id' => $company->id])
            ->postJson('/commands', [
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ], [
                'X-Action' => 'user.create',
                'X-Idempotency-Key' => 'u1',
            ]);
        $res->assertStatus(200)->assertJsonPath('data.email', 'alice@example.com');
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_company_create(): void
    {
        $actor = User::factory()->create(['system_role' => 'superadmin']);
        $res = $this->actingAs($actor)
            ->withSession(['current_company_id' => null])
            ->postJson('/commands', ['name' => 'Acme'], [
                'X-Action' => 'company.create',
                'X-Idempotency-Key' => 'c1',
            ]);
        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data.slug'));
        $this->assertDatabaseHas('auth.companies', ['name' => 'Acme']);
    }

    public function test_assign_and_unassign(): void
    {
        $actor = User::factory()->create(['system_role' => 'superadmin']);
        $company = Company::factory()->create();
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($actor)->withSession(['current_company_id' => $company->id]);
        $this->setTenant($company->id);

        $assign = $this->postJson('/commands', [
            'email' => $user->email,
            'company' => $company->id,
            'role' => 'admin',
        ], [
            'X-Action' => 'company.assign',
            'X-Idempotency-Key' => 'a1',
        ]);
        $assign->assertStatus(200);
        $this->assertDatabaseHas('auth.company_user', ['company_id' => $company->id, 'user_id' => $user->id]);

        $unassign = $this->postJson('/commands', [
            'email' => $user->email,
            'company' => $company->id,
        ], [
            'X-Action' => 'company.unassign',
            'X-Idempotency-Key' => 'a2',
        ]);
        $unassign->assertStatus(200);
        $this->assertDatabaseMissing('auth.company_user', ['company_id' => $company->id, 'user_id' => $user->id]);
    }

    public function test_idempotent_replay_returns_409(): void
    {
        $actor = User::factory()->create(['system_role' => 'superadmin']);
        $company = Company::factory()->create();
        $this->actingAs($actor)->withSession(['current_company_id' => $company->id]);

        $headers = ['X-Action' => 'company.create', 'X-Idempotency-Key' => 'dup'];
        $this->postJson('/commands', ['name' => 'Beta'], $headers)->assertStatus(200);
        $this->postJson('/commands', ['name' => 'Beta'], $headers)->assertStatus(409);
    }

    public function test_company_delete(): void
    {
        $actor = User::factory()->create(['system_role' => 'superadmin']);
        $company = Company::factory()->create(['name' => 'ToRemoveCo']);

        $this->actingAs($actor)
            ->withSession(['current_company_id' => null])
            ->postJson('/commands', [
                'company' => $company->id,
            ], [
                'X-Action' => 'company.delete',
                'X-Idempotency-Key' => 'd1',
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('auth.companies', ['id' => $company->id]);
    }

    public function test_company_name_unique(): void
    {
        $actor = User::factory()->create(['system_role' => 'superadmin']);

        $res1 = $this->actingAs($actor)
            ->postJson('/commands', ['name' => 'Acme'], [
                'X-Action' => 'company.create',
                'X-Idempotency-Key' => 'u-1',
            ]);
        $res1->assertStatus(200);

        $res2 = $this->actingAs($actor)
            ->postJson('/commands', ['name' => 'Acme'], [
                'X-Action' => 'company.create',
                'X-Idempotency-Key' => 'u-2',
            ]);
        $this->assertTrue(in_array($res2->status(), [422, 500]), 'Expected validation error status');
    }

    public function test_unauthorized_assign(): void
    {
        $actor = User::factory()->create();
        $company = Company::factory()->create();
        $target = User::factory()->create(['email' => 't@example.com']);

        $this->actingAs($actor)->withSession(['current_company_id' => $company->id]);
        $this->setTenant($company->id);

        $res = $this->postJson('/commands', [
            'email' => $target->email,
            'company' => $company->id,
            'role' => 'admin',
        ], [
            'X-Action' => 'company.assign',
            'X-Idempotency-Key' => 'z1',
        ]);
        $res->assertStatus(403);
    }
}
