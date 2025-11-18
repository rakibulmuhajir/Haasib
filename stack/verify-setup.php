<?php

/**
 * Quick Database Verification Script
 * 
 * This script helps verify the current state of the database
 * for manual customer and invoice creation testing.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Database Verification Report\n";
echo "==============================\n\n";

// Check Khan user exists
echo "👤 User Verification:\n";
$KhanUser = DB::table('auth.users')
    ->where('username', 'Khan')
    ->first();

if ($KhanUser) {
    echo "✅ Khan user found:\n";
    echo "   ID: {$KhanUser->id}\n";
    echo "   Name: {$KhanUser->name}\n";
    echo "   Email: {$KhanUser->email}\n";
    echo "   Username: {$KhanUser->username}\n";
} else {
    echo "❌ Khan user not found\n";
}

// Check companies for Khan user
if ($KhanUser) {
    echo "\n🏢 Companies for Khan:\n";
    $companies = DB::table('auth.company_user')
        ->join('auth.companies', 'auth.company_user.company_id', '=', 'auth.companies.id')
        ->where('auth.company_user.user_id', $KhanUser->id)
        ->select('auth.companies.*')
        ->get();
    
    foreach ($companies as $company) {
        echo "   📋 {$company->name} (ID: {$company->id})\n";
    }
}

// Check existing customers
echo "\n👥 Existing Customers:\n";
$customers = DB::table('acct.customers')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(['id', 'name', 'email', 'status', 'created_at']);

if ($customers->count() > 0) {
    foreach ($customers as $customer) {
        echo "   📇 {$customer->name} (ID: {$customer->id})\n";
        echo "      Email: {$customer->email}\n";
        echo "      Status: {$customer->status}\n";
        echo "      Created: {$customer->created_at}\n\n";
    }
} else {
    echo "   💭 No customers found\n";
}

// Check existing invoices
echo "🧾 Existing Invoices:\n";
$invoices = DB::table('acct.invoices')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(['id', 'invoice_number', 'customer_id', 'status', 'total_amount', 'created_at']);

if ($invoices->count() > 0) {
    foreach ($invoices as $invoice) {
        $customerName = DB::table('acct.customers')
            ->where('id', $invoice->customer_id)
            ->value('name') ?? 'Unknown';
            
        echo "   📄 Invoice #{$invoice->invoice_number}\n";
        echo "      ID: {$invoice->id}\n";
        echo "      Customer: {$customerName}\n";
        echo "      Status: {$invoice->status}\n";
        echo "      Total: $" . number_format($invoice->total_amount, 2) . "\n";
        echo "      Created: {$invoice->created_at}\n\n";
    }
} else {
    echo "   💭 No invoices found\n";
}

// Check currencies available
echo "💰 Available Currencies:\n";
$currencies = DB::table('currencies')
    ->where('is_active', true)
    ->orderBy('code')
    ->limit(10)
    ->get(['code', 'name', 'symbol']);

foreach ($currencies as $currency) {
    echo "   {$currency->code} - {$currency->name} ({$currency->symbol})\n";
}

// Check RLS settings
echo "\n⚙️  RLS Settings Check:\n";
try {
    $rlsResult = DB::select("SHOW app.current_company_id");
    echo "   ✅ RLS context function exists\n";
} catch (Exception $e) {
    echo "   ⚠️  RLS context issue: {$e->getMessage()}\n";
}

echo "\n🚀 Manual Testing Ready!\n";
echo "==============================\n";
echo "1. Open: http://localhost:8000/login\n";
echo "2. Login with: username='Khan', password='yasirkhan'\n";
echo "3. Navigate to customers: http://localhost:8000/customers\n";
echo "4. Create customer: http://localhost:8000/customers/create\n";
echo "5. Create invoice: http://localhost:8000/invoices/create\n";
echo "\n📊 Current Statistics:\n";
echo "   Customers: " . DB::table('acct.customers')->count() . "\n";
echo "   Invoices: " . DB::table('acct.invoices')->count() . "\n";
echo "   Active Companies: " . DB::table('auth.companies')->count() . "\n";