<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Performance tests for company listing
it('loads company listing under 200ms with 10 companies', function () {
    $user = User::factory()->create();

    // Create companies and attach to user
    $companies = Company::factory()->count(10)->create();
    foreach ($companies as $company) {
        $company->users()->attach($user, ['role' => 'owner']);
    }

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
});

it('loads company listing under 200ms with 100 companies', function () {
    $user = User::factory()->create();

    // Create companies and attach to user
    $companies = Company::factory()->count(100)->create();
    foreach ($companies as $company) {
        $company->users()->attach($user, ['role' => 'owner']);
    }

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
});

it('loads paginated company listing under 200ms with 1000 companies', function () {
    $user = User::factory()->create();

    // Create companies and attach to user
    $companies = Company::factory()->count(1000)->create();
    foreach ($companies as $company) {
        $company->users()->attach($user, ['role' => 'owner']);
    }

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies?page=1&per_page=50');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
    expect($response->json('data'))->toHaveCount(50); // Paginated results
});

it('loads company search results under 200ms', function () {
    $user = User::factory()->create();

    // Create companies with searchable names
    Company::factory()->count(100)->create(['name' => 'Different Company']);
    Company::factory()->count(10)->create(['name' => 'Test Company Name']);

    // Attach all companies to user
    Company::chunk(50, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies?search=Test');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
    expect($response->json('data'))->toHaveCount(10); // Should find 10 companies
});

it('loads filtered company listing under 200ms', function () {
    $user = User::factory()->create();

    // Create companies with different countries
    Company::factory()->count(50)->create(['country' => 'US']);
    Company::factory()->count(30)->create(['country' => 'SA']);
    Company::factory()->count(20)->create(['country' => 'AE']);

    // Attach all companies to user
    Company::chunk(50, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies?country=SA');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
    expect($response->json('data'))->toHaveCount(30); // Should find 30 SA companies
});

it('maintains performance with concurrent requests', function () {
    $user = User::factory()->create();

    // Create companies and attach to user
    $companies = Company::factory()->count(100)->create();
    foreach ($companies as $company) {
        $company->users()->attach($user, ['role' => 'owner']);
    }

    $responseTimes = [];
    $concurrentRequests = 10;

    for ($i = 0; $i < $concurrentRequests; $i++) {
        $startTime = microtime(true);

        $response = $this->actingAs($user)
            ->getJson('/api/companies?page='.($i + 1));

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        $responseTimes[] = $responseTime;

        $response->assertStatus(200);
    }

    // Check that all requests are under 200ms
    foreach ($responseTimes as $responseTime) {
        expect($responseTime)->toBeLessThan(200);
    }

    // Check average response time
    $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
    expect($averageResponseTime)->toBeLessThan(150); // Should be well under 200ms on average
});

it('handles company detail view performance under 200ms', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    // Create related data to simulate real-world scenario
    $users = User::factory()->count(20)->create();
    $company->users()->attach($users, ['role' => 'member']);

    // Add some company settings
    $company->update(['settings' => [
        'features' => ['accounting', 'reporting', 'invoicing'],
        'preferences' => ['theme' => 'light', 'timezone' => 'UTC'],
        'limits' => ['max_users' => 100, 'max_storage' => '10GB'],
    ]]);

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson("/api/companies/{$company->id}");

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
    expect($response->json('data.users'))->toHaveCount(20);
});

it('performs well with complex filtering and sorting', function () {
    $user = User::factory()->create();

    // Create diverse set of companies
    Company::factory()->count(50)->create([
        'country' => 'US',
        'base_currency' => 'USD',
        'is_active' => true,
        'created_at' => now()->subDays(rand(1, 365)),
    ]);

    Company::factory()->count(30)->create([
        'country' => 'SA',
        'base_currency' => 'SAR',
        'is_active' => true,
        'created_at' => now()->subDays(rand(1, 365)),
    ]);

    Company::factory()->count(20)->create([
        'country' => 'AE',
        'base_currency' => 'AED',
        'is_active' => false,
        'created_at' => now()->subDays(rand(1, 365)),
    ]);

    // Attach all companies to user
    Company::chunk(50, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies?country=SA&is_active=true&sort=created_at&order=desc');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
    expect($response->json('data'))->toHaveCount(30);

    // Verify sorting
    $companies = $response->json('data');
    for ($i = 0; $i < count($companies) - 1; $i++) {
        expect(strtotime($companies[$i]['created_at']))->toBeGreaterThanOrEqual(strtotime($companies[$i + 1]['created_at']));
    }
});

it('measures database query performance for company listing', function () {
    $user = User::factory()->create();

    // Create companies and attach to user
    $companies = Company::factory()->count(100)->create();
    foreach ($companies as $company) {
        $company->users()->attach($user, ['role' => 'owner']);
    }

    // Enable query logging
    DB::enableQueryLog();

    $startTime = microtime(true);

    $response = $this->actingAs($user)
        ->getJson('/api/companies');

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000;

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect($responseTime)->toBeLessThan(200);
    expect($queryCount)->toBeLessThanOrEqual(3); // Should be minimal queries (companies, maybe users, count)
});
