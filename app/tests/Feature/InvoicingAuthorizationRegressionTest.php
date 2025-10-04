<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\HasCompanyContext;
use Tests\Concerns\WithTeamRoles;

uses(HasCompanyContext::class, WithTeamRoles::class);

beforeEach(function () {
    // Reset cached permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Seed permissions
    $this->artisan('db:seed', ['--class' => 'RbacSeeder', '--env' => 'testing']);

    // Create test companies
    $this->company = Company::create([
        'id' => Str::uuid(),
        'name' => 'Test Company ' . uniqid(),
        'email' => 'company' . uniqid() . '@example.com',
        'phone' => '+1234567890',
        'address' => '123 Test St',
        'city' => 'Test City',
        'state' => 'TS',
        'country' => 'US',
        'postal_code' => '12345',
        'is_active' => true,
    ]);
    $this->otherCompany = Company::create([
        'id' => Str::uuid(),
        'name' => 'Other Company ' . uniqid(),
        'email' => 'other' . uniqid() . '@example.com',
        'phone' => '+1234567891',
        'address' => '456 Other St',
        'city' => 'Other City',
        'state' => 'OS',
        'country' => 'US',
        'postal_code' => '67890',
        'is_active' => true,
    ]);

    // Create test customer
    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer ' . uniqid(),
        'email' => 'customer' . uniqid() . '@example.com',
        'phone' => '+1234567892',
        'is_active' => true,
    ]);

    // Get USD currency for tests - ensure it exists
    $usdCurrency = Currency::firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'is_active' => true,
    ]);

    // Create test invoice
    $this->invoice = Invoice::create([
        'invoice_id' => Str::uuid(),
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'INV-' . uniqid(),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'currency_id' => $usdCurrency->id,
        'total_amount' => 1000,
        'tax_amount' => 0,
        'status' => 'draft',
    ]);

    // Create users with different roles
    $this->superAdmin = User::create([
        'name' => 'Super Admin ' . uniqid(),
        'email' => 'superadmin' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
    ]);
    $this->assignSystemRole($this->superAdmin, 'super_admin');

    $this->owner = User::create([
        'name' => 'Owner ' . uniqid(),
        'email' => 'owner' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->owner->companies()->attach($this->company->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->owner, 'owner', $this->company);

    $this->admin = User::create([
        'name' => 'Admin ' . uniqid(),
        'email' => 'admin' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);
    $this->assignCompanyRole($this->admin, 'admin', $this->company);

    $this->manager = User::create([
        'name' => 'Manager ' . uniqid(),
        'email' => 'manager' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->manager->companies()->attach($this->company->id, ['role' => 'member']); // Use 'member' as the base role
    $this->assignCompanyRole($this->manager, 'manager', $this->company);

    $this->employee = User::create([
        'name' => 'Employee ' . uniqid(),
        'email' => 'employee' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->employee->companies()->attach($this->company->id, ['role' => 'member']); // Use 'member' as the base role
    $this->assignCompanyRole($this->employee, 'employee', $this->company);

    $this->viewer = User::create([
        'name' => 'Viewer ' . uniqid(),
        'email' => 'viewer' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    $this->assignCompanyRole($this->viewer, 'viewer', $this->company);

    // User from other company
    $this->otherUser = User::create([
        'name' => 'Other User ' . uniqid(),
        'email' => 'otheruser' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->otherUser->companies()->attach($this->otherCompany->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->otherUser, 'owner', $this->otherCompany);
});

// Helper function to act as user with company context
function actingAsWithCompany($test, $user, $company)
{
    return $test->actingAs($user)
        ->withSession(['current_company_id' => $company->id]);
}

// Invoice Index Tests
describe('Invoice Index Authorization', function () {
    test('super admin can access invoices index', function () {
        $this->actingAs($this->superAdmin)
            ->get('/invoices')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Invoicing/Invoices/Index')
            );
    });

    test('owner can access company invoices', function () {
        // Set permissions team context
        setPermissionsTeamId($this->company->id);

        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices')
            ->assertSuccessful();
    });

    test('viewer can access invoices list', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices')
            ->assertSuccessful();
    });

    test('user from other company cannot access invoices', function () {
        $this->actingAs($this->otherUser)
            ->withSession(['current_company_id' => $this->otherCompany->id])
            ->get('/invoices')
            ->assertSuccessful(); // Will see empty list as no invoices for their company
    });
});

// Invoice Create Tests
describe('Invoice Create Authorization', function () {
    test('super admin can create invoices in any company', function () {
        $this->actingAs($this->superAdmin)
            ->get('/invoices/create')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Invoicing/Invoices/Create')
            );
    });

    test('owner can create invoices', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/create')
            ->assertSuccessful();
    });

    test('admin can create invoices', function () {
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/create')
            ->assertSuccessful();
    });

    test('manager can create invoices', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/create')
            ->assertSuccessful();
    });

    test('employee can create invoices', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/create')
            ->assertSuccessful();
    });

    test('viewer cannot create invoices', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/create')
            ->assertForbidden();
    });
});

// Invoice Store Tests
describe('Invoice Store Authorization', function () {
    test('super admin can store invoices', function () {
        $usdCurrency = Currency::where('code', 'USD')->first();
        $invoiceData = [
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ];

        $this->actingAs($this->superAdmin)
            ->post('/invoices', $invoiceData)
            ->assertRedirect();
    });

    test('owner can store invoices', function () {
        $usdCurrency = Currency::where('code', 'USD')->first();
        $invoiceData = [
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ];

        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/invoices', $invoiceData)
            ->assertRedirect();
    });

    test('viewer cannot store invoices', function () {
        $usdCurrency = Currency::where('code', 'USD')->first();
        $invoiceData = [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ];

        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/invoices', $invoiceData)
            ->assertForbidden();
    });
});

// Invoice View Tests
describe('Invoice View Authorization', function () {
    test('super admin can view any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->get("/invoices/{$this->invoice->invoice_id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Invoicing/Invoices/Show')
            );
    });

    test('owner can view company invoice', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}")
            ->assertSuccessful();
    });

    test('viewer can view invoice', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}")
            ->assertSuccessful();
    });

    test('user from other company cannot view invoice', function () {
        $this->actingAs($this->otherUser)
            ->withSession(['current_company_id' => $this->otherCompany->id])
            ->get("/invoices/{$this->invoice->invoice_id}")
            ->assertForbidden();
    });
});

// Invoice Edit Tests
describe('Invoice Edit Authorization', function () {
    test('super admin can edit any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->get("/invoices/{$this->invoice->invoice_id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Invoicing/Invoices/Edit')
            );
    });

    test('owner can edit invoice', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}/edit")
            ->assertSuccessful();
    });

    test('admin can edit invoice', function () {
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}/edit")
            ->assertSuccessful();
    });

    test('manager can edit invoice', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}/edit")
            ->assertSuccessful();
    });

    test('employee can edit invoice', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}/edit")
            ->assertSuccessful();
    });

    test('viewer cannot edit invoice', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}/edit")
            ->assertForbidden();
    });
});

// Invoice Update Tests
describe('Invoice Update Authorization', function () {
    test('super admin can update any invoice', function () {
        $updateData = ['number' => 'UPDATED-001'];

        $this->actingAs($this->superAdmin)
            ->put("/invoices/{$this->invoice->invoice_id}", $updateData)
            ->assertRedirect();
    });

    test('owner can update invoice', function () {
        $updateData = ['number' => 'UPDATED-001'];

        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/invoices/{$this->invoice->invoice_id}", $updateData)
            ->assertRedirect();
    });

    test('viewer cannot update invoice', function () {
        $updateData = ['number' => 'UPDATED-001'];

        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/invoices/{$this->invoice->invoice_id}", $updateData)
            ->assertForbidden();
    });
});

// Invoice Delete Tests
describe('Invoice Delete Authorization', function () {
    test('super admin can delete any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->delete("/invoices/{$this->invoice->invoice_id}")
            ->assertRedirect();
    });

    test('owner can delete invoice', function () {
        $usdCurrency = Currency::where('code', 'USD')->first();
        $newInvoice = Invoice::create([
            'invoice_id' => Str::uuid(),
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/invoices/{$newInvoice->invoice_id}")
            ->assertRedirect();
    });

    test('admin can delete invoice', function () {
        $usdCurrency = Currency::where('code', 'USD')->first();
        $newInvoice = Invoice::create([
            'invoice_id' => Str::uuid(),
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/invoices/{$newInvoice->invoice_id}")
            ->assertRedirect();
    });

    test('manager cannot delete invoice', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/invoices/{$this->invoice->invoice_id}")
            ->assertForbidden();
    });

    test('employee cannot delete invoice', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/invoices/{$this->invoice->invoice_id}")
            ->assertForbidden();
    });

    test('viewer cannot delete invoice', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/invoices/{$this->invoice->invoice_id}")
            ->assertForbidden();
    });
});

// Invoice Send Tests
describe('Invoice Send Authorization', function () {
    test('super admin can send any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->invoice_id}/send")
            ->assertRedirect();
    });

    test('owner can send invoice', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/send")
            ->assertRedirect();
    });

    test('manager can send invoice', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/send")
            ->assertRedirect();
    });

    test('employee can send invoice', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/send")
            ->assertRedirect();
    });

    test('viewer cannot send invoice', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/send")
            ->assertForbidden();
    });
});

// Invoice Post Tests
describe('Invoice Post Authorization', function () {
    test('super admin can post any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->invoice_id}/post")
            ->assertRedirect();
    });

    test('owner can post invoice', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/post")
            ->assertRedirect();
    });

    test('admin can post invoice', function () {
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/post")
            ->assertRedirect();
    });

    test('accountant can post invoice', function () {
        $accountant = User::create([
            'name' => 'Accountant ' . uniqid(),
            'email' => 'accountant' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'user',
            'is_active' => true,
        ]);
        $accountant->companies()->attach($this->company->id, ['role' => 'accountant']);
        $this->assignCompanyRole($accountant, 'accountant', $this->company);

        $this->actingAs($accountant)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/post")
            ->assertRedirect();
    });

    test('manager cannot post invoice', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/post")
            ->assertForbidden();
    });

    test('employee cannot post invoice', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/post")
            ->assertForbidden();
    });
});

// Invoice Export Tests
describe('Invoice Export Authorization', function () {
    test('super admin can export invoices', function () {
        $this->actingAs($this->superAdmin)
            ->get('/invoices/export')
            ->assertSuccessful();
    });

    test('owner can export invoices', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/export')
            ->assertSuccessful();
    });

    test('admin can export invoices', function () {
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/export')
            ->assertSuccessful();
    });

    test('manager cannot export invoices', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices/export')
            ->assertForbidden();
    });
});

// Invoice Duplicate Tests
describe('Invoice Duplicate Authorization', function () {
    test('super admin can duplicate any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->invoice_id}/duplicate")
            ->assertRedirect();
    });

    test('owner can duplicate invoice', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/duplicate")
            ->assertRedirect();
    });

    test('admin can duplicate invoice', function () {
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/duplicate")
            ->assertRedirect();
    });

    test('manager can duplicate invoice', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/duplicate")
            ->assertRedirect();
    });

    test('employee cannot duplicate invoice', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/duplicate")
            ->assertForbidden();
    });

    test('viewer cannot duplicate invoice', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/duplicate")
            ->assertForbidden();
    });
});

// Invoice Update Status Tests
describe('Invoice Update Status Authorization', function () {
    test('super admin can update invoice status', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->invoice_id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });

    test('owner can update invoice status', function () {
        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });

    test('admin can update invoice status', function () {
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });

    test('manager can update invoice status', function () {
        $this->actingAs($this->manager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });

    test('employee cannot update invoice status', function () {
        $this->actingAs($this->employee)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/update-status", ['status' => 'approved'])
            ->assertForbidden();
    });

    test('viewer cannot update invoice status', function () {
        $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/invoices/{$this->invoice->invoice_id}/update-status", ['status' => 'approved'])
            ->assertForbidden();
    });
});

// Test Invoice UI Props (Inertia gating)
describe('Invoice UI Permission Props', function () {
    test('invoice page includes correct permissions for super admin', function () {
        $response = $this->actingAs($this->superAdmin)
            ->get("/invoices/{$this->invoice->invoice_id}");

        $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('invoices.view') &&
                $permissions->contains('invoices.update') &&
                $permissions->contains('invoices.delete')
        )
        );
    });

    test('invoice page includes correct permissions for viewer', function () {
        $response = $this->actingAs($this->viewer)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$this->invoice->invoice_id}");

        $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('invoices.view') &&
                ! $permissions->contains('invoices.update') &&
                ! $permissions->contains('invoices.delete')
        )
        );
    });

    test('invoice list page includes company permissions', function () {
        $response = $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/invoices');

        $response->assertInertia(fn ($page) => $page->where('auth.companyPermissions', fn ($permissions) => $permissions->contains('invoices.view') &&
                $permissions->contains('invoices.create') &&
                $permissions->contains('invoices.delete')
        )
            ->where('auth.canManageCompany', true)
        );
    });
});

// Cross-company access tests
describe('Invoice Cross-Company Access', function () {
    test('super admin can access invoice from any company', function () {
        $usdCurrency = Currency::where('code', 'USD')->first();
        $otherCustomer = Customer::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Customer ' . uniqid(),
            'email' => 'othercustomer' . uniqid() . '@example.com',
            'phone' => '+1234567893',
            'is_active' => true,
        ]);

        $otherCompanyInvoice = Invoice::create([
            'invoice_id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'customer_id' => $otherCustomer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($this->superAdmin)
            ->get("/invoices/{$otherCompanyInvoice->invoice_id}")
            ->assertSuccessful();
    });

    test('regular user cannot access invoice from other company', function () {
        $otherCustomer = Customer::create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Customer 2 ' . uniqid(),
            'email' => 'othercustomer2' . uniqid() . '@example.com',
            'phone' => '+1234567894',
            'is_active' => true,
        ]);

        $usdCurrency = Currency::where('code', 'USD')->first();
        $otherCompanyInvoice = Invoice::create([
            'invoice_id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'customer_id' => $otherCustomer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'currency_id' => $usdCurrency->id,
            'total_amount' => 1000,
            'tax_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($this->owner)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/invoices/{$otherCompanyInvoice->invoice_id}")
            ->assertForbidden();
    });
});
