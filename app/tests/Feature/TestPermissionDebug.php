<?php

use App\Models\User;
use App\Models\Company;

test('debug permissions', function () {
    // Clean slate
    \DB::table('model_has_roles')->where('model_type', User::class)->delete();
    
    // Seed permissions
    $this->artisan('db:seed', ['--class' => 'RbacSeeder', '--env' => 'testing']);
    
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($company->id, ['role' => 'viewer']);
    
    // Assign role
    setPermissionsTeamId($company->id);
    $user->assignRole('viewer');
    setPermissionsTeamId(null);
    
    // Check database
    $roleAssignment = \DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->where('model_type', User::class)
        ->first();
    
    dump([
        'role_id' => $roleAssignment->role_id,
        'team_id' => $roleAssignment->team_id,
        'company_id' => $company->id,
    ]);
    
    // Check role details
    $role = \DB::table('roles')->where('id', $roleAssignment->role_id)->first();
    dump([
        'role_name' => $role->name,
        'role_team_id' => $role->team_id,
    ]);
    
    // Check permission exists
    $permission = \DB::table('permissions')->where('name', 'invoices.view')->first();
    dump(['permission_id' => $permission->id]);
    
    // Check role has permission
    $rolePermission = \DB::table('role_has_permissions')
        ->where('role_id', $roleAssignment->role_id)
        ->where('permission_id', $permission->id)
        ->first();
    
    dump(['role_has_permission' => $rolePermission ? 'YES' : 'NO']);
    
    // Test permission check
    setPermissionsTeamId($company->id);
    $hasPermission = $user->hasPermissionTo('invoices.view');
    setPermissionsTeamId(null);
    
    dump(['has_permission_with_team' => $hasPermission ? 'YES' : 'NO']);
    
    // Test without team
    $hasPermission = $user->hasPermissionTo('invoices.view');
    dump(['has_permission_no_team' => $hasPermission ? 'YES' : 'NO']);
    
    expect($hasPermission)->toBeTrue();
});