<?php

test('debug viewer permissions from seeder', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(\Database\Seeders\RbacSeeder::class);

    $company = \App\Models\Company::factory()->create();
    $viewer = \App\Models\User::factory()->create();
    $viewer->companies()->attach($company->id, ['role' => 'viewer']);

    setPermissionsTeamId($company->id);
    $viewer->assignRole('viewer');
    $viewer->refresh();

    // Check specific permissions
    $permissions = [
        'invoices.view' => $viewer->hasPermissionTo('invoices.view'),
        'payments.view' => $viewer->hasPermissionTo('payments.view'),
        'customers.view' => $viewer->hasPermissionTo('customers.view'),
        'ledger.view' => $viewer->hasPermissionTo('ledger.view'),
        'companies.currencies.enable' => $viewer->hasPermissionTo('companies.currencies.enable'),
    ];

    dump('Viewer permissions:');
    foreach ($permissions as $perm => $has) {
        dump("  $perm: ".($has ? 'YES' : 'NO'));
    }

    dump('Total permissions count:', $viewer->permissions->count());
    dump('All permissions:', $viewer->permissions->pluck('name')->toArray());
});
