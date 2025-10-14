<?php

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\Module;
use App\Models\User;
use App\Models\CompanyUser;
use App\Services\ContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->contextService = app(ContextService::class);
});

function actingSuperAdminWithCompanyForEnable(): array
{
    $user = User::factory()->superAdmin()->create([
        'password' => Hash::make('password123'),
    ]);

    $company = Company::factory()->create();

    actingAs($user);

    app(ContextService::class)->setCurrentCompany($user, $company);

    return [$user, $company];
}

it('enables module for company successfully', function () {
    [$user, $company] = actingSuperAdminWithCompanyForEnable();

    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->postJson("/api/v1/modules/{$module->id}/enable");

    $response->assertOk();

    $companyModule = CompanyModule::where('company_id', $company->id)
        ->where('module_id', $module->id)
        ->first();

    expect($companyModule)->not->toBeNull();
    expect($companyModule->is_active)->toBeTrue();
});

it('fails to enable non-existent module', function () {
    [$user] = actingSuperAdminWithCompanyForEnable();

    $response = $this->postJson('/api/v1/modules/'.Str::uuid().'/enable');

    $response->assertNotFound()
        ->assertJson(['message' => 'Module not found']);
});

it('fails to enable module with invalid UUID format', function () {
    [$user] = actingSuperAdminWithCompanyForEnable();

    $response = $this->postJson('/api/v1/modules/invalid-uuid-format/enable');

    $response->assertStatus(422);
});

it('fails to enable inactive system module', function () {
    [$user] = actingSuperAdminWithCompanyForEnable();

    $module = Module::factory()->create([
        'is_active' => false,
    ]);

    $response = $this->postJson("/api/v1/modules/{$module->id}/enable");

    $response->assertStatus(400)
        ->assertJson(['message' => 'Module is not available']);
});

it('idempotently enables already enabled module', function () {
    [$user, $company] = actingSuperAdminWithCompanyForEnable();

    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    CompanyModule::factory()->create([
        'company_id' => $company->id,
        'module_id' => $module->id,
        'is_active' => true,
    ]);

    $response = $this->postJson("/api/v1/modules/{$module->id}/enable");

    $response->assertOk();

    $companyModule = CompanyModule::where('company_id', $company->id)
        ->where('module_id', $module->id)
        ->first();

    expect($companyModule->is_active)->toBeTrue();
});

it('enables previously disabled module', function () {
    [$user, $company] = actingSuperAdminWithCompanyForEnable();

    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    CompanyModule::factory()->create([
        'company_id' => $company->id,
        'module_id' => $module->id,
        'is_active' => false,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $response = $this->postJson("/api/v1/modules/{$module->id}/enable");

    $response->assertOk();

    $companyModule = CompanyModule::where('company_id', $company->id)
        ->where('module_id', $module->id)
        ->first();

    expect($companyModule->is_active)->toBeTrue();
    expect($companyModule->updated_at->gt($companyModule->created_at))->toBeTrue();
});

it('fails when no company context is set', function () {
    $user = User::factory()->superAdmin()->create([
        'password' => Hash::make('password123'),
    ]);

    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/modules/{$module->id}/enable");

    $response->assertStatus(400)
        ->assertJson(['message' => 'No company context']);
});

it('denies non-superadmin even with permissions', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
    ]);

    $company = Company::factory()->create();

    actingAs($user);

    CompanyUser::factory()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'role' => 'owner',
    ]);

    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->postJson("/api/v1/modules/{$module->id}/enable");

    $response->assertStatus(403)
        ->assertJson(['message' => 'Only system administrators can manage modules']);
});
