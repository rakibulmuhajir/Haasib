<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\{artisan};

uses(RefreshDatabase::class);

it('creates a company with required options', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    
    artisan('company:create', [
        '--name' => 'Test Company LLC',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user->id
    ])
        ->assertExitCode(0)
        ->expectsOutput('✓ Company "Test Company LLC" created successfully')
        ->expectsOutput('ID: ')
        ->expectsOutput('Slug: test-company-llc')
        ->expectsOutput('Currency: USD')
        ->expectsOutput('Timezone: America/New_York')
        ->expectsOutput('✓ Fiscal year created with 12 monthly periods')
        ->expectsOutput('✓ Chart of accounts created with default accounts');
});

it('creates a company with all options', function () {
    $user = User::factory()->create(['email' => 'founder@example.com']);
    
    artisan('company:create', [
        '--name' => 'Complete Company Inc',
        '--currency' => 'EUR',
        '--timezone' => 'Europe/London',
        '--country' => 'GB',
        '--language' => 'en',
        '--locale' => 'en_GB',
        '--user' => $user->id
    ])
        ->assertExitCode(0)
        ->expectsOutput('✓ Company "Complete Company Inc" created successfully')
        ->expectsOutput('ID: ')
        ->expectsOutput('Slug: complete-company-inc')
        ->expectsOutput('Currency: EUR')
        ->expectsOutput('Timezone: Europe/London')
        ->expectsOutput('Country: GB')
        ->expectsOutput('Language: en')
        ->expectsOutput('Locale: en_GB');
});

it('requires name option', function () {
    artisan('company:create', [
        '--currency' => 'USD',
        '--timezone' => 'America/New_York'
    ])
        ->assertExitCode(1);
});

it('requires currency option', function () {
    artisan('company:create', [
        '--name' => 'Test Company',
        '--timezone' => 'America/New_York'
    ])
        ->assertExitCode(1);
});

it('requires timezone option', function () {
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'USD'
    ])
        ->assertExitCode(1);
});

it('validates currency format', function () {
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'INVALID',
        '--timezone' => 'America/New_York'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid currency code. Must be a 3-letter ISO code.');
});

it('validates timezone format', function () {
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'USD',
        '--timezone' => 'Invalid/Timezone'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid timezone identifier.');
});

it('validates country format', function () {
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--country' => 'USA' // Should be 2 letters
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid country code. Must be a 2-letter ISO code.');
});

it('creates company and assigns user as owner', function () {
    $user = User::factory()->create();
    
    artisan('company:create', [
        '--name' => 'Owner Test Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user->id
    ])
        ->assertExitCode(0);
    
    // Verify company was created
    $this->assertDatabaseHas('auth.companies', [
        'name' => 'Owner Test Company',
        'currency' => 'USD',
        'timezone' => 'America/New_York',
        'created_by_user_id' => $user->id,
        'is_active' => true
    ]);
    
    // Verify user is assigned as owner
    $this->assertDatabaseHas('auth.company_user', [
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value,
        'is_active' => true
    ]);
});

it('creates fiscal year automatically', function () {
    $user = User::factory()->create();
    
    artisan('company:create', [
        '--name' => 'Fiscal Year Test',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user->id
    ])
        ->assertExitCode(0);
    
    // Get the created company
    $company = DB::table('auth.companies')
        ->where('name', 'Fiscal Year Test')
        ->first();
    
    // Verify fiscal year was created
    $this->assertDatabaseHas('accounting.fiscal_years', [
        'company_id' => $company->id,
        'is_current' => true,
        'is_locked' => false
    ]);
});

it('creates chart of accounts automatically', function () {
    $user = User::factory()->create();
    
    artisan('company:create', [
        '--name' => 'Chart Test Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user->id
    ])
        ->assertExitCode(0);
    
    // Get the created company
    $company = DB::table('auth.companies')
        ->where('name', 'Chart Test Company')
        ->first();
    
    // Verify chart of accounts was created
    $this->assertDatabaseHas('accounting.chart_of_accounts', [
        'company_id' => $company->id,
        'is_active' => true,
        'is_template' => false
    ]);
});

it('creates default accounts automatically', function () {
    $user = User::factory()->create();
    
    artisan('company:create', [
        '--name' => 'Accounts Test Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user->id
    ])
        ->assertExitCode(0);
    
    // Get the created company and chart of accounts
    $company = DB::table('auth.companies')
        ->where('name', 'Accounts Test Company')
        ->first();
    
    $chartOfAccounts = DB::table('accounting.chart_of_accounts')
        ->where('company_id', $company->id)
        ->first();
    
    // Verify accounts were created
    $accountCount = DB::table('accounting.accounts')
        ->where('chart_of_accounts_id', $chartOfAccounts->id)
        ->count();
    
    expect($accountCount)->toBeGreaterThan(0);
});

it('generates unique slug for company name', function () {
    $user = User::factory()->create();
    
    // Create first company
    artisan('company:create', [
        '--name' => 'Duplicate Name LLC',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user->id
    ])
        ->assertExitCode(0);
    
    // Create second company with same name
    artisan('company:create', [
        '--name' => 'Duplicate Name LLC',
        '--currency' => 'EUR',
        '--timezone' => 'Europe/London',
        '--user' => $user->id
    ])
        ->assertExitCode(0);
    
    // Verify both companies exist with different slugs
    $companies = DB::table('auth.companies')
        ->where('name', 'Duplicate Name LLC')
        ->orderBy('created_at')
        ->get();
    
    expect($companies)->toHaveCount(2);
    expect($companies[0]->slug)->toBe('duplicate-name-llc');
    expect($companies[1]->slug)->toContain('duplicate-name-llc-');
    expect($companies[0]->slug)->not->toBe($companies[1]->slug);
});

it('handles company creation for different currencies', function () {
    $user = User::factory()->create();
    
    $currencies = ['USD', 'EUR', 'GBP', 'JPY', 'CAD'];
    
    foreach ($currencies as $currency) {
        $companyName = "Test Company {$currency}";
        
        artisan('company:create', [
            '--name' => $companyName,
            '--currency' => $currency,
            '--timezone' => 'America/New_York',
            '--user' => $user->id
        ])
            ->assertExitCode(0)
            ->expectsOutput("✓ Company \"{$companyName}\" created successfully")
            ->expectsOutput("Currency: {$currency}");
        
        // Verify company was created with correct currency
        $this->assertDatabaseHas('auth.companies', [
            'name' => $companyName,
            'currency' => $currency
        ]);
    }
});

it('handles company creation for different timezones', function () {
    $user = User::factory()->create();
    
    $timezones = [
        'America/New_York',
        'Europe/London',
        'Asia/Tokyo',
        'Australia/Sydney'
    ];
    
    foreach ($timezones as $timezone) {
        $companyName = "Timezone Test " . str_replace('/', '_', $timezone);
        
        artisan('company:create', [
            '--name' => $companyName,
            '--currency' => 'USD',
            '--timezone' => $timezone,
            '--user' => $user->id
        ])
            ->assertExitCode(0)
            ->expectsOutput("Timezone: {$timezone}");
        
        // Verify company was created with correct timezone
        $this->assertDatabaseHas('auth.companies', [
            'name' => $companyName,
            'timezone' => $timezone
        ]);
    }
});

it('handles company creation with different locales', function () {
    $user = User::factory()->create();
    
    $locales = [
        ['language' => 'en', 'locale' => 'en_US'],
        ['language' => 'es', 'locale' => 'es_ES'],
        ['language' => 'fr', 'locale' => 'fr_FR'],
        ['language' => 'de', 'locale' => 'de_DE']
    ];
    
    foreach ($locales as $localeData) {
        $companyName = "Locale Test {$localeData['language']}";
        
        artisan('company:create', [
            '--name' => $companyName,
            '--currency' => 'USD',
            '--timezone' => 'America/New_York',
            '--language' => $localeData['language'],
            '--locale' => $localeData['locale'],
            '--user' => $user->id
        ])
            ->assertExitCode(0)
            ->expectsOutput("Language: {$localeData['language']}")
            ->expectsOutput("Locale: {$localeData['locale']}");
        
        // Verify company was created with correct locale
        $this->assertDatabaseHas('auth.companies', [
            'name' => $companyName,
            'language' => $localeData['language'],
            'locale' => $localeData['locale']
        ]);
    }
});

it('handles company creation for specific user', function () {
    $user1 = User::factory()->create(['email' => 'user1@example.com']);
    $user2 = User::factory()->create(['email' => 'user2@example.com']);
    
    // Create company for user1
    artisan('company:create', [
        '--name' => 'User1 Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--user' => $user1->id
    ])
        ->assertExitCode(0);
    
    // Verify company is assigned to user1
    $company = DB::table('auth.companies')
        ->where('name', 'User1 Company')
        ->first();
    
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $user1->id,
        'role' => CompanyRole::Owner->value
    ]);
    
    // Verify company is NOT assigned to user2
    $this->assertDatabaseMissing('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $user2->id
    ]);
});

it('handles company creation with country codes', function () {
    $user = User::factory()->create();
    
    $countries = ['US', 'GB', 'CA', 'AU', 'DE'];
    
    foreach ($countries as $country) {
        $companyName = "Country Test {$country}";
        
        artisan('company:create', [
            '--name' => $companyName,
            '--currency' => 'USD',
            '--timezone' => 'America/New_York',
            '--country' => $country,
            '--user' => $user->id
        ])
            ->assertExitCode(0)
            ->expectsOutput("Country: {$country}");
        
        // Verify company was created with correct country
        $this->assertDatabaseHas('auth.companies', [
            'name' => $companyName,
            'country' => $country
        ]);
    }
});

it('provides clear success output with all company details', function () {
    $user = User::factory()->create();
    
    artisan('company:create', [
        '--name' => 'Detailed Test Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--country' => 'US',
        '--language' => 'en',
        '--locale' => 'en_US',
        '--user' => $user->id
    ])
        ->assertExitCode(0)
        ->expectsOutput('✓ Company "Detailed Test Company" created successfully')
        ->expectsOutput('ID: ') // Should show UUID
        ->expectsOutput('Slug: detailed-test-company')
        ->expectsOutput('Currency: USD')
        ->expectsOutput('Timezone: America/New_York')
        ->expectsOutput('Country: US')
        ->expectsOutput('Language: en')
        ->expectsOutput('Locale: en_US')
        ->expectsOutput('✓ Fiscal year created with 12 monthly periods')
        ->expectsOutput('✓ Chart of accounts created with default accounts');
});

it('handles errors gracefully with helpful messages', function () {
    // Test invalid currency
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'XYZ',
        '--timezone' => 'America/New_York'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid currency code. Must be a 3-letter ISO code.');
    
    // Test invalid timezone
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'USD',
        '--timezone' => 'Fake/Timezone'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid timezone identifier.');
    
    // Test invalid country
    artisan('company:create', [
        '--name' => 'Test Company',
        '--currency' => 'USD',
        '--timezone' => 'America/New_York',
        '--country' => 'XYZ'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid country code. Must be a 2-letter ISO code.');
});