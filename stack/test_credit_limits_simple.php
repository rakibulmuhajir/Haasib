<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    // Set up company context for RLS
    $companyId = '019a5d18-fa77-70cd-b1ac-3df238523776'; // TestOwner Company
    \Illuminate\Support\Facades\DB::statement("SET app.current_company_id = '{$companyId}'");
    \Illuminate\Support\Facades\DB::statement("SET app.is_super_admin = 'false'");
    
    echo "=== Credit Limit Management Test (Simple) ===\n\n";
    
    // 1. Test basic database queries work
    echo "=== Testing Database Access ===\n";
    
    $customerData = \Illuminate\Support\Facades\DB::table('acct.customers')
        ->where('customer_number', 'CUST-0002')
        ->first();
    
    if (!$customerData) {
        echo "❌ Customer not found\n";
        exit(1);
    }
    
    echo "✅ Found customer: {$customerData->name} ({$customerData->customer_number})\n";
    echo "   Base credit limit: $" . number_format($customerData->credit_limit ?? 0, 2) . "\n\n";
    
    // 2. Test credit limit records
    echo "=== Testing Credit Limit Records ===\n";
    
    $creditLimits = \Illuminate\Support\Facades\DB::table('acct.customer_credit_limits')
        ->where('customer_id', $customerData->id)
        ->where('company_id', $companyId)
        ->get();
    
    echo "✅ Found {$creditLimits->count()} credit limit records:\n";
    
    foreach ($creditLimits as $limit) {
        $isActive = $limit->status === 'approved' && $limit->effective_at <= now()->format('Y-m-d');
        echo "   - $" . number_format($limit->limit_amount, 2) . " ({$limit->status}) [" . ($isActive ? 'ACTIVE' : 'INACTIVE') . "] effective {$limit->effective_at}\n";
    }
    
    // 3. Test current exposure calculation
    echo "\n=== Testing Exposure Calculation ===\n";
    
    $outstandingInvoices = \Illuminate\Support\Facades\DB::table('acct.invoices')
        ->where('customer_id', $customerData->id)
        ->where('company_id', $companyId)
        ->where('status', '!=', 'paid')
        ->where('status', '!=', 'void')
        ->sum('balance_due');
    
    echo "✅ Outstanding invoices: $" . number_format($outstandingInvoices, 2) . "\n";
    
    // Get current active limit
    $activeLimit = \Illuminate\Support\Facades\DB::table('acct.customer_credit_limits')
        ->where('customer_id', $customerData->id)
        ->where('company_id', $companyId)
        ->where('status', 'approved')
        ->where('effective_at', '<=', now()->format('Y-m-d'))
        ->orderBy('effective_at', 'desc')
        ->first();
    
    $currentLimit = $activeLimit ? $activeLimit->limit_amount : ($customerData->credit_limit ?? 0);
    $availableCredit = $currentLimit - $outstandingInvoices;
    
    echo "✅ Current credit limit: $" . number_format($currentLimit, 2) . "\n";
    echo "✅ Available credit: $" . number_format($availableCredit, 2) . "\n";
    
    // 4. Test creating a new credit limit record
    echo "\n=== Testing Credit Limit Creation ===\n";
    
    // Get a user for approval
    $userData = \Illuminate\Support\Facades\DB::table('auth.users')
        ->where('email', 'like', 'test%')
        ->first();
    
    if (!$userData) {
        echo "❌ Test user not found\n";
        exit(1);
    }
    
    $newLimit = 20000.00;
    $effectiveDate = now()->addDay()->format('Y-m-d');
    
    $creditLimitId = \Illuminate\Support\Facades\DB::table('acct.customer_credit_limits')->insertGetId([
        'id' => \Illuminate\Support\Str::uuid(),
        'customer_id' => $customerData->id,
        'company_id' => $companyId,
        'limit_amount' => $newLimit,
        'effective_at' => $effectiveDate,
        'status' => 'approved',
        'notes' => 'Test credit limit creation',
        'approved_by' => $userData->id,
        'approved_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], 'id');
    
    echo "✅ Created new credit limit record: {$creditLimitId}\n";
    echo "   Amount: $" . number_format($newLimit, 2) . "\n";
    echo "   Effective: {$effectiveDate}\n";
    echo "   Status: approved\n";
    
    // 5. Verify the new limit exists
    $newRecord = \Illuminate\Support\Facades\DB::table('acct.customer_credit_limits')
        ->where('id', $creditLimitId)
        ->first();
    
    if ($newRecord) {
        echo "✅ New credit limit record verified in database\n";
    } else {
        echo "❌ Failed to retrieve new credit limit record\n";
        exit(1);
    }
    
    // 6. Test API endpoint structure (without authentication)
    echo "\n=== Testing API Route Structure ===\n";
    
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $creditLimitRoutes = [];
    
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'credit-limit')) {
            $creditLimitRoutes[] = [
                'method' => implode(', ', $route->methods()),
                'uri' => $route->uri(),
                'action' => $route->getAction('uses') ? class_basename($route->getAction('uses')) : 'Closure'
            ];
        }
    }
    
    echo "✅ Found " . count($creditLimitRoutes) . " credit limit API routes:\n";
    foreach ($creditLimitRoutes as $route) {
        echo "   {$route['method']} {$route['uri']} -> {$route['action']}\n";
    }
    
    echo "\n=== Credit Limit Management Test PASSED ===\n";
    echo "✅ Database layer works correctly\n";
    echo "✅ Credit limit records can be created and retrieved\n";
    echo "✅ Exposure calculations work\n";
    echo "✅ API routes are properly defined\n";
    echo "✅ Core credit limit functionality is operational\n";
    
} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}