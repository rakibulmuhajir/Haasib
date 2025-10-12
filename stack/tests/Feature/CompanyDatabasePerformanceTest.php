<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Database performance tests for company queries
beforeEach(function () {
    // Ensure we have proper indexes for testing
    if (! Schema::hasIndex('auth.companies', 'idx_companies_active')) {
        DB::statement('CREATE INDEX idx_companies_active ON auth.companies(is_active)');
    }

    if (! Schema::hasIndex('auth.companies', 'idx_companies_country')) {
        DB::statement('CREATE INDEX idx_companies_country ON auth.companies(country)');
    }

    if (! Schema::hasIndex('auth.companies', 'idx_companies_slug')) {
        DB::statement('CREATE INDEX idx_companies_slug ON auth.companies(slug)');
    }

    if (! Schema::hasIndex('auth.company_user', 'idx_company_user_user')) {
        DB::statement('CREATE INDEX idx_company_user_user ON auth.company_user(user_id)');
    }

    if (! Schema::hasIndex('auth.company_user', 'idx_company_user_company')) {
        DB::statement('CREATE INDEX idx_company_user_company ON auth.company_user(company_id)');
    }
});

it('uses indexes efficiently for company lookup by slug', function () {
    $user = User::factory()->create();
    $companies = Company::factory()->count(1000)->create();

    // Attach companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    $targetCompany = $companies->random();

    // Explain query to check index usage
    $explain = DB::select('
        EXPLAIN ANALYZE 
        SELECT * FROM auth.companies 
        WHERE slug = ? AND id IN (
            SELECT company_id FROM auth.company_user 
            WHERE user_id = ?
        )
    ', [$targetCompany->slug, $user->id]);

    // Check that the query uses an index
    $queryPlan = implode(' ', array_column($explain, 'QUERY PLAN'));
    expect($queryPlan)->toContain('Index');
    expect($queryPlan)->toContain('idx_companies_slug');

    // Test actual performance
    $startTime = microtime(true);

    $foundCompany = Company::where('slug', $targetCompany->slug)
        ->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->first();

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    expect($foundCompany->id)->toBe($targetCompany->id);
    expect($executionTime)->toBeLessThan(50); // Should be very fast with index
});

it('uses indexes efficiently for filtering by country', function () {
    $user = User::factory()->create();

    // Create companies in different countries
    $countries = ['US', 'SA', 'AE', 'GB', 'DE', 'FR', 'JP', 'CN'];
    $companies = collect();

    foreach ($countries as $country) {
        $countryCompanies = Company::factory()->count(125)->create(['country' => $country]);
        $companies->push(...$countryCompanies);
    }

    // Attach all companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    // Explain query to check index usage
    $explain = DB::select('
        EXPLAIN ANALYZE 
        SELECT c.* FROM auth.companies c
        INNER JOIN auth.company_user cu ON c.id = cu.company_id
        WHERE c.country = ? AND cu.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 50
    ', ['SA', $user->id]);

    $queryPlan = implode(' ', array_column($explain, 'QUERY PLAN'));
    expect($queryPlan)->toContain('Index');
    expect($queryPlan)->toContain('idx_companies_country');

    // Test actual performance
    $startTime = microtime(true);

    $saCompanies = Company::where('country', 'SA')
        ->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    expect($saCompanies)->toHaveCount(50);
    expect($executionTime)->toBeLessThan(100);
});

it('uses indexes efficiently for active company filtering', function () {
    $user = User::factory()->create();

    // Create mix of active and inactive companies
    Company::factory()->count(500)->create(['is_active' => true]);
    Company::factory()->count(500)->create(['is_active' => false]);

    // Attach all companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    // Explain query to check index usage
    $explain = DB::select('
        EXPLAIN ANALYZE 
        SELECT c.* FROM auth.companies c
        INNER JOIN auth.company_user cu ON c.id = cu.company_id
        WHERE c.is_active = ? AND cu.user_id = ?
        ORDER BY c.name ASC
        LIMIT 100
    ', [true, $user->id]);

    $queryPlan = implode(' ', array_column($explain, 'QUERY PLAN'));
    expect($queryPlan)->toContain('Index');
    expect($queryPlan)->toContain('idx_companies_active');

    // Test actual performance
    $startTime = microtime(true);

    $activeCompanies = Company::where('is_active', true)
        ->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->orderBy('name')
        ->limit(100)
        ->get();

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    expect($activeCompanies)->toHaveCount(100);
    expect($executionTime)->toBeLessThan(100);

    // Verify all returned companies are active
    $activeCompanies->each(fn ($company) => expect($company->is_active)->toBeTrue());
});

it('performs well with complex joins and filtering', function () {
    $user = User::factory()->create();

    // Create companies with various attributes
    Company::factory()->count(200)->create([
        'country' => 'SA',
        'base_currency' => 'SAR',
        'is_active' => true,
        'language' => 'ar',
    ]);

    Company::factory()->count(200)->create([
        'country' => 'US',
        'base_currency' => 'USD',
        'is_active' => true,
        'language' => 'en',
    ]);

    Company::factory()->count(100)->create([
        'country' => 'AE',
        'base_currency' => 'AED',
        'is_active' => false,
        'language' => 'ar',
    ]);

    // Attach all companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    // Complex query with multiple filters
    $startTime = microtime(true);

    $filteredCompanies = Company::where('country', 'SA')
        ->where('base_currency', 'SAR')
        ->where('is_active', true)
        ->where('language', 'ar')
        ->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('role', 'owner');
        })
        ->with(['users' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
        ->orderBy('name')
        ->get();

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    expect($filteredCompanies)->toHaveCount(200);
    expect($executionTime)->toBeLessThan(150);

    // Verify all filters are applied correctly
    $filteredCompanies->each(function ($company) {
        expect($company->country)->toBe('SA');
        expect($company->base_currency)->toBe('SAR');
        expect($company->is_active)->toBeTrue();
        expect($company->language)->toBe('ar');
    });
});

it('maintains performance with pagination on large datasets', function () {
    $user = User::factory()->create();

    // Create large dataset
    Company::factory()->count(5000)->create();

    // Attach all companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    $pages = [1, 10, 25, 50]; // Test various pages
    $perPage = 50;

    foreach ($pages as $page) {
        $startTime = microtime(true);

        $companiesPage = Company::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        expect($companiesPage)->toHaveCount($perPage);
        expect($executionTime)->toBeLessThan(200);

        // Verify correct page data
        $expectedOffset = ($page - 1) * $perPage;
        expect($companiesPage->firstItem())->toBe($expectedOffset + 1);
    }
});

it('measures query plan efficiency for user company access', function () {
    $user = User::factory()->create();
    $companies = Company::factory()->count(1000)->create();

    // Attach companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    // Test the most common query: user's companies
    $explain = DB::select('
        EXPLAIN ANALYZE 
        SELECT c.* FROM auth.companies c
        INNER JOIN auth.company_user cu ON c.id = cu.company_id
        WHERE cu.user_id = ? AND cu.is_active = true
        ORDER BY c.name ASC
        LIMIT 100
    ', [$user->id]);

    $queryPlan = implode(' ', array_column($explain, 'QUERY PLAN'));

    // Should use both company_user and companies indexes
    expect($queryPlan)->toContain('Index Scan');
    expect($queryPlan)->toContain('idx_company_user_user');

    // Test actual query performance
    $startTime = microtime(true);

    $userCompanies = Company::whereHas('users', function ($query) use ($user) {
        $query->where('user_id', $user->id)
            ->where('is_active', true);
    })
        ->orderBy('name')
        ->limit(100)
        ->get();

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    expect($userCompanies)->toHaveCount(100);
    expect($executionTime)->toBeLessThan(50);
});

it('tests index effectiveness with text search', function () {
    $user = User::factory()->create();

    // Create companies with searchable names
    Company::factory()->count(500)->create(['name' => 'Technology Company']);
    Company::factory()->count(500)->create(['name' => 'Manufacturing Company']);
    Company::factory()->count(500)->create(['name' => 'Service Company']);

    // Attach all companies to user
    Company::chunk(100, function ($companies) use ($user) {
        foreach ($companies as $company) {
            $company->users()->attach($user, ['role' => 'owner']);
        }
    });

    // Test ILIKE query with index
    $startTime = microtime(true);

    $techCompanies = Company::where('name', 'ILIKE', '%Technology%')
        ->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->get();

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    expect($techCompanies)->toHaveCount(500);
    expect($executionTime)->toBeLessThan(200);

    // All results should contain 'Technology'
    $techCompanies->each(fn ($company) => expect($company->name)->toContain('Technology')
    );
});
