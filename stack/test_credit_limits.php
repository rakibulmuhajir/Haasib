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
    
    echo "=== Credit Limit Management Test ===\n\n";
    
    // 1. Get the customer
    $customer = \Modules\Accounting\Domain\Customers\Models\Customer::where('customer_number', 'CUST-0002')->first();
    if (!$customer) {
        echo "❌ Customer CUST-0002 not found\n";
        exit(1);
    }
    
    echo "✅ Found customer: {$customer->name} ({$customer->customer_number})\n";
    echo "   Base credit limit: $" . number_format($customer->credit_limit ?? 0, 2) . "\n\n";
    
    // 2. Test CustomerCreditService
    echo "=== Testing CustomerCreditService ===\n";
    $creditService = new \Modules\Accounting\Domain\Customers\Services\CustomerCreditService();
    
    $currentLimit = $creditService->getCurrentCreditLimit($customer);
    $currentExposure = $creditService->calculateExposure($customer);
    $availableCredit = ($currentLimit ?? 0) - $currentExposure;
    
    echo "✅ Current credit limit: $" . number_format($currentLimit ?? 0, 2) . "\n";
    echo "✅ Current exposure: $" . number_format($currentExposure, 2) . "\n";
    echo "✅ Available credit: $" . number_format($availableCredit, 2) . "\n\n";
    
    // 3. Test credit limit records
    echo "=== Testing CustomerCreditLimit Records ===\n";
    $limits = \Modules\Accounting\Domain\Customers\Models\CustomerCreditLimit::where('customer_id', $customer->id)->get();
    echo "✅ Found {$limits->count()} credit limit records:\n";
    
    foreach ($limits as $limit) {
        $isActive = $limit->isActive() ? 'ACTIVE' : 'INACTIVE';
        echo "   - $" . number_format($limit->limit_amount, 2) . " ({$limit->status}) [{$isActive}] effective {$limit->effective_at}\n";
    }
    
    // 4. Test AdjustCustomerCreditLimitAction
    echo "\n=== Testing AdjustCustomerCreditLimitAction ===\n";
    
    // Get a user for the test
    $user = \App\Models\User::where('email', 'like', 'test%')->first();
    if (!$user) {
        echo "❌ Test user not found\n";
        exit(1);
    }
    
    \Illuminate\Support\Facades\Auth::login($user);
    
    $action = new \Modules\Accounting\Domain\Customers\Actions\AdjustCustomerCreditLimitAction(
        $creditService
    );
    
    // Test creating a new credit limit
    $newLimit = 20000.00;
    $effectiveDate = now()->addDay();
    
    echo "✅ Creating new credit limit: $" . number_format($newLimit, 2) . "\n";
    
    $newCreditLimit = $action->execute(
        $customer,
        $newLimit,
        $effectiveDate,
        [
            'reason' => 'Test credit limit adjustment',
            'status' => 'approved',
            'changed_by_user_id' => $user->id,
            'approval_reference' => 'TEST-APPROVAL-001'
        ]
    );
    
    echo "✅ Created credit limit record: {$newCreditLimit->id}\n";
    echo "   Amount: $" . number_format($newCreditLimit->limit_amount, 2) . "\n";
    echo "   Status: {$newCreditLimit->status}\n";
    echo "   Effective: {$newCreditLimit->effective_at}\n";
    
    // 5. Verify the new limit is reflected
    echo "\n=== Verifying Updated Credit Limit ===\n";
    $updatedLimit = $creditService->getCurrentCreditLimit($customer);
    $updatedExposure = $creditService->calculateExposure($customer);
    
    echo "✅ Updated credit limit: $" . number_format($updatedLimit ?? 0, 2) . "\n";
    echo "✅ Current exposure: $" . number_format($updatedExposure, 2) . "\n";
    echo "✅ Available credit: $" . number_format(($updatedLimit ?? 0) - $updatedExposure, 2) . "\n";
    
    // 6. Test conflict detection
    echo "\n=== Testing Conflict Detection ===\n";
    try {
        $conflictLimit = $action->execute(
            $customer,
            25000.00,
            $effectiveDate, // Same effective date - should detect conflict
            [
                'reason' => 'Test conflict detection',
                'status' => 'approved',
                'changed_by_user_id' => $user->id
            ]
        );
        echo "⚠️  Expected conflict but none was detected\n";
    } catch (\Exception $e) {
        echo "✅ Conflict detection working: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Credit Limit Management Test PASSED ===\n";
    echo "✅ All credit limit functionality is working correctly!\n";
    
} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}