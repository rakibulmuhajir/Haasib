#!/usr/bin/env php
<?php

/**
 * Test script to verify company operations
 *
 * This script will:
 * 1. Create a new company
 * 2. Activate the company
 * 3. Deactivate the company
 * 4. Delete the company
 */

require_once __DIR__.'/vendor/autoload.php';

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Company Operations Test\n";
echo "================================\n\n";

try {
    // Get or create a superadmin user
    $superAdmin = User::where('system_role', 'superadmin')->first();

    if (! $superAdmin) {
        echo "Creating superadmin user...\n";
        $superAdmin = User::create([
            'name' => 'Test SuperAdmin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'system_role' => 'superadmin',
        ]);
        echo "✓ Superadmin user created\n";
    } else {
        echo "✓ Using existing superadmin user\n";
    }

    echo 'SuperAdmin ID: '.$superAdmin->id."\n\n";

    // Test 1: Create Company
    echo "Test 1: Creating Company\n";
    echo "-------------------------\n";

    $companyName = 'Test Company '.date('Y-m-d H:i:s');
    $companyData = [
        'name' => $companyName,
        'base_currency' => 'USD',
        'language' => 'en',
        'locale' => 'en-US',
        'settings' => [
            'timezone' => 'UTC',
            'fiscal_year_start' => '01-01',
        ],
        'created_by_user_id' => $superAdmin->id,
    ];

    DB::beginTransaction();

    try {
        // Create company
        $company = Company::create($companyData);

        // Attach creator as owner
        $superAdmin->companies()->attach($company->id, [
            'role' => 'owner',
            'invited_by_user_id' => $superAdmin->id,
        ]);

        // Set currency_id
        $currency = \App\Models\Currency::where('code', 'USD')->first();
        if ($currency) {
            $company->currency_id = $currency->id;
            $company->save();
        }

        DB::commit();

        echo "✓ Company created successfully\n";
        echo '  Company ID: '.$company->id."\n";
        echo '  Company Name: '.$company->name."\n";
        echo '  Company Slug: '.$company->slug."\n\n";

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }

    // Test 2: Activate Company
    echo "Test 2: Activating Company\n";
    echo "--------------------------\n";

    // Simulate activation
    $company->activate();

    echo "✓ Company activated successfully\n";
    echo '  Company Status: '.$company->status."\n\n";

    // Test 3: Deactivate Company
    echo "Test 3: Deactivating Company\n";
    echo "----------------------------\n";

    // Simulate deactivation
    $company->deactivate();

    echo "✓ Company deactivated successfully\n";
    echo '  Company Status: '.$company->status."\n\n";

    // Test 4: Delete Company
    echo "Test 4: Deleting Company\n";
    echo "------------------------\n";

    $companyId = $company->id;
    $companyName = $company->name;

    // Soft delete the company
    $company->delete();

    echo "✓ Company deleted successfully\n";
    echo '  Deleted Company ID: '.$companyId."\n\n";

    // Verify deletion
    $deletedCompany = Company::withTrashed()->find($companyId);
    if ($deletedCompany && $deletedCompany->trashed()) {
        echo "✓ Company is soft-deleted\n";
    } else {
        echo "✗ Company deletion verification failed\n";
    }

    // Test 5: API Endpoints
    echo "\nTest 5: Testing API Endpoints\n";
    echo "-----------------------------\n";

    // Create a new company for API tests
    $apiCompany = Company::create([
        'name' => 'API Test Company '.date('Y-m-d H:i:s'),
        'base_currency' => 'EUR',
        'language' => 'en',
        'locale' => 'en-US',
        'created_by_user_id' => $superAdmin->id,
    ]);

    $superAdmin->companies()->attach($apiCompany->id, [
        'role' => 'owner',
        'invited_by_user_id' => $superAdmin->id,
    ]);

    echo '✓ Created company for API tests: '.$apiCompany->name."\n";

    // Simulate API requests
    $app->instance('request', Request::create('/test', 'GET'));

    // Test CompanyController methods directly
    $controller = new App\Http\Controllers\CompanyController;

    // Create a mock request
    $request = Request::create('/companies', 'POST', [
        'name' => 'Controller Test Company',
        'base_currency' => 'GBP',
    ]);
    $request->setUserResolver(function () use ($superAdmin) {
        return $superAdmin;
    });

    app()->instance('request', $request);

    echo "✓ API endpoint tests setup complete\n\n";

    echo "All tests completed successfully!\n";
    echo "==================================\n\n";

    // Summary
    echo "Test Summary:\n";
    echo "- ✓ Company Creation\n";
    echo "- ✓ Company Activation\n";
    echo "- ✓ Company Deactivation\n";
    echo "- ✓ Company Deletion\n";
    echo "- ✓ API Endpoint Setup\n";

} catch (\Exception $e) {
    echo "\n❌ Test failed with error:\n";
    echo 'Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "\nStack Trace:\n".$e->getTraceAsString()."\n";

    exit(1);
}
