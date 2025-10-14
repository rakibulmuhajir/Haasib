<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\{actingAs, postJson, getJson};

uses(RefreshDatabase::class);

it('completes full company registration flow successfully', function () {
    $user = User::factory()->create([
        'email' => 'founder@example.com',
        'name' => 'John Doe'
    ]);
    
    // Step 1: Create the company
    $createResponse = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Tech Innovations LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'US',
            'language' => 'en',
            'locale' => 'en_US'
        ])
        ->assertCreated();
    
    $companyId = $createResponse->json('data.id');
    
    // Verify company was created with correct data
    expect($createResponse->json('data.name'))->toBe('Tech Innovations LLC');
    expect($createResponse->json('data.currency'))->toBe('USD');
    expect($createResponse->json('data.slug'))->toBe('tech-innovations-llc');
    expect($createResponse->json('data.is_active'))->toBeTrue();
    
    // Verify fiscal year and chart of accounts were created
    expect($createResponse->json('meta.fiscal_year_created'))->toBeTrue();
    expect($createResponse->json('meta.chart_of_accounts_created'))->toBeTrue();
    
    // Step 2: Verify user is assigned as owner
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $companyId,
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value,
        'is_active' => true
    ]);
    
    // Step 3: Retrieve company details
    $detailsResponse = actingAs($user)
        ->getJson("/api/v1/companies/{$companyId}")
        ->assertOk();
    
    // Verify all expected fields are present
    expect($detailsResponse->json('data'))->toHaveKeys([
        'id', 'name', 'slug', 'currency', 'timezone', 'country', 'language', 'locale',
        'is_active', 'created_at', 'updated_at', 'fiscal_year', 'user_role'
    ]);
    
    // Verify user role is owner
    expect($detailsResponse->json('data.user_role'))->toBe(CompanyRole::Owner->value);
    
    // Verify fiscal year information
    expect($detailsResponse->json('data.fiscal_year'))->toHaveKeys([
        'id', 'name', 'start_date', 'end_date', 'is_current'
    ]);
    expect($detailsResponse->json('data.fiscal_year.is_current'))->toBeTrue();
    
    // Step 4: Switch to company context
    $switchResponse = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $companyId
        ])
        ->assertOk();
    
    // Verify context switch was successful
    expect($switchResponse->json('data.company.id'))->toBe($companyId);
    expect($switchResponse->json('data.company.name'))->toBe('Tech Innovations LLC');
    expect($switchResponse->json('data.user_role'))->toBe(CompanyRole::Owner->value);
    expect($switchResponse->json('data.fiscal_year.is_current'))->toBeTrue();
    
    // Step 5: Verify current context
    $currentContextResponse = actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    expect($currentContextResponse->json('data.company.id'))->toBe($companyId);
    expect($currentContextResponse->json('data.user_role'))->toBe(CompanyRole::Owner->value);
    expect($currentContextResponse->json('data.available_companies'))->toHaveCount(1);
    expect($currentContextResponse->json('data.available_companies.0.role'))->toBe(CompanyRole::Owner->value);
});

it('handles company registration with minimal data', function () {
    $user = User::factory()->create();
    
    // Create company with minimal required fields
    $response = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Minimal Co',
            'currency' => 'EUR',
            'timezone' => 'Europe/London'
        ])
        ->assertCreated();
    
    $companyId = $response->json('data.id');
    
    // Verify defaults were applied
    expect($response->json('data.language'))->toBe('en');
    expect($response->json('data.locale'))->toBe('en_US');
    expect($response->json('data.country'))->toBeNull();
    
    // Verify database state
    $this->assertDatabaseHas('auth.companies', [
        'id' => $companyId,
        'name' => 'Minimal Co',
        'currency' => 'EUR',
        'timezone' => 'Europe/London',
        'language' => 'en',
        'locale' => 'en_US',
        'country' => null
    ]);
    
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $companyId,
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value
    ]);
});

it('creates company with proper database relationships', function () {
    $user = User::factory()->create();
    
    $response = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Database Test Inc',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'country' => 'GB'
        ])
        ->assertCreated();
    
    $companyId = $response->json('data.id');
    
    // Verify company record exists
    $this->assertDatabaseHas('auth.companies', [
        'id' => $companyId,
        'name' => 'Database Test Inc',
        'currency' => 'GBP',
        'timezone' => 'Europe/London',
        'country' => 'GB',
        'created_by_user_id' => $user->id,
        'is_active' => true
    ]);
    
    // Verify user-company relationship exists
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $companyId,
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value,
        'is_active' => true
    ]);
    
    // Verify fiscal year was created
    $this->assertDatabaseHas('accounting.fiscal_years', [
        'company_id' => $companyId,
        'is_current' => true,
        'is_locked' => false
    ]);
    
    // Verify chart of accounts was created
    $this->assertDatabaseHas('accounting.chart_of_accounts', [
        'company_id' => $companyId,
        'is_active' => true,
        'is_template' => false
    ]);
    
    // Verify account types were seeded (should exist from migrations)
    $this->assertDatabaseHas('accounting.account_types', [
        'name' => 'Assets'
    ]);
    
    // Verify default accounts were created
    $this->assertDatabaseHas('accounting.accounts', [
        'chart_of_accounts_id' => function ($query) use ($companyId) {
            return $query->select('id')
                ->from('accounting.chart_of_accounts')
                ->where('company_id', $companyId);
        }
    ]);
});

it('creates company with audit trail', function () {
    $user = User::factory()->create();
    
    $response = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Audit Trail Company',
            'currency' => 'CAD',
            'timezone' => 'America/Toronto',
            'country' => 'CA'
        ])
        ->assertCreated();
    
    $companyId = $response->json('data.id');
    
    // Verify created_at and updated_at timestamps are set
    $company = DB::table('auth.companies')
        ->where('id', $companyId)
        ->first();
    
    expect($company->created_at)->not->toBeNull();
    expect($company->updated_at)->not->toBeNull();
    expect($company->created_at)->toEqual($company->updated_at);
    
    // Verify user-company relationship has timestamps
    $companyUser = DB::table('auth.company_user')
        ->where('company_id', $companyId)
        ->where('user_id', $user->id)
        ->first();
    
    expect($companyUser->created_at)->not->toBeNull();
    expect($companyUser->updated_at)->not->toBeNull();
    expect($companyUser->created_at)->toEqual($companyUser->updated_at);
});

it('handles multiple company creation for same user', function () {
    $user = User::Factory()->create();
    
    // Create first company
    $firstResponse = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'First Company',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();
    
    $firstCompanyId = $firstResponse->json('data.id');
    
    // Create second company
    $secondResponse = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Second Company',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris'
        ])
        ->assertCreated();
    
    $secondCompanyId = $secondResponse->json('data.id');
    
    // Verify both companies exist
    $this->assertDatabaseHas('auth.companies', [
        'id' => $firstCompanyId,
        'name' => 'First Company',
        'currency' => 'USD'
    ]);
    
    $this->assertDatabaseHas('auth.companies', [
        'id' => $secondCompanyId,
        'name' => 'Second Company',
        'currency' => 'EUR'
    ]);
    
    // Verify user is owner of both companies
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $firstCompanyId,
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value
    ]);
    
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $secondCompanyId,
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value
    ]);
    
    // Verify user can access both companies
    actingAs($user)
        ->getJson("/api/v1/companies/{$firstCompanyId}")
        ->assertOk();
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$secondCompanyId}")
        ->assertOk();
    
    // Verify context shows both available companies
    $contextResponse = actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    expect($contextResponse->json('data.available_companies'))->toHaveCount(2);
});

it('creates unique slugs for companies with same name', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    // Create company with same name for different users
    $firstResponse = actingAs($user1)
        ->postJson('/api/companies', [
            'name' => 'Common Name LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();
    
    $secondResponse = actingAs($user2)
        ->postJson('/api/companies', [
            'name' => 'Common Name LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();
    
    $firstSlug = $firstResponse->json('data.slug');
    $secondSlug = $secondResponse->json('data.slug');
    
    // Verify slugs are different
    expect($firstSlug)->not->toBe($secondSlug);
    expect($firstSlug)->toBe('common-name-llc');
    expect($secondSlug)->toContain('common-name-llc-');
});

it('validates company name uniqueness within same user', function () {
    $user = User::factory()->create();
    
    // Create first company
    actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Duplicate Test Co',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();
    
    // Attempt to create second company with same name
    actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Duplicate Test Co',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name'])
        ->assertJsonFragment([
            'message' => 'You already have a company with this name.'
        ]);
});

it('creates company with proper fiscal year structure', function () {
    $user = User::factory()->create();
    
    $response = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Fiscal Year Test Inc',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();
    
    $companyId = $response->json('data.id');
    
    // Get company details to verify fiscal year
    $detailsResponse = actingAs($user)
        ->getJson("/api/v1/companies/{$companyId}")
        ->assertOk();
    
    $fiscalYear = $detailsResponse->json('data.fiscal_year');
    
    // Verify fiscal year structure
    expect($fiscalYear['name'])->toBeString();
    expect($fiscalYear['start_date'])->toBeString();
    expect($fiscalYear['end_date'])->toBeString();
    expect($fiscalYear['is_current'])->toBeTrue();
    
    // Verify start_date is before end_date
    expect($fiscalYear['start_date'])->toBeLessThan($fiscalYear['end_date']);
    
    // Verify fiscal year exists in database
    $this->assertDatabaseHas('accounting.fiscal_years', [
        'id' => $fiscalYear['id'],
        'company_id' => $companyId,
        'name' => $fiscalYear['name'],
        'is_current' => true
    ]);
});

it('creates company with proper chart of accounts structure', function () {
    $user = User::factory()->create();
    
    $response = actingAs($user)
        ->postJson('/api/companies', [
            'name' => 'Chart of Accounts Test',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();
    
    $companyId = $response->json('data.id');
    
    // Verify chart of accounts exists
    $this->assertDatabaseHas('accounting.chart_of_accounts', [
        'company_id' => $companyId,
        'is_active' => true,
        'is_template' => false
    ]);
    
    // Get chart of accounts ID
    $chartOfAccounts = DB::table('accounting.chart_of_accounts')
        ->where('company_id', $companyId)
        ->first();
    
    // Verify accounts were created
    $accountCount = DB::table('accounting.accounts')
        ->where('chart_of_accounts_id', $chartOfAccounts->id)
        ->count();
    
    expect($accountCount)->toBeGreaterThan(0);
    
    // Verify accounts are linked to valid account types
    $accounts = DB::table('accounting.accounts')
        ->where('chart_of_accounts_id', $chartOfAccounts->id)
        ->get();
    
    foreach ($accounts as $account) {
        $this->assertDatabaseHas('accounting.account_types', [
            'id' => $account->account_type_id
        ]);
    }
});