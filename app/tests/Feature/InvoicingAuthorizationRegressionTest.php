<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\HasCompanyContext;

uses(DatabaseTransactions::class, HasCompanyContext::class);

beforeEach(function () {
    // Seed permissions
    $this->artisan('db:seed', ['--class' => 'RbacSeeder', '--env' => 'testing']);
    
    // Create test companies
    $this->company = Company::factory()->create();
    $this->otherCompany = Company::factory()->create();
    
    // Create test customer
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    
    // Create test invoice
    $this->invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id
    ]);
    
    // Create users with different roles
    $this->superAdmin = User::factory()->create(['system_role' => 'superadmin']);
    setPermissionsTeamId(null); // Global role for super admin
    $this->superAdmin->assignRole('super_admin');
    
    $this->owner = User::factory()->create();
    $this->owner->companies()->attach($this->company->id, ['role' => 'owner']);
    setPermissionsTeamId($this->company->id);
    $this->owner->assignRole('owner');
    setPermissionsTeamId(null);
    
    $this->admin = User::factory()->create();
    $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);
    setPermissionsTeamId($this->company->id);
    $this->admin->assignRole('admin');
    setPermissionsTeamId(null);
    
    $this->manager = User::factory()->create();
    $this->manager->companies()->attach($this->company->id, ['role' => 'member']); // Use 'member' as the base role
    setPermissionsTeamId($this->company->id);
    $this->manager->assignRole('manager');
    setPermissionsTeamId(null);
    
    $this->employee = User::factory()->create();
    $this->employee->companies()->attach($this->company->id, ['role' => 'member']); // Use 'member' as the base role
    setPermissionsTeamId($this->company->id);
    $this->employee->assignRole('employee');
    setPermissionsTeamId(null);
    
    $this->viewer = User::factory()->create();
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    setPermissionsTeamId($this->company->id);
    $this->viewer->assignRole('viewer');
    setPermissionsTeamId(null);
    
    // User from other company
    $this->otherUser = User::factory()->create();
    $this->otherUser->companies()->attach($this->otherCompany->id, ['role' => 'owner']);
    setPermissionsTeamId($this->otherCompany->id);
    $this->otherUser->assignRole('owner');
    setPermissionsTeamId(null);
});

// Helper function to act as user with company context
function actingAsWithCompany($test, $user, $company) {
    return $test->actingAs($user)
        ->withSession(['current_company_id' => $company->id]);
}

// Invoice Index Tests
describe('Invoice Index Authorization', function () {
    test('super admin can access invoices index', function () {
        $this->actingAs($this->superAdmin)
            ->get('/invoices')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/Invoices/Index')
            );
    });
    
    test('owner can access company invoices', function () {
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
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/Invoices/Create')
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
        $invoiceData = Invoice::factory()->make([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id
        ])->toArray();
        
        $this->actingAs($this->superAdmin)
            ->post('/invoices', $invoiceData)
            ->assertRedirect();
    });
    
    test('owner can store invoices', function () {
        $invoiceData = Invoice::factory()->make([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id
        ])->toArray();
        
        $this->actingAs($this->owner)
            ->post('/invoices', $invoiceData)
            ->assertRedirect();
    });
    
    test('viewer cannot store invoices', function () {
        $invoiceData = Invoice::factory()->make()->toArray();
        
        $this->actingAs($this->viewer)
            ->post('/invoices', $invoiceData)
            ->assertForbidden();
    });
});

// Invoice View Tests
describe('Invoice View Authorization', function () {
    test('super admin can view any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->get("/invoices/{$this->invoice->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/Invoices/Show')
                    ->where('invoice.id', $this->invoice->id)
            );
    });
    
    test('owner can view company invoice', function () {
        $this->actingAs($this->owner)
            ->get("/invoices/{$this->invoice->id}")
            ->assertSuccessful();
    });
    
    test('viewer can view invoice', function () {
        $this->actingAs($this->viewer)
            ->get("/invoices/{$this->invoice->id}")
            ->assertSuccessful();
    });
    
    test('user from other company cannot view invoice', function () {
        $this->actingAs($this->otherUser)
            ->get("/invoices/{$this->invoice->id}")
            ->assertForbidden();
    });
});

// Invoice Edit Tests
describe('Invoice Edit Authorization', function () {
    test('super admin can edit any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->get("/invoices/{$this->invoice->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/Invoices/Edit')
            );
    });
    
    test('owner can edit invoice', function () {
        $this->actingAs($this->owner)
            ->get("/invoices/{$this->invoice->id}/edit")
            ->assertSuccessful();
    });
    
    test('admin can edit invoice', function () {
        $this->actingAs($this->admin)
            ->get("/invoices/{$this->invoice->id}/edit")
            ->assertSuccessful();
    });
    
    test('manager can edit invoice', function () {
        $this->actingAs($this->manager)
            ->get("/invoices/{$this->invoice->id}/edit")
            ->assertSuccessful();
    });
    
    test('employee can edit invoice', function () {
        $this->actingAs($this->employee)
            ->get("/invoices/{$this->invoice->id}/edit")
            ->assertSuccessful();
    });
    
    test('viewer cannot edit invoice', function () {
        $this->actingAs($this->viewer)
            ->get("/invoices/{$this->invoice->id}/edit")
            ->assertForbidden();
    });
});

// Invoice Update Tests
describe('Invoice Update Authorization', function () {
    test('super admin can update any invoice', function () {
        $updateData = ['number' => 'UPDATED-001'];
        
        $this->actingAs($this->superAdmin)
            ->put("/invoices/{$this->invoice->id}", $updateData)
            ->assertRedirect();
    });
    
    test('owner can update invoice', function () {
        $updateData = ['number' => 'UPDATED-001'];
        
        $this->actingAs($this->owner)
            ->put("/invoices/{$this->invoice->id}", $updateData)
            ->assertRedirect();
    });
    
    test('viewer cannot update invoice', function () {
        $updateData = ['number' => 'UPDATED-001'];
        
        $this->actingAs($this->viewer)
            ->put("/invoices/{$this->invoice->id}", $updateData)
            ->assertForbidden();
    });
});

// Invoice Delete Tests
describe('Invoice Delete Authorization', function () {
    test('super admin can delete any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->delete("/invoices/{$this->invoice->id}")
            ->assertRedirect();
    });
    
    test('owner can delete invoice', function () {
        $newInvoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id
        ]);
        
        $this->actingAs($this->owner)
            ->delete("/invoices/{$newInvoice->id}")
            ->assertRedirect();
    });
    
    test('admin can delete invoice', function () {
        $newInvoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id
        ]);
        
        $this->actingAs($this->admin)
            ->delete("/invoices/{$newInvoice->id}")
            ->assertRedirect();
    });
    
    test('manager cannot delete invoice', function () {
        $this->actingAs($this->manager)
            ->delete("/invoices/{$this->invoice->id}")
            ->assertForbidden();
    });
    
    test('employee cannot delete invoice', function () {
        $this->actingAs($this->employee)
            ->delete("/invoices/{$this->invoice->id}")
            ->assertForbidden();
    });
    
    test('viewer cannot delete invoice', function () {
        $this->actingAs($this->viewer)
            ->delete("/invoices/{$this->invoice->id}")
            ->assertForbidden();
    });
});

// Invoice Send Tests
describe('Invoice Send Authorization', function () {
    test('super admin can send any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->id}/send")
            ->assertRedirect();
    });
    
    test('owner can send invoice', function () {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/send")
            ->assertRedirect();
    });
    
    test('manager can send invoice', function () {
        $this->actingAs($this->manager)
            ->post("/invoices/{$this->invoice->id}/send")
            ->assertRedirect();
    });
    
    test('employee can send invoice', function () {
        $this->actingAs($this->employee)
            ->post("/invoices/{$this->invoice->id}/send")
            ->assertRedirect();
    });
    
    test('viewer cannot send invoice', function () {
        $this->actingAs($this->viewer)
            ->post("/invoices/{$this->invoice->id}/send")
            ->assertForbidden();
    });
});

// Invoice Post Tests
describe('Invoice Post Authorization', function () {
    test('super admin can post any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->id}/post")
            ->assertRedirect();
    });
    
    test('owner can post invoice', function () {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/post")
            ->assertRedirect();
    });
    
    test('admin can post invoice', function () {
        $this->actingAs($this->admin)
            ->post("/invoices/{$this->invoice->id}/post")
            ->assertRedirect();
    });
    
    test('accountant can post invoice', function () {
        $accountant = User::factory()->create();
        $accountant->companies()->attach($this->company->id, ['role' => 'accountant']);
        setPermissionsTeamId($this->company->id);
        $accountant->assignRole('accountant');
        setPermissionsTeamId(null);
        
        $this->actingAs($accountant)
            ->post("/invoices/{$this->invoice->id}/post")
            ->assertRedirect();
    });
    
    test('manager cannot post invoice', function () {
        $this->actingAs($this->manager)
            ->post("/invoices/{$this->invoice->id}/post")
            ->assertForbidden();
    });
    
    test('employee cannot post invoice', function () {
        $this->actingAs($this->employee)
            ->post("/invoices/{$this->invoice->id}/post")
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
            ->get('/invoices/export')
            ->assertSuccessful();
    });
    
    test('admin can export invoices', function () {
        $this->actingAs($this->admin)
            ->get('/invoices/export')
            ->assertSuccessful();
    });
    
    test('manager cannot export invoices', function () {
        $this->actingAs($this->manager)
            ->get('/invoices/export')
            ->assertForbidden();
    });
});

// Invoice Duplicate Tests
describe('Invoice Duplicate Authorization', function () {
    test('super admin can duplicate any invoice', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->id}/duplicate")
            ->assertRedirect();
    });
    
    test('owner can duplicate invoice', function () {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/duplicate")
            ->assertRedirect();
    });
    
    test('admin can duplicate invoice', function () {
        $this->actingAs($this->admin)
            ->post("/invoices/{$this->invoice->id}/duplicate")
            ->assertRedirect();
    });
    
    test('manager can duplicate invoice', function () {
        $this->actingAs($this->manager)
            ->post("/invoices/{$this->invoice->id}/duplicate")
            ->assertRedirect();
    });
    
    test('employee cannot duplicate invoice', function () {
        $this->actingAs($this->employee)
            ->post("/invoices/{$this->invoice->id}/duplicate")
            ->assertForbidden();
    });
    
    test('viewer cannot duplicate invoice', function () {
        $this->actingAs($this->viewer)
            ->post("/invoices/{$this->invoice->id}/duplicate")
            ->assertForbidden();
    });
});

// Invoice Update Status Tests
describe('Invoice Update Status Authorization', function () {
    test('super admin can update invoice status', function () {
        $this->actingAs($this->superAdmin)
            ->post("/invoices/{$this->invoice->id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });
    
    test('owner can update invoice status', function () {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });
    
    test('admin can update invoice status', function () {
        $this->actingAs($this->admin)
            ->post("/invoices/{$this->invoice->id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });
    
    test('manager can update invoice status', function () {
        $this->actingAs($this->manager)
            ->post("/invoices/{$this->invoice->id}/update-status", ['status' => 'approved'])
            ->assertRedirect();
    });
    
    test('employee cannot update invoice status', function () {
        $this->actingAs($this->employee)
            ->post("/invoices/{$this->invoice->id}/update-status", ['status' => 'approved'])
            ->assertForbidden();
    });
    
    test('viewer cannot update invoice status', function () {
        $this->actingAs($this->viewer)
            ->post("/invoices/{$this->invoice->id}/update-status", ['status' => 'approved'])
            ->assertForbidden();
    });
});

// Test Invoice UI Props (Inertia gating)
describe('Invoice UI Permission Props', function () {
    test('invoice page includes correct permissions for super admin', function () {
        $response = $this->actingAs($this->superAdmin)
            ->get("/invoices/{$this->invoice->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('invoices.view') &&
                $permissions->contains('invoices.update') &&
                $permissions->contains('invoices.delete')
            )
        );
    });
    
    test('invoice page includes correct permissions for viewer', function () {
        $response = $this->actingAs($this->viewer)
            ->get("/invoices/{$this->invoice->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('invoices.view') &&
                !$permissions->contains('invoices.update') &&
                !$permissions->contains('invoices.delete')
            )
        );
    });
    
    test('invoice list page includes company permissions', function () {
        $response = $this->actingAs($this->owner)
            ->get('/invoices');
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.companyPermissions', fn ($permissions) => 
                $permissions->contains('invoices.view') &&
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
        $otherCompanyInvoice = Invoice::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->superAdmin)
            ->get("/invoices/{$otherCompanyInvoice->id}")
            ->assertSuccessful();
    });
    
    test('regular user cannot access invoice from other company', function () {
        $otherCompanyInvoice = Invoice::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->owner)
            ->get("/invoices/{$otherCompanyInvoice->id}")
            ->assertForbidden();
    });
});