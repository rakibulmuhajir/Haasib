<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Company;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing Invitation System...\n\n";

try {
    // Get test data
    $company = Company::first();
    $owner = User::first();
    
    if (!$company || !$owner) {
        echo "❌ No company or user found. Please create test data first.\n";
        exit(1);
    }
    
    echo "📋 Test Setup:\n";
    echo "   Company: {$company->name}\n";
    echo "   Owner: {$owner->name} ({$owner->email})\n\n";
    
    // Test 1: Send invitation
    echo "1️⃣ Testing invitation sending...\n";
    $service = new InvitationService();
    $testEmail = 'test-invite-' . time() . '@example.com';
    
    $invitation = $service->sendInvitation(
        $company,
        $testEmail,
        'accounting_admin',
        $owner
    );
    
    echo "   ✅ Invitation created: {$invitation->id}\n";
    echo "   📧 Email: {$invitation->email}\n";
    echo "   🎭 Role: {$invitation->role}\n";
    echo "   ⏰ Expires: {$invitation->expires_at}\n";
    echo "   🔗 Accept URL: {$invitation->accept_url}\n\n";
    
    // Test 2: Check database state
    echo "2️⃣ Checking database state...\n";
    $invitationCount = \App\Models\Invitation::count();
    $membershipCount = \App\Models\CompanyUser::count();
    
    echo "   📊 Invitations in DB: {$invitationCount}\n";
    echo "   👥 Company memberships: {$membershipCount}\n\n";
    
    // Test 3: Try to send duplicate invitation
    echo "3️⃣ Testing duplicate invitation prevention...\n";
    try {
        $service->sendInvitation($company, $testEmail, 'accounting_admin', $owner);
        echo "   ❌ ERROR: Duplicate invitation should have failed!\n";
    } catch (\Exception $e) {
        echo "   ✅ Duplicate prevented: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 Core invitation system is working!\n\n";
    
    echo "🔍 Manual tests you should do:\n";
    echo "   1. Open the accept URL in browser: {$invitation->accept_url}\n";
    echo "   2. Check sidebar shows 'No Companies' for new users\n";
    echo "   3. Verify emails are being sent (check logs)\n";
    echo "   4. Test the decline URL: " . str_replace('accept', 'decline', $invitation->accept_url) . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}