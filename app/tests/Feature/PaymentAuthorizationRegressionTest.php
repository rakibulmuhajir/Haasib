<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WithTeamRoles;

uses(WithTeamRoles::class);

beforeEach(function () {
    // Create test companies
    $this->company = Company::factory()->create();
    $this->otherCompany = Company::factory()->create();
    
    // Create test customer and invoice
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total' => 1000
    ]);
    
    // Create test payment
    $this->payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 500
    ]);
    
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
    $this->manager->companies()->attach($this->company->id, ['role' => 'manager']);
    $this->assignCompanyRole($this->manager, 'manager', $this->company);

    $this->accountant = User::factory()->create();
    $this->accountant->companies()->attach($this->company->id, ['role' => 'accountant']);
    $this->assignCompanyRole($this->accountant, 'accountant', $this->company);

    $this->employee = User::factory()->create();
    $this->employee->companies()->attach($this->company->id, ['role' => 'employee']);
    $this->assignCompanyRole($this->employee, 'employee', $this->company);

    $this->viewer = User::factory()->create();
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    $this->assignCompanyRole($this->viewer, 'viewer', $this->company);

    // User from other company
    $this->otherUser = User::factory()->create();
    $this->otherUser->companies()->attach($this->otherCompany->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->otherUser, 'owner', $this->otherCompany);
});

// Payment Index Tests
describe('Payment Index Authorization', function () {
    test('super admin can access payments index', function () {
        $this->actingAs($this->superAdmin)
            ->get('/payments')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/PaymentIndex')
            );
    });
    
    test('owner can access company payments', function () {
        $this->actingAs($this->owner)
            ->get('/payments')
            ->assertSuccessful();
    });
    
    test('viewer can access payments list', function () {
        $this->actingAs($this->viewer)
            ->get('/payments')
            ->assertSuccessful();
    });
    
    test('user from other company sees only their payments', function () {
        $this->actingAs($this->otherUser)
            ->get('/payments')
            ->assertSuccessful(); // Will see empty or different list
    });
});

// Payment Create Tests
describe('Payment Create Authorization', function () {
    test('super admin can create payments in any company', function () {
        $this->actingAs($this->superAdmin)
            ->get('/payments/create')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/PaymentCreate')
            );
    });
    
    test('owner can create payments', function () {
        $this->actingAs($this->owner)
            ->get('/payments/create')
            ->assertSuccessful();
    });
    
    test('admin can create payments', function () {
        $this->actingAs($this->admin)
            ->get('/payments/create')
            ->assertSuccessful();
    });
    
    test('manager can create payments', function () {
        $this->actingAs($this->manager)
            ->get('/payments/create')
            ->assertSuccessful();
    });
    
    test('accountant can create payments', function () {
        $this->actingAs($this->accountant)
            ->get('/payments/create')
            ->assertSuccessful();
    });
    
    test('employee can create payments', function () {
        $this->actingAs($this->employee)
            ->get('/payments/create')
            ->assertSuccessful();
    });
    
    test('viewer cannot create payments', function () {
        $this->actingAs($this->viewer)
            ->get('/payments/create')
            ->assertForbidden();
    });
});

// Payment Store Tests
describe('Payment Store Authorization', function () {
    test('super admin can store payments', function () {
        $paymentData = Payment::factory()->make([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id
        ])->toArray();
        
        $this->actingAs($this->superAdmin)
            ->post('/payments', $paymentData)
            ->assertRedirect();
    });
    
    test('accountant can store payments', function () {
        $paymentData = Payment::factory()->make([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id
        ])->toArray();
        
        $this->actingAs($this->accountant)
            ->post('/payments', $paymentData)
            ->assertRedirect();
    });
    
    test('viewer cannot store payments', function () {
        $paymentData = Payment::factory()->make()->toArray();
        
        $this->actingAs($this->viewer)
            ->post('/payments', $paymentData)
            ->assertForbidden();
    });
});

// Payment View Tests
describe('Payment View Authorization', function () {
    test('super admin can view any payment', function () {
        $this->actingAs($this->superAdmin)
            ->get("/payments/{$this->payment->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/PaymentShow')
                    ->where('payment.id', $this->payment->id)
            );
    });
    
    test('owner can view company payment', function () {
        $this->actingAs($this->owner)
            ->get("/payments/{$this->payment->id}")
            ->assertSuccessful();
    });
    
    test('viewer can view payment', function () {
        $this->actingAs($this->viewer)
            ->get("/payments/{$this->payment->id}")
            ->assertSuccessful();
    });
    
    test('user from other company cannot view payment', function () {
        $this->actingAs($this->otherUser)
            ->get("/payments/{$this->payment->id}")
            ->assertForbidden();
    });
});

// Payment Edit Tests
describe('Payment Edit Authorization', function () {
    test('super admin can edit any payment', function () {
        $this->actingAs($this->superAdmin)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Invoicing/PaymentEdit')
            );
    });
    
    test('owner can edit payment', function () {
        $this->actingAs($this->owner)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertSuccessful();
    });
    
    test('admin can edit payment', function () {
        $this->actingAs($this->admin)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertSuccessful();
    });
    
    test('accountant can edit payment', function () {
        $this->actingAs($this->accountant)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertSuccessful();
    });
    
    test('manager cannot edit payment', function () {
        $this->actingAs($this->manager)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertForbidden();
    });
    
    test('employee cannot edit payment', function () {
        $this->actingAs($this->employee)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertForbidden();
    });
    
    test('viewer cannot edit payment', function () {
        $this->actingAs($this->viewer)
            ->get("/payments/{$this->payment->id}/edit")
            ->assertForbidden();
    });
});

// Payment Update Tests
describe('Payment Update Authorization', function () {
    test('super admin can update any payment', function () {
        $updateData = ['amount' => 750];
        
        $this->actingAs($this->superAdmin)
            ->put("/payments/{$this->payment->id}", $updateData)
            ->assertRedirect();
    });
    
    test('accountant can update payment', function () {
        $updateData = ['amount' => 750];
        
        $this->actingAs($this->accountant)
            ->put("/payments/{$this->payment->id}", $updateData)
            ->assertRedirect();
    });
    
    test('viewer cannot update payment', function () {
        $updateData = ['amount' => 750];
        
        $this->actingAs($this->viewer)
            ->put("/payments/{$this->payment->id}", $updateData)
            ->assertForbidden();
    });
});

// Payment Delete Tests
describe('Payment Delete Authorization', function () {
    test('super admin can delete any payment', function () {
        $newPayment = Payment::factory()->create([
            'company_id' => $this->company->id
        ]);
        
        $this->actingAs($this->superAdmin)
            ->delete("/payments/{$newPayment->id}")
            ->assertRedirect();
    });
    
    test('owner can delete payment', function () {
        $newPayment = Payment::factory()->create([
            'company_id' => $this->company->id
        ]);
        
        $this->actingAs($this->owner)
            ->delete("/payments/{$newPayment->id}")
            ->assertRedirect();
    });
    
    test('admin can delete payment', function () {
        $newPayment = Payment::factory()->create([
            'company_id' => $this->company->id
        ]);
        
        $this->actingAs($this->admin)
            ->delete("/payments/{$newPayment->id}")
            ->assertRedirect();
    });
    
    test('manager cannot delete payment', function () {
        $this->actingAs($this->manager)
            ->delete("/payments/{$this->payment->id}")
            ->assertForbidden();
    });
    
    test('viewer cannot delete payment', function () {
        $this->actingAs($this->viewer)
            ->delete("/payments/{$this->payment->id}")
            ->assertForbidden();
    });
});

// Payment Allocate Tests
describe('Payment Allocate Authorization', function () {
    test('super admin can allocate payments', function () {
        $this->actingAs($this->superAdmin)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertRedirect();
    });
    
    test('owner can allocate payments', function () {
        $this->actingAs($this->owner)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertRedirect();
    });
    
    test('admin can allocate payments', function () {
        $this->actingAs($this->admin)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertRedirect();
    });
    
    test('manager can allocate payments', function () {
        $this->actingAs($this->manager)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertRedirect();
    });
    
    test('accountant can allocate payments', function () {
        $this->actingAs($this->accountant)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertRedirect();
    });
    
    test('employee cannot allocate payments', function () {
        $this->actingAs($this->employee)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertForbidden();
    });
    
    test('viewer cannot allocate payments', function () {
        $this->actingAs($this->viewer)
            ->post("/payments/{$this->payment->id}/allocate", [
                'invoice_id' => $this->invoice->id,
                'amount' => 250
            ])
            ->assertForbidden();
    });
});

// Payment Auto-Allocate Tests
describe('Payment Auto-Allocate Authorization', function () {
    test('super admin can auto-allocate payments', function () {
        $this->actingAs($this->superAdmin)
            ->post("/payments/{$this->payment->id}/auto-allocate")
            ->assertRedirect();
    });
    
    test('owner can auto-allocate payments', function () {
        $this->actingAs($this->owner)
            ->post("/payments/{$this->payment->id}/auto-allocate")
            ->assertRedirect();
    });
    
    test('accountant can auto-allocate payments', function () {
        $this->actingAs($this->accountant)
            ->post("/payments/{$this->payment->id}/auto-allocate")
            ->assertRedirect();
    });
    
    test('manager cannot auto-allocate payments', function () {
        $this->actingAs($this->manager)
            ->post("/payments/{$this->payment->id}/auto-allocate")
            ->assertForbidden();
    });
});

// Payment Refund Tests
describe('Payment Refund Authorization', function () {
    test('super admin can refund payments', function () {
        $this->actingAs($this->superAdmin)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertRedirect();
    });
    
    test('owner can refund payments', function () {
        $this->actingAs($this->owner)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertRedirect();
    });
    
    test('admin can refund payments', function () {
        $this->actingAs($this->admin)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertRedirect();
    });
    
    test('manager can refund payments', function () {
        $this->actingAs($this->manager)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertRedirect();
    });
    
    test('accountant can refund payments', function () {
        $this->actingAs($this->accountant)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertRedirect();
    });
    
    test('employee cannot refund payments', function () {
        $this->actingAs($this->employee)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertForbidden();
    });
    
    test('viewer cannot refund payments', function () {
        $this->actingAs($this->viewer)
            ->post("/payments/{$this->payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Customer requested refund'
            ])
            ->assertForbidden();
    });
});

// Payment Void Tests
describe('Payment Void Authorization', function () {
    test('super admin can void payments', function () {
        $this->actingAs($this->superAdmin)
            ->post("/payments/{$this->payment->id}/void")
            ->assertRedirect();
    });
    
    test('owner can void payments', function () {
        $this->actingAs($this->owner)
            ->post("/payments/{$this->payment->id}/void")
            ->assertRedirect();
    });
    
    test('admin can void payments', function () {
        $this->actingAs($this->admin)
            ->post("/payments/{$this->payment->id}/void")
            ->assertRedirect();
    });
    
    test('accountant can void payments', function () {
        $this->actingAs($this->accountant)
            ->post("/payments/{$this->payment->id}/void")
            ->assertRedirect();
    });
    
    test('manager cannot void payments', function () {
        $this->actingAs($this->manager)
            ->post("/payments/{$this->payment->id}/void")
            ->assertForbidden();
    });
    
    test('employee cannot void payments', function () {
        $this->actingAs($this->employee)
            ->post("/payments/{$this->payment->id}/void")
            ->assertForbidden();
    });
    
    test('viewer cannot void payments', function () {
        $this->actingAs($this->viewer)
            ->post("/payments/{$this->payment->id}/void")
            ->assertForbidden();
    });
});

// Payment Reconcile Tests (if implemented)
describe('Payment Reconcile Authorization', function () {
    test('super admin can reconcile payments', function () {
        $this->actingAs($this->superAdmin)
            ->post("/payments/{$this->payment->id}/reconcile")
            ->assertRedirect();
    });
    
    test('accountant can reconcile payments', function () {
        $this->actingAs($this->accountant)
            ->post("/payments/{$this->payment->id}/reconcile")
            ->assertRedirect();
    });
    
    test('manager cannot reconcile payments', function () {
        $this->actingAs($this->manager)
            ->post("/payments/{$this->payment->id}/reconcile")
            ->assertForbidden();
    });
});

// Test Payment UI Props (Inertia gating)
describe('Payment UI Permission Props', function () {
    test('payment page includes correct permissions for super admin', function () {
        $response = $this->actingAs($this->superAdmin)
            ->get("/payments/{$this->payment->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('payments.view') &&
                $permissions->contains('payments.update') &&
                $permissions->contains('payments.delete') &&
                $permissions->contains('payments.allocate') &&
                $permissions->contains('payments.refund')
            )
        );
    });
    
    test('payment page includes correct permissions for viewer', function () {
        $response = $this->actingAs($this->viewer)
            ->get("/payments/{$this->payment->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('payments.view') &&
                !$permissions->contains('payments.update') &&
                !$permissions->contains('payments.delete') &&
                !$permissions->contains('payments.allocate') &&
                !$permissions->contains('payments.refund')
            )
        );
    });
    
    test('payment page includes correct permissions for manager', function () {
        $response = $this->actingAs($this->manager)
            ->get("/payments/{$this->payment->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('payments.view') &&
                $permissions->contains('payments.create') &&
                $permissions->contains('payments.allocate') &&
                $permissions->contains('payments.refund') &&
                !$permissions->contains('payments.update') &&
                !$permissions->contains('payments.delete')
            )
        );
    });
    
    test('payment list page includes company permissions', function () {
        $response = $this->actingAs($this->accountant)
            ->get('/payments');
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.companyPermissions', fn ($permissions) => 
                $permissions->contains('payments.view') &&
                $permissions->contains('payments.create') &&
                $permissions->contains('payments.allocate') &&
                $permissions->contains('payments.reconcile')
            )
        );
    });
});

// Cross-company access tests
describe('Payment Cross-Company Access', function () {
    test('super admin can access payment from any company', function () {
        $otherCompanyPayment = Payment::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->superAdmin)
            ->get("/payments/{$otherCompanyPayment->id}")
            ->assertSuccessful();
    });
    
    test('regular user cannot access payment from other company', function () {
        $otherCompanyPayment = Payment::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->owner)
            ->get("/payments/{$otherCompanyPayment->id}")
            ->assertForbidden();
    });
    
    test('super admin can create payment for any company', function () {
        $paymentData = Payment::factory()->make([
            'company_id' => $this->otherCompany->id,
            'customer_id' => Customer::factory()->create(['company_id' => $this->otherCompany->id])->id
        ])->toArray();
        
        $this->actingAs($this->superAdmin)
            ->post('/payments', $paymentData)
            ->assertRedirect();
    });
});

// Payment Export Tests
describe('Payment Export Authorization', function () {
    test('super admin can export payments', function () {
        $this->actingAs($this->superAdmin)
            ->get('/payments/export')
            ->assertSuccessful();
    });
    
    test('owner can export payments', function () {
        $this->actingAs($this->owner)
            ->get('/payments/export')
            ->assertSuccessful();
    });
    
    test('admin can export payments', function () {
        $this->actingAs($this->admin)
            ->get('/payments/export')
            ->assertSuccessful();
    });
    
    test('manager cannot export payments', function () {
        $this->actingAs($this->manager)
            ->get('/payments/export')
            ->assertForbidden();
    });
    
    test('accountant can export payments', function () {
        $this->actingAs($this->accountant)
            ->get('/payments/export')
            ->assertSuccessful();
    });
});

// API Tests for Payments
describe('Payment API Authorization', function () {
    test('super admin can access payment API endpoints', function () {
        $this->actingAs($this->superAdmin)
            ->getJson("/api/payments")
            ->assertSuccessful();
    });
    
    test('unauthorized user cannot access payment API', function () {
        $this->actingAs($this->viewer)
            ->postJson("/api/payments", [])
            ->assertForbidden();
    });
    
    test('super admin can auto-allocate via API', function () {
        $this->actingAs($this->superAdmin)
            ->postJson("/api/payments/{$this->payment->id}/auto-allocate")
            ->assertSuccessful();
    });
});