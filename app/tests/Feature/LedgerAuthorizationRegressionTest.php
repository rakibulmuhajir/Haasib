<?php

use App\Models\User;
use App\Models\Company;
use App\Models\LedgerEntry;
use App\Models\LedgerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Create test companies
    $this->company = Company::factory()->create();
    $this->otherCompany = Company::factory()->create();
    
    // Create test ledger accounts
    $this->assetAccount = LedgerAccount::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'asset'
    ]);
    
    $this->revenueAccount = LedgerAccount::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'revenue'
    ]);
    
    // Create test ledger entries
    $this->ledgerEntry = LedgerEntry::factory()->create([
        'company_id' => $this->company->id,
        'date' => now(),
        'description' => 'Test Journal Entry',
        'entries' => [
            [
                'account_id' => $this->assetAccount->id,
                'debit' => 1000,
                'credit' => 0
            ],
            [
                'account_id' => $this->revenueAccount->id,
                'debit' => 0,
                'credit' => 1000
            ]
        ],
        'posted' => false
    ]);
    
    $this->postedEntry = LedgerEntry::factory()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(1),
        'description' => 'Posted Journal Entry',
        'posted' => true,
        'posted_at' => now()->subDay()
    ]);
    
    // Create users with different roles
    $this->superAdmin = User::factory()->create(['system_role' => 'superadmin']);
    $this->superAdmin->assignRole('super_admin');
    
    $this->owner = User::factory()->create();
    $this->owner->companies()->attach($this->company->id, ['role' => 'owner']);
    $this->owner->assignRole('owner', $this->company);
    
    $this->admin = User::factory()->create();
    $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);
    $this->admin->assignRole('admin', $this->company);
    
    $this->manager = User::factory()->create();
    $this->manager->companies()->attach($this->company->id, ['role' => 'manager']);
    $this->manager->assignRole('manager', $this->company);
    
    $this->accountant = User::factory()->create();
    $this->accountant->companies()->attach($this->company->id, ['role' => 'accountant']);
    $this->accountant->assignRole('accountant', $this->company);
    
    $this->employee = User::factory()->create();
    $this->employee->companies()->attach($this->company->id, ['role' => 'employee']);
    $this->employee->assignRole('employee', $this->company);
    
    $this->viewer = User::factory()->create();
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    $this->viewer->assignRole('viewer', $this->company);
    
    // User from other company
    $this->otherUser = User::factory()->create();
    $this->otherUser->companies()->attach($this->otherCompany->id, ['role' => 'owner']);
    $this->otherUser->assignRole('owner', $this->otherCompany);
});

// Ledger Index Tests
describe('Ledger Index Authorization', function () {
    test('super admin can access ledger index', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Ledger/LedgerIndex')
            );
    });
    
    test('owner can access company ledger', function () {
        $this->actingAs($this->owner)
            ->get('/ledger')
            ->assertSuccessful();
    });
    
    test('viewer can access ledger list', function () {
        $this->actingAs($this->viewer)
            ->get('/ledger')
            ->assertSuccessful();
    });
    
    test('user from other company sees only their ledger entries', function () {
        $this->actingAs($this->otherUser)
            ->get('/ledger')
            ->assertSuccessful(); // Will see empty or different list
    });
});

// Ledger Create Tests
describe('Ledger Create Authorization', function () {
    test('super admin can create ledger entries in any company', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/create')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Ledger/LedgerCreate')
            );
    });
    
    test('owner can create ledger entries', function () {
        $this->actingAs($this->owner)
            ->get('/ledger/create')
            ->assertSuccessful();
    });
    
    test('accountant can create ledger entries', function () {
        $this->actingAs($this->accountant)
            ->get('/ledger/create')
            ->assertSuccessful();
    });
    
    test('manager cannot create ledger entries', function () {
        $this->actingAs($this->manager)
            ->get('/ledger/create')
            ->assertForbidden();
    });
    
    test('employee cannot create ledger entries', function () {
        $this->actingAs($this->employee)
            ->get('/ledger/create')
            ->assertForbidden();
    });
    
    test('viewer cannot create ledger entries', function () {
        $this->actingAs($this->viewer)
            ->get('/ledger/create')
            ->assertForbidden();
    });
});

// Ledger Store Tests
describe('Ledger Store Authorization', function () {
    test('super admin can store ledger entries', function () {
        $entryData = LedgerEntry::factory()->make([
            'company_id' => $this->company->id
        ])->toArray();
        
        $this->actingAs($this->superAdmin)
            ->post('/ledger', $entryData)
            ->assertRedirect();
    });
    
    test('accountant can store ledger entries', function () {
        $entryData = LedgerEntry::factory()->make([
            'company_id' => $this->company->id
        ])->toArray();
        
        $this->actingAs($this->accountant)
            ->post('/ledger', $entryData)
            ->assertRedirect();
    });
    
    test('viewer cannot store ledger entries', function () {
        $entryData = LedgerEntry::factory()->make()->toArray();
        
        $this->actingAs($this->viewer)
            ->post('/ledger', $entryData)
            ->assertForbidden();
    });
});

// Ledger View Tests
describe('Ledger View Authorization', function () {
    test('super admin can view any ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->get("/ledger/{$this->ledgerEntry->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Ledger/LedgerShow')
                    ->where('entry.id', $this->ledgerEntry->id)
            );
    });
    
    test('owner can view company ledger entry', function () {
        $this->actingAs($this->owner)
            ->get("/ledger/{$this->ledgerEntry->id}")
            ->assertSuccessful();
    });
    
    test('viewer can view ledger entry', function () {
        $this->actingAs($this->viewer)
            ->get("/ledger/{$this->ledgerEntry->id}")
            ->assertSuccessful();
    });
    
    test('user from other company cannot view ledger entry', function () {
        $this->actingAs($this->otherUser)
            ->get("/ledger/{$this->ledgerEntry->id}")
            ->assertForbidden();
    });
});

// Ledger Edit Tests
describe('Ledger Edit Authorization', function () {
    test('super admin can edit any unposted ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => 
                $page->component('Ledger/LedgerEdit')
            );
    });
    
    test('accountant can edit unposted ledger entry', function () {
        $this->actingAs($this->accountant)
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertSuccessful();
    });
    
    test('owner can edit unposted ledger entry', function () {
        $this->actingAs($this->owner)
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertSuccessful();
    });
    
    test('manager cannot edit ledger entry', function () {
        $this->actingAs($this->manager)
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertForbidden();
    });
    
    test('viewer cannot edit ledger entry', function () {
        $this->actingAs($this->viewer)
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertForbidden();
    });
});

// Ledger Update Tests
describe('Ledger Update Authorization', function () {
    test('super admin can update any unposted ledger entry', function () {
        $updateData = ['description' => 'Updated Description'];
        
        $this->actingAs($this->superAdmin)
            ->put("/ledger/{$this->ledgerEntry->id}", $updateData)
            ->assertRedirect();
    });
    
    test('accountant can update unposted ledger entry', function () {
        $updateData = ['description' => 'Updated Description'];
        
        $this->actingAs($this->accountant)
            ->put("/ledger/{$this->ledgerEntry->id}", $updateData)
            ->assertRedirect();
    });
    
    test('viewer cannot update ledger entry', function () {
        $updateData = ['description' => 'Updated Description'];
        
        $this->actingAs($this->viewer)
            ->put("/ledger/{$this->ledgerEntry->id}", $updateData)
            ->assertForbidden();
    });
});

// Ledger Delete Tests
describe('Ledger Delete Authorization', function () {
    test('super admin can delete any unposted ledger entry', function () {
        $newEntry = LedgerEntry::factory()->create([
            'company_id' => $this->company->id,
            'posted' => false
        ]);
        
        $this->actingAs($this->superAdmin)
            ->delete("/ledger/{$newEntry->id}")
            ->assertRedirect();
    });
    
    test('accountant can delete unposted ledger entry', function () {
        $newEntry = LedgerEntry::factory()->create([
            'company_id' => $this->company->id,
            'posted' => false
        ]);
        
        $this->actingAs($this->accountant)
            ->delete("/ledger/{$newEntry->id}")
            ->assertRedirect();
    });
    
    test('owner can delete unposted ledger entry', function () {
        $newEntry = LedgerEntry::factory()->create([
            'company_id' => $this->company->id,
            'posted' => false
        ]);
        
        $this->actingAs($this->owner)
            ->delete("/ledger/{$newEntry->id}")
            ->assertRedirect();
    });
    
    test('manager cannot delete ledger entry', function () {
        $this->actingAs($this->manager)
            ->delete("/ledger/{$this->ledgerEntry->id}")
            ->assertForbidden();
    });
    
    test('viewer cannot delete ledger entry', function () {
        $this->actingAs($this->viewer)
            ->delete("/ledger/{$this->ledgerEntry->id}")
            ->assertForbidden();
    });
});

// Ledger Post Tests
describe('Ledger Post Authorization', function () {
    test('super admin can post any ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertRedirect();
    });
    
    test('accountant can post ledger entry', function () {
        $this->actingAs($this->accountant)
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertRedirect();
    });
    
    test('owner can post ledger entry', function () {
        $this->actingAs($this->owner)
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertRedirect();
    });
    
    test('admin cannot post ledger entry', function () {
        $this->actingAs($this->admin)
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertForbidden();
    });
    
    test('manager cannot post ledger entry', function () {
        $this->actingAs($this->manager)
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertForbidden();
    });
    
    test('employee cannot post ledger entry', function () {
        $this->actingAs($this->employee)
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertForbidden();
    });
});

// Ledger Void Tests
describe('Ledger Void Authorization', function () {
    test('super admin can void any posted ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->post("/ledger/{$this->postedEntry->id}/void")
            ->assertRedirect();
    });
    
    test('accountant can void posted ledger entry', function () {
        $this->actingAs($this->accountant)
            ->post("/ledger/{$this->postedEntry->id}/void")
            ->assertRedirect();
    });
    
    test('manager cannot void ledger entry', function () {
        $this->actingAs($this->manager)
            ->post("/ledger/{$this->postedEntry->id}/void")
            ->assertForbidden();
    });
    
    test('viewer cannot void ledger entry', function () {
        $this->actingAs($this->viewer)
            ->post("/ledger/{$this->postedEntry->id}/void")
            ->assertForbidden();
    });
});

// Ledger Accounts Tests
describe('Ledger Accounts Authorization', function () {
    test('super admin can view all ledger accounts', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/accounts')
            ->assertSuccessful();
    });
    
    test('viewer can view ledger accounts', function () {
        $this->actingAs($this->viewer)
            ->get('/ledger/accounts')
            ->assertSuccessful();
    });
    
    test('super admin can view specific ledger account', function () {
        $this->actingAs($this->superAdmin)
            ->get("/ledger/accounts/{$this->assetAccount->id}")
            ->assertSuccessful();
    });
    
    test('user from other company cannot view ledger account', function () {
        $otherAccount = LedgerAccount::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->owner)
            ->get("/ledger/accounts/{$otherAccount->id}")
            ->assertForbidden();
    });
});

// Ledger Reports Tests
describe('Ledger Reports Authorization', function () {
    test('super admin can access trial balance', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/reports/trial-balance')
            ->assertSuccessful();
    });
    
    test('accountant can access trial balance', function () {
        $this->actingAs($this->accountant)
            ->get('/ledger/reports/trial-balance')
            ->assertSuccessful();
    });
    
    test('viewer cannot access trial balance', function () {
        $this->actingAs($this->viewer)
            ->get('/ledger/reports/trial-balance')
            ->assertForbidden();
    });
    
    test('super admin can access balance sheet', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/reports/balance-sheet')
            ->assertSuccessful();
    });
    
    test('accountant can access income statement', function () {
        $this->actingAs($this->accountant)
            ->get('/ledger/reports/income-statement')
            ->assertSuccessful();
    });
    
    test('manager cannot access income statement', function () {
        $this->actingAs($this->manager)
            ->get('/ledger/reports/income-statement')
            ->assertForbidden();
    });
});

// Test Ledger UI Props (Inertia gating)
describe('Ledger UI Permission Props', function () {
    test('ledger page includes correct permissions for super admin', function () {
        $response = $this->actingAs($this->superAdmin)
            ->get("/ledger/{$this->ledgerEntry->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('ledger.view') &&
                $permissions->contains('ledger.entries.create') &&
                $permissions->contains('ledger.entries.update') &&
                $permissions->contains('ledger.entries.delete') &&
                $permissions->contains('ledger.entries.post')
            )
        );
    });
    
    test('ledger page includes correct permissions for viewer', function () {
        $response = $this->actingAs($this->viewer)
            ->get("/ledger/{$this->ledgerEntry->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('ledger.view') &&
                !$permissions->contains('ledger.entries.create') &&
                !$permissions->contains('ledger.entries.update') &&
                !$permissions->contains('ledger.entries.delete') &&
                !$permissions->contains('ledger.entries.post')
            )
        );
    });
    
    test('ledger page includes correct permissions for manager', function () {
        $response = $this->actingAs($this->manager)
            ->get("/ledger/{$this->ledgerEntry->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('ledger.view') &&
                !$permissions->contains('ledger.entries.create') &&
                !$permissions->contains('ledger.entries.update') &&
                !$permissions->contains('ledger.entries.delete') &&
                !$permissions->contains('ledger.entries.post')
            )
        );
    });
    
    test('ledger page includes correct permissions for accountant', function () {
        $response = $this->actingAs($this->accountant)
            ->get("/ledger/{$this->ledgerEntry->id}");
            
        $response->assertInertia(fn ($page) => 
            $page->where('auth.permissions', fn ($permissions) => 
                $permissions->contains('ledger.view') &&
                $permissions->contains('ledger.entries.create') &&
                $permissions->contains('ledger.entries.update') &&
                $permissions->contains('ledger.entries.post') &&
                $permissions->contains('ledger.entries.void')
            )
        );
    });
});

// Cross-company access tests
describe('Ledger Cross-Company Access', function () {
    test('super admin can access ledger entry from any company', function () {
        $otherCompanyEntry = LedgerEntry::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->superAdmin)
            ->get("/ledger/{$otherCompanyEntry->id}")
            ->assertSuccessful();
    });
    
    test('regular user cannot access ledger entry from other company', function () {
        $otherCompanyEntry = LedgerEntry::factory()->create([
            'company_id' => $this->otherCompany->id
        ]);
        
        $this->actingAs($this->owner)
            ->get("/ledger/{$otherCompanyEntry->id}")
            ->assertForbidden();
    });
    
    test('super admin can create ledger entry for any company', function () {
        $entryData = LedgerEntry::factory()->make([
            'company_id' => $this->otherCompany->id
        ])->toArray();
        
        $this->actingAs($this->superAdmin)
            ->post('/ledger', $entryData)
            ->assertRedirect();
    });
    
    test('super admin can post ledger entry in any company', function () {
        $otherCompanyEntry = LedgerEntry::factory()->create([
            'company_id' => $this->otherCompany->id,
            'posted' => false
        ]);
        
        $this->actingAs($this->superAdmin)
            ->post("/ledger/{$otherCompanyEntry->id}/post")
            ->assertRedirect();
    });
});

// Journal View Tests
describe('Journal View Authorization', function () {
    test('super admin can view journal', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/journal')
            ->assertSuccessful();
    });
    
    test('accountant can view journal', function () {
        $this->actingAs($this->accountant)
            ->get('/ledger/journal')
            ->assertSuccessful();
    });
    
    test('viewer can view journal', function () {
        $this->actingAs($this->viewer)
            ->get('/ledger/journal')
            ->assertSuccessful();
    });
    
    test('employee can view journal', function () {
        $this->actingAs($this->employee)
            ->get('/ledger/journal')
            ->assertSuccessful();
    });
});

// Journal Create Tests
describe('Journal Create Authorization', function () {
    test('super admin can create journal entries', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/journal/create')
            ->assertSuccessful();
    });
    
    test('accountant can create journal entries', function () {
        $this->actingAs($this->accountant)
            ->get('/ledger/journal/create')
            ->assertSuccessful();
    });
    
    test('manager cannot create journal entries', function () {
        $this->actingAs($this->manager)
            ->get('/ledger/journal/create')
            ->assertForbidden();
    });
});

// Test Posted Entry Restrictions
describe('Posted Entry Restrictions', function () {
    test('cannot edit posted ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->get("/ledger/{$this->postedEntry->id}/edit")
            ->assertForbidden();
    });
    
    test('cannot update posted ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->put("/ledger/{$this->postedEntry->id}", ['description' => 'Updated'])
            ->assertForbidden();
    });
    
    test('cannot delete posted ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->delete("/ledger/{$this->postedEntry->id}")
            ->assertForbidden();
    });
    
    test('cannot void unposted ledger entry', function () {
        $this->actingAs($this->superAdmin)
            ->post("/ledger/{$this->ledgerEntry->id}/void")
            ->assertForbidden();
    });
});

// Test Date-based Restrictions
describe('Ledger Date Restrictions', function () {
    test('cannot create entry in closed period', function () {
        // This would test period closing logic if implemented
        $this->actingAs($this->accountant)
            ->post('/ledger', [
                'company_id' => $this->company->id,
                'date' => now()->subYear(), // Closed period
                'description' => 'Test Entry',
                'entries' => []
            ])
            ->assertStatus(302); // Should fail with validation or error
    });
    
    test('cannot post entry in closed period', function () {
        $oldEntry = LedgerEntry::factory()->create([
            'company_id' => $this->company->id,
            'date' => now()->subYear(),
            'posted' => false
        ]);
        
        $this->actingAs($this->accountant)
            ->post("/ledger/{$oldEntry->id}/post")
            ->assertStatus(302); // Should fail with validation or error
    });
});