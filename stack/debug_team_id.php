<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test team ID resolution
echo "=== Debugging Team ID Issue ===\n";

// Create a test company
$company = \App\Models\Company::factory()->create();
echo "Created company: {$company->id}\n";

// Create a test user
$user = \App\Models\User::factory()->create();
echo "Created user: {$user->id}\n";

// Set permissions team ID
echo "\n--- Setting team ID to {$company->id} ---\n";
setPermissionsTeamId($company->id);

// Try to assign a role
echo "\n--- Attempting to assign 'owner' role ---\n";
try {
    $role = \Spatie\Permission\Models\Role::where('name', 'owner')->first();
    if (!$role) {
        echo "Role 'owner' not found, creating it...\n";
        $role = \Spatie\Permission\Models\Role::create([
            'name' => 'owner',
            'guard_name' => 'web',
            'team_id' => $company->id,
        ]);
    }
    echo "Role ID: {$role->id}, Team ID: " . ($role->team_id ?? 'null') . "\n";

    $user->assignRole($role);
    echo "Role assigned successfully!\n";

    // Check the model_has_roles table
    $assignment = DB::table('auth.model_has_roles')
        ->where('model_id', $user->id)
        ->first();
    echo "Team ID in model_has_roles: " . ($assignment->team_id ?? 'null') . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";