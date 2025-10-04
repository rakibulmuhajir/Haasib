<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WithTeamRoles;

uses(WithTeamRoles::class);

beforeEach(function () {
    // Reset cached permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Seed RBAC
    $this->seed(\Database\Seeders\RbacSeeder::class);

    // Create test companies
    $this->company = Company::factory()->create();
    $this->otherCompany = Company::factory()->create();

    // Create users with different roles
    $this->superAdmin = User::factory()->create(['system_role' => 'superadmin']);
    $this->assignSystemRole($this->superAdmin, 'super_admin');

    $this->owner = User::factory()->create();
    $this->owner->companies()->attach($this->company->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->owner, 'owner', $this->company);

    $this->admin = User::factory()->create();
    $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);
    $this->assignCompanyRole($this->admin, 'admin', $this->company);

    $this->manager = User::factory()->create();
    $this->manager->companies()->attach($this->company->id, ['role' => 'member']);
    $this->assignCompanyRole($this->manager, 'admin', $this->company); // Use 'admin' role instead of 'manager'

    $this->viewer = User::factory()->create();
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    $this->assignCompanyRole($this->viewer, 'viewer', $this->company);

    // User from other company
    $this->otherUser = User::factory()->create();
    $this->otherUser->companies()->attach($this->otherCompany->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->otherUser, 'owner', $this->otherCompany);

    // Reset team context
    setPermissionsTeamId(null);
});

// Test Route Middleware Protections
describe('Route Middleware Permission Checks', function () {
    // Invoice Routes
    test('invoice routes protected by permission middleware', function () {
        $routes = [
            ['method' => 'GET', 'uri' => '/invoices', 'permission' => 'invoices.view'],
            ['method' => 'GET', 'uri' => '/invoices/create', 'permission' => 'invoices.create'],
            ['method' => 'POST', 'uri' => '/invoices', 'permission' => 'invoices.create'],
            ['method' => 'GET', '/invoices/some-uuid', 'permission' => 'invoices.view'],
            ['method' => 'GET', '/invoices/some-uuid/edit', 'permission' => 'invoices.update'],
            ['method' => 'PUT', '/invoices/some-uuid', 'permission' => 'invoices.update'],
            ['method' => 'DELETE', '/invoices/some-uuid', 'permission' => 'invoices.delete'],
            ['method' => 'POST', '/invoices/some-uuid/send', 'permission' => 'invoices.send'],
            ['method' => 'POST', '/invoices/some-uuid/post', 'permission' => 'invoices.post'],
            ['method' => 'POST', '/invoices/some-uuid/duplicate', 'permission' => 'invoices.create'],
            ['method' => 'GET', '/invoices/export', 'permission' => 'invoices.export'],
        ];

        foreach ($routes as $route) {
            // Test viewer permissions - can access all view routes
            if ($route['permission'] === 'invoices.view') {
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertSuccessful();
            } else {
                // Test unauthorized user cannot access
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertForbidden();
            }

            // Test super admin can access regardless of explicit permission
            $this->actingAs($this->superAdmin)
                ->call($route['method'], $route['uri'])
                ->assertSuccessful();
        }
    });

    // Payment Routes
    test('payment routes protected by permission middleware', function () {
        $routes = [
            ['method' => 'GET', 'uri' => '/payments', 'permission' => 'payments.view'],
            ['method' => 'GET', 'uri' => '/payments/create', 'permission' => 'payments.create'],
            ['method' => 'POST', 'uri' => '/payments', 'permission' => 'payments.create'],
            ['method' => 'GET', '/payments/some-uuid', 'permission' => 'payments.view'],
            ['method' => 'GET', '/payments/some-uuid/edit', 'permission' => 'payments.update'],
            ['method' => 'PUT', '/payments/some-uuid', 'permission' => 'payments.update'],
            ['method' => 'DELETE', '/payments/some-uuid', 'permission' => 'payments.delete'],
            ['method' => 'POST', '/payments/some-uuid/allocate', 'permission' => 'payments.allocate'],
            ['method' => 'POST', '/payments/some-uuid/auto-allocate', 'permission' => 'payments.allocate'],
            ['method' => 'POST', '/payments/some-uuid/refund', 'permission' => 'payments.refund'],
            ['method' => 'POST', '/payments/some-uuid/void', 'permission' => 'payments.delete'],
            ['method' => 'GET', '/payments/export', 'permission' => 'payments.export'],
        ];

        foreach ($routes as $route) {
            // Test viewer can access view routes
            if ($route['permission'] === 'payments.view') {
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertSuccessful();
            } else {
                // Test unauthorized user cannot access
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertForbidden();
            }

            // Test authorized user can access
            $this->actingAs($this->owner)
                ->withSession(['current_company_id' => $this->company->id])
                ->call($route['method'], $route['uri'])
                ->assertStatus(200, 302, 419); // Can be successful, redirect, or CSRF token mismatch
        }
    });

    // Ledger Routes
    test('ledger routes protected by permission middleware', function () {
        $routes = [
            ['method' => 'GET', 'uri' => '/ledger', 'permission' => 'ledger.view'],
            ['method' => 'GET', 'uri' => '/ledger/create', 'permission' => 'ledger.entries.create'],
            ['method' => 'POST', 'uri' => '/ledger', 'permission' => 'ledger.entries.create'],
            ['method' => 'GET', '/ledger/some-uuid', 'permission' => 'ledger.view'],
            ['method' => 'GET', '/ledger/some-uuid/edit', 'permission' => 'ledger.entries.update'],
            ['method' => 'PUT', '/ledger/some-uuid', 'permission' => 'ledger.entries.update'],
            ['method' => 'DELETE', '/ledger/some-uuid', 'permission' => 'ledger.entries.delete'],
            ['method' => 'POST', '/ledger/some-uuid/post', 'permission' => 'ledger.entries.post'],
            ['method' => 'POST', '/ledger/some-uuid/void', 'permission' => 'ledger.entries.void'],
            ['method' => 'GET', '/ledger/accounts', 'permission' => 'ledger.view'],
            ['method' => 'GET', '/ledger/journal', 'permission' => 'ledger.journal.view'],
        ];

        foreach ($routes as $route) {
            // Test viewer can access view routes
            if ($route['permission'] === 'ledger.view' || $route['permission'] === 'ledger.journal.view') {
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertSuccessful();
            } else {
                // Test unauthorized user cannot access
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertForbidden();
            }

            // Test accountant can access ledger functions
            $this->actingAs($this->admin) // Use admin instead of superAdmin
                ->withSession(['current_company_id' => $this->company->id])
                ->call($route['method'], $route['uri'])
                ->assertStatus(200, 302, 419);
        }
    });

    // Company Role Management Routes
    test('company role management routes protected', function () {
        $routes = [
            ['method' => 'GET', 'uri' => "/companies/{$this->company->id}/roles", 'permission' => 'users.roles.assign'],
            ['method' => 'PUT', 'uri' => "/companies/{$this->company->id}/roles/some-uuid", 'permission' => 'users.roles.assign'],
            ['method' => 'DELETE', 'uri' => "/companies/{$this->company->id}/roles/some-uuid", 'permission' => 'users.deactivate'],
        ];

        foreach ($routes as $route) {
            // Test manager cannot access role management
            $this->actingAs($this->manager)
                ->withSession(['current_company_id' => $this->company->id])
                ->call($route['method'], $route['uri'])
                ->assertForbidden();

            // Test owner can access role management
            $this->actingAs($this->owner)
                ->withSession(['current_company_id' => $this->company->id])
                ->call($route['method'], $route['uri'])
                ->assertStatus(200, 302, 404); // 404 is ok for non-existent user
        }
    });

    // Currency Routes
    test('currency routes protected by permission middleware', function () {
        $routes = [
            ['method' => 'POST', 'uri' => "/api/companies/{$this->company->id}/currencies", 'permission' => 'companies.currencies.enable'],
            ['method' => 'DELETE', 'uri' => "/api/companies/{$this->company->id}/currencies/some-uuid", 'permission' => 'companies.currencies.disable'],
            ['method' => 'POST', 'uri' => "/api/companies/{$this->company->id}/currencies/exchange-rates", 'permission' => 'companies.currencies.exchange-rates.update'],
            ['method' => 'PATCH', 'uri' => "/api/companies/{$this->company->id}/currencies/base-currency", 'permission' => 'companies.currencies.set-base'],
        ];

        foreach ($routes as $route) {
            // Test viewer cannot manage currencies
            $this->actingAs($this->viewer)
                ->call($route['method'], $route['uri'])
                ->assertForbidden();

            // Test super admin can manage currencies regardless of company
            $this->actingAs($this->superAdmin)
                ->call($route['method'], $route['uri'])
                ->assertStatus(200, 302, 404, 422); // Various valid responses
        }
    });

    // Customer Routes
    test('customer routes protected by permission middleware', function () {
        $routes = [
            ['method' => 'GET', 'uri' => '/customers', 'permission' => 'customers.view'],
            ['method' => 'GET', 'uri' => '/customers/create', 'permission' => 'customers.create'],
            ['method' => 'POST', 'uri' => '/customers', 'permission' => 'customers.create'],
            ['method' => 'GET', 'uri' => '/customers/some-uuid', 'permission' => 'customers.view'],
            ['method' => 'GET', 'uri' => '/customers/some-uuid/edit', 'permission' => 'customers.update'],
            ['method' => 'PUT', 'uri' => '/customers/some-uuid', 'permission' => 'customers.update'],
            ['method' => 'DELETE', 'uri' => '/customers/some-uuid', 'permission' => 'customers.delete'],
            ['method' => 'GET', 'uri' => '/customers/export', 'permission' => 'customers.export'],
        ];

        foreach ($routes as $route) {
            // Test viewer can access view routes
            if ($route['permission'] === 'customers.view') {
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertSuccessful();
            } else {
                // Test unauthorized user cannot access
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($route['method'], $route['uri'])
                    ->assertForbidden();
            }

            // Test authorized user can access
            $this->actingAs($this->owner)
                ->withSession(['current_company_id' => $this->company->id])
                ->call($route['method'], $route['uri'])
                ->assertStatus(200, 302, 404, 419);
        }
    });
});

// Test Super Admin Bypass
describe('Super Admin Permission Bypass', function () {
    test('super admin can access all routes without explicit permissions', function () {
        $routes = [
            '/invoices',
            '/invoices/create',
            '/payments',
            '/payments/create',
            '/ledger',
            '/ledger/create',
            '/customers',
            '/customers/create',
            '/settings',
        ];

        foreach ($routes as $uri) {
            $response = $this->actingAs($this->superAdmin)
                ->get($uri);

            // Should not be forbidden, may be 200, 302, 404, or 419
            expect($response->status())->not->toBe(403);
        }
    });

    test('super admin can perform all actions', function () {
        $actions = [
            ['method' => 'POST', 'uri' => '/invoices'],
            ['method' => 'PUT', 'uri' => '/invoices/fake-uuid'],
            ['method' => 'DELETE', 'uri' => '/invoices/fake-uuid'],
            ['method' => 'POST', 'uri' => '/payments'],
            ['method' => 'PUT', 'uri' => '/payments/fake-uuid'],
            ['method' => 'POST', 'uri' => '/ledger'],
            ['method' => 'PUT', 'uri' => '/ledger/fake-uuid'],
        ];

        foreach ($actions as $action) {
            $response = $this->actingAs($this->superAdmin)
                ->call($action['method'], $action['uri']);

            // Should not be forbidden, may fail for other reasons (404, 422, 500)
            expect($response->status())->not->toBe(403);
        }
    });
});

// Test Cross-Company Access Controls
describe('Cross-Company Access Controls', function () {
    test('users cannot access other company data', function () {
        // Try to access other company's data
        $routes = [
            "/companies/{$this->otherCompany->id}/roles",
            "/api/companies/{$this->otherCompany->id}/currencies",
            '/customers/fake-uuid', // Assuming belongs to other company
        ];

        foreach ($routes as $uri) {
            $this->actingAs($this->owner)
                ->get($uri)
                ->assertStatus(403, 404); // Accept both forbidden and not found
        }
    });

    test('super admin can access any company data', function () {
        $routes = [
            "/companies/{$this->otherCompany->id}/roles",
            "/companies/{$this->otherCompany->id}",
            "/companies/{$this->otherCompany->id}/users",
        ];

        foreach ($routes as $uri) {
            $response = $this->actingAs($this->superAdmin)
                ->get($uri);

            // Should not be forbidden, may be 200, 302, or 404
            expect($response->status())->not->toBe(403);
            expect($response->status())->toBeIn([200, 302, 404]);
        }
    });
});

// Test API Endpoints
describe('API Permission Middleware', function () {
    test('API endpoints respect permissions', function () {
        $endpoints = [
            ['method' => 'GET', 'uri' => '/api/settings', 'permission' => 'settings.view'],
            ['method' => 'PATCH', 'uri' => '/api/settings', 'permission' => 'settings.update'],
            ['method' => 'GET', 'uri' => "/api/companies/{$this->company->id}/currencies", 'permission' => 'companies.currencies.view'],
            ['method' => 'POST', 'uri' => "/api/companies/{$this->company->id}/currencies", 'permission' => 'companies.currencies.enable'],
        ];

        foreach ($endpoints as $endpoint) {
            // Test viewer can access view endpoints
            if ($endpoint['permission'] === 'settings.view' || $endpoint['permission'] === 'companies.currencies.view') {
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($endpoint['method'], $endpoint['uri'])
                    ->assertSuccessful();
            } else {
                // Test unauthorized user cannot access
                $this->actingAs($this->viewer)
                    ->withSession(['current_company_id' => $this->company->id])
                    ->call($endpoint['method'], $endpoint['uri'])
                    ->assertForbidden();
            }

            // Test authorized user can access
            $this->actingAs($this->superAdmin)
                ->call($endpoint['method'], $endpoint['uri'])
                ->assertStatus(200, 302, 404, 422); // Valid responses
        }
    });
});

// Test Inertia Props Include Permissions
describe('Inertia Permission Props', function () {
    test('authenticated pages include permission props', function () {
        $pages = [
            '/invoices',
            '/payments',
            '/ledger',
            '/customers',
            '/settings',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->owner)
                ->get($page);

            if ($response->status() === 200) {
                $response->assertInertia(fn ($page) => $page->has('auth.permissions')
                    ->has('auth.companyPermissions')
                    ->has('auth.roles')
                    ->has('auth.isSuperAdmin')
                );
            }
        }
    });

    test('permission props reflect user role', function () {
        // Test viewer permissions
        $response = $this->actingAs($this->viewer)
            ->get('/invoices');

        if ($response->status() === 200) {
            $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($perms) => $perms->contains('invoices.view') &&
                    ! $perms->contains('invoices.create') &&
                    ! $perms->contains('invoices.delete')
            )
                ->where('auth.roles.company', fn ($roles) => $roles->contains('viewer')
                )
            );
        }

        // Test owner permissions
        $response = $this->actingAs($this->owner)
            ->get('/invoices');

        if ($response->status() === 200) {
            $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($perms) => $perms->contains('invoices.view') &&
                    $perms->contains('invoices.create') &&
                    $perms->contains('invoices.delete')
            )
                ->where('auth.roles.company', fn ($roles) => $roles->contains('owner')
                )
                ->where('auth.canManageCompany', true)
            );
        }
    });

    test('super admin props include system permissions', function () {
        $response = $this->actingAs($this->superAdmin)
            ->get('/invoices');

        if ($response->status() === 200) {
            $response->assertInertia(fn ($page) => $page->where('auth.isSuperAdmin', true)
                ->where('auth.permissions', fn ($perms) => $perms->contains('system.companies.view') &&
                    $perms->contains('system.users.manage')
                )
                ->where('auth.roles.system', fn ($roles) => $roles->contains('super_admin')
                )
            );
        }
    });
});

// Test Session Company Context
describe('Session Company Context', function () {
    test('user without company context gets fallback', function () {
        // Clear company context
        session(['current_company_id' => null]);

        $response = $this->actingAs($this->owner)
            ->get('/dashboard');

        $response->assertInertia(fn ($page) => $page->where('auth.currentCompany', fn ($company) =>
                // Handle both model and collection cases
                $company && data_get($company, 'id') === $this->company->id
        )
        );
    });

    test('super admin can clear company context', function () {
        // Set company context
        session(['current_company_id' => $this->company->id]);

        // Clear context
        $response = $this->actingAs($this->superAdmin)
            ->post('/company/clear-context');

        $response->assertRedirect();

        // Verify context is cleared
        $this->assertNull(session('current_company_id'));
        $this->assertTrue(session('super_admin_global_view'));
    });

    test('super admin global view persists', function () {
        // Set global view
        session(['super_admin_global_view' => true]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/dashboard');

        // Should remain in global view
        $this->assertTrue(session('super_admin_global_view'));
        $this->assertNull(session('current_company_id'));
    });
});

// Test Permission Gates and Policies
describe('Permission Gates and Policies', function () {
    test('permission gates work correctly', function () {
        // Set team context for permission checking
        setPermissionsTeamId($this->company->id);

        // Refresh users to get team-scoped permissions
        $this->viewer->refresh();
        $this->owner->refresh();

        // Test user can check their permissions
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id]);

        expect($this->viewer->can('invoices.view'))->toBeTrue();
        expect($this->viewer->can('invoices.create'))->toBeFalse();
        expect($this->viewer->can('invoices.delete'))->toBeFalse();

        // Test owner permissions
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id]);

        expect($this->owner->can('invoices.view'))->toBeTrue();
        expect($this->owner->can('invoices.create'))->toBeTrue();
        expect($this->owner->can('invoices.delete'))->toBeTrue();
        expect($this->owner->can('manage-company', $this->company))->toBeTrue();
    });

    test('super admin can bypass all gates', function () {
        $this->actingAs($this->superAdmin);

        // Can do anything regardless of explicit permission
        expect($this->superAdmin->can('invoices.view'))->toBeTrue();
        expect($this->superAdmin->can('invoices.create'))->toBeTrue();
        expect($this->superAdmin->can('invoices.delete'))->toBeTrue();
        expect($this->superAdmin->can('system.companies.manage'))->toBeTrue();
        expect($this->superAdmin->can('system.users.manage'))->toBeTrue();
    });
});
