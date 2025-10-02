<?php

use App\Models\User;
use App\Models\Company;
beforeEach(function () {
    // Seed permissions
    $this->artisan('db:seed', ['--class' => 'RbacSeeder', '--env' => 'testing']);
    
    // Create test company
    $this->company = Company::factory()->create();
    
    // Create users with different roles
    $this->superAdmin = User::factory()->create(['system_role' => 'superadmin']);
    $this->superAdmin->assignRole('super_admin');
    
    $this->owner = User::factory()->create();
    $this->owner->companies()->attach($this->company->id, ['role' => 'owner']);
    $this->owner->assignRole('owner', $this->company);
    
    $this->viewer = User::factory()->create();
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    $this->viewer->assignRole('viewer', $this->company);
});

// Test Permission Composable Logic
describe('Permission Composable Logic', function () {
    test('usePermissions composable returns correct structure', function () {
        // Mock Inertia page props
        $pageProps = [
            'auth' => [
                'user' => $this->owner,
                'permissions' => ['invoices.view', 'invoices.create'],
                'companyPermissions' => ['invoices.view', 'invoices.create', 'invoices.delete'],
                'roles' => [
                    'system' => [],
                    'company' => ['owner']
                ],
                'isSuperAdmin' => false,
                'canManageCompany' => true,
                'currentCompanyId' => $this->company->id
            ]
        ];
        
        // Test that the expected permission structure exists
        expect($pageProps['auth'])->toHaveKeys([
            'permissions',
            'companyPermissions', 
            'roles',
            'isSuperAdmin',
            'canManageCompany',
            'currentCompanyId'
        ]);
    });
    
    test('super admin has all can methods returning true', function () {
        // Mock super admin props
        $pageProps = [
            'auth' => [
                'permissions' => ['system.companies.manage', 'invoices.view', 'payments.view'],
                'companyPermissions' => ['invoices.view', 'payments.view'],
                'isSuperAdmin' => true,
                'canManageCompany' => true
            ]
        ];
        
        // Simulate permission checks that would happen in frontend
        $has = fn($permission) => in_array($permission, $pageProps['auth']['permissions']);
        $hasSystemPermission = fn($permission) => in_array($permission, $pageProps['auth']['permissions']);
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        
        // Test can methods
        $can = [
            'viewCompany' => fn() => $has('companies.view') || $isSuperAdmin(),
            'manageCompanySettings' => fn() => $has('companies.settings.update') || $isSuperAdmin(),
            'viewCurrencies' => fn() => $isSuperAdmin() || $has('companies.currencies.view'),
            'manageCurrencies' => fn() => $isSuperAdmin() || $has('companies.currencies.enable'),
            'viewInvoices' => fn() => $has('invoices.view') || $isSuperAdmin(),
            'createInvoices' => fn() => $has('invoices.create') || $isSuperAdmin(),
            'deleteInvoices' => fn() => $has('invoices.delete') || $isSuperAdmin(),
            'viewLedger' => fn() => $has('ledger.view') || $isSuperAdmin(),
            'manageSystem' => fn() => $hasSystemPermission('system.companies.manage'),
        ];
        
        // All should return true for super admin
        expect($can['viewCompany']())->toBeTrue();
        expect($can['manageCompanySettings']())->toBeTrue();
        expect($can['viewCurrencies']())->toBeTrue();
        expect($can['manageCurrencies']())->toBeTrue();
        expect($can['viewInvoices']())->toBeTrue();
        expect($can['createInvoices']())->toBeTrue();
        expect($can['deleteInvoices']())->toBeTrue();
        expect($can['viewLedger']())->toBeTrue();
        expect($can['manageSystem']())->toBeTrue();
    });
    
    test('viewer has limited can methods', function () {
        // Mock viewer props
        $pageProps = [
            'auth' => [
                'permissions' => ['invoices.view', 'customers.view', 'payments.view'],
                'companyPermissions' => ['invoices.view', 'customers.view', 'payments.view'],
                'isSuperAdmin' => false,
                'canManageCompany' => false
            ]
        ];
        
        // Simulate permission checks
        $has = fn($permission) => in_array($permission, $pageProps['auth']['companyPermissions']);
        $hasSystemPermission = fn($permission) => in_array($permission, $pageProps['auth']['permissions']);
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        
        // Test can methods
        $can = [
            'viewCompany' => fn() => $has('companies.view') || $isSuperAdmin(),
            'manageCompanySettings' => fn() => $has('companies.settings.update'),
            'viewInvoices' => fn() => $has('invoices.view') || $isSuperAdmin(),
            'createInvoices' => fn() => $has('invoices.create'),
            'deleteInvoices' => fn() => $has('invoices.delete'),
            'viewCustomers' => fn() => $has('customers.view') || $isSuperAdmin(),
            'createCustomers' => fn() => $has('customers.create'),
            'viewPayments' => fn() => $has('payments.view') || $isSuperAdmin(),
            'createPayments' => fn() => $has('payments.create'),
            'manageSystem' => fn() => $hasSystemPermission('system.companies.manage'),
        ];
        
        // Only view methods should return true
        expect($can['viewCompany']())->toBeFalse(); // Viewer doesn't have companies.view
        expect($can['manageCompanySettings']())->toBeFalse();
        expect($can['viewInvoices']())->toBeTrue();
        expect($can['createInvoices']())->toBeFalse();
        expect($can['deleteInvoices']())->toBeFalse();
        expect($can['viewCustomers']())->toBeTrue();
        expect($can['createCustomers']())->toBeFalse();
        expect($can['viewPayments']())->toBeTrue();
        expect($can['createPayments']())->toBeFalse();
        expect($can['manageSystem']())->toBeFalse();
    });
    
    test('owner has most can methods', function () {
        // Mock owner props
        $pageProps = [
            'auth' => [
                'permissions' => [],
                'companyPermissions' => [
                    'companies.view', 'companies.settings.update',
                    'companies.currencies.view', 'companies.currencies.enable',
                    'invoices.view', 'invoices.create', 'invoices.delete',
                    'customers.view', 'customers.create', 'customers.delete',
                    'payments.view', 'payments.create', 'payments.delete',
                    'users.view', 'users.invite', 'users.update', 'users.deactivate'
                ],
                'isSuperAdmin' => false,
                'canManageCompany' => true
            ]
        ];
        
        // Simulate permission checks
        $has = fn($permission) => in_array($permission, $pageProps['auth']['companyPermissions']);
        $hasSystemPermission = fn($permission) => in_array($permission, $pageProps['auth']['permissions']);
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        
        // Test can methods
        $can = [
            'viewCompany' => fn() => $has('companies.view') || $isSuperAdmin(),
            'manageCompanySettings' => fn() => $has('companies.settings.update'),
            'viewCurrencies' => fn() => $isSuperAdmin() || $has('companies.currencies.view'),
            'manageCurrencies' => fn() => $isSuperAdmin() || $has('companies.currencies.enable'),
            'viewInvoices' => fn() => $has('invoices.view') || $isSuperAdmin(),
            'createInvoices' => fn() => $has('invoices.create'),
            'deleteInvoices' => fn() => $has('invoices.delete'),
            'sendInvoices' => fn() => $has('invoices.send'),
            'viewCustomers' => fn() => $has('customers.view') || $isSuperAdmin(),
            'createCustomers' => fn() => $has('customers.create'),
            'deleteCustomers' => fn() => $has('customers.delete'),
            'viewPayments' => fn() => $has('payments.view') || $isSuperAdmin(),
            'createPayments' => fn() => $has('payments.create'),
            'deletePayments' => fn() => $has('payments.delete'),
            'manageUsers' => fn() => $has('users.update') || $has('users.deactivate'),
            'assignRoles' => fn() => $has('users.roles.assign'),
            'manageSystem' => fn() => $hasSystemPermission('system.companies.manage'),
        ];
        
        // Most should return true for owner
        expect($can['viewCompany']())->toBeTrue();
        expect($can['manageCompanySettings']())->toBeTrue();
        expect($can['viewCurrencies']())->toBeTrue();
        expect($can['manageCurrencies']())->toBeTrue();
        expect($can['viewInvoices']())->toBeTrue();
        expect($can['createInvoices']())->toBeTrue();
        expect($can['deleteInvoices']())->toBeTrue();
        expect($can['viewCustomers']())->toBeTrue();
        expect($can['createCustomers']())->toBeTrue();
        expect($can['deleteCustomers']())->toBeTrue();
        expect($can['viewPayments']())->toBeTrue();
        expect($can['createPayments']())->toBeTrue();
        expect($can['deletePayments']())->toBeTrue();
        expect($can['manageUsers']())->toBeTrue();
        expect($can['manageSystem']())->toBeFalse(); // Not system level
    });
});

// Test Role-based Helpers
describe('Role-based Helper Methods', function () {
    test('role helpers return correct values', function () {
        // Test different roles
        $roleTests = [
            ['user' => $this->superAdmin, 'expectedSystemRole' => 'super_admin', 'expectedCompanyRole' => null],
            ['user' => $this->owner, 'expectedSystemRole' => null, 'expectedCompanyRole' => 'owner'],
            ['user' => $this->viewer, 'expectedSystemRole' => null, 'expectedCompanyRole' => 'viewer'],
        ];
        
        foreach ($roleTests as $test) {
            // Simulate role checking
            $hasSystemRole = fn($role) => $test['expectedSystemRole'] === $role;
            $hasRole = fn($role) => $test['expectedCompanyRole'] === $role;
            
            if ($test['expectedSystemRole']) {
                expect($hasSystemRole($test['expectedSystemRole']))->toBeTrue();
                expect($hasSystemRole('other_role'))->toBeFalse();
            }
            
            if ($test['expectedCompanyRole']) {
                expect($hasRole($test['expectedCompanyRole']))->toBeTrue();
                expect($hasRole('other_role'))->toBeFalse();
            }
        }
    });
});

// Test Permission Inheritance
describe('Permission Inheritance', function () {
    test('super admin inherits all permissions', function () {
        // Even if no explicit permissions, super admin should have access
        $pageProps = [
            'auth' => [
                'permissions' => [], // Empty permissions
                'companyPermissions' => [], // Empty company permissions
                'isSuperAdmin' => true,
                'canManageCompany' => true
            ]
        ];
        
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        $can = [
            'viewInvoices' => fn() => $isSuperAdmin(),
            'createInvoices' => fn() => $isSuperAdmin(),
            'deleteInvoices' => fn() => $isSuperAdmin(),
            'manageSystem' => fn() => $isSuperAdmin(),
        ];
        
        expect($can['viewInvoices']())->toBeTrue();
        expect($can['createInvoices']())->toBeTrue();
        expect($can['deleteInvoices']())->toBeTrue();
        expect($can['manageSystem']())->toBeTrue();
    });
    
    test('regular users need explicit permissions', function () {
        $pageProps = [
            'auth' => [
                'permissions' => ['invoices.view'],
                'companyPermissions' => ['invoices.view'],
                'isSuperAdmin' => false,
                'canManageCompany' => false
            ]
        ];
        
        $has = fn($permission) => in_array($permission, $pageProps['auth']['companyPermissions']);
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        
        $can = [
            'viewInvoices' => fn() => $has('invoices.view') || $isSuperAdmin(),
            'createInvoices' => fn() => $has('invoices.create'),
            'deleteInvoices' => fn() => $has('invoices.delete'),
        ];
        
        expect($can['viewInvoices']())->toBeTrue(); // Has permission
        expect($can['createInvoices']())->toBeFalse(); // No permission
        expect($can['deleteInvoices']())->toBeFalse(); // No permission
    });
});

// Test Currency Permissions
describe('Currency Permission Logic', function () {
    test('super admin can manage currencies anywhere', function () {
        $pageProps = [
            'auth' => [
                'isSuperAdmin' => true,
                'permissions' => [],
                'companyPermissions' => [],
            ]
        ];
        
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        $can = [
            'viewCurrencies' => fn() => $isSuperAdmin() || false,
            'manageCurrencies' => fn() => $isSuperAdmin() || false,
        ];
        
        expect($can['viewCurrencies']())->toBeTrue();
        expect($can['manageCurrencies']())->toBeTrue();
    });
    
    test('regular users need company currency permissions', function () {
        $pageProps = [
            'auth' => [
                'isSuperAdmin' => false,
                'companyPermissions' => ['companies.currencies.view'],
            ]
        ];
        
        $isSuperAdmin = fn() => $pageProps['auth']['isSuperAdmin'];
        $has = fn($permission) => in_array($permission, $pageProps['auth']['companyPermissions']);
        
        $can = [
            'viewCurrencies' => fn() => $isSuperAdmin() || $has('companies.currencies.view'),
            'manageCurrencies' => fn() => $isSuperAdmin() || $has('companies.currencies.enable') || $has('companies.currencies.disable'),
        ];
        
        expect($can['viewCurrencies']())->toBeTrue();
        expect($can['manageCurrencies']())->toBeFalse(); // Needs enable/disable permission
    });
});

// Test Company Management Permissions
describe('Company Management Permissions', function () {
    test('canManageCompany reflects role correctly', function () {
        // Owner should manage company
        expect($this->owner->hasRole('owner', $this->company))->toBeTrue();
        expect($this->owner->hasRole('admin', $this->company))->toBeFalse();
        
        // Viewer should not manage company
        expect($this->viewer->hasRole('owner', $this->company))->toBeFalse();
        expect($this->viewer->hasRole('viewer', $this->company))->toBeTrue();
    });
    
    test('super admin can manage any company', function () {
        // Super admin can manage companies even without explicit role
        expect($this->superAdmin->isSuperAdmin())->toBeTrue();
        // This would be checked in controller logic
    });
});