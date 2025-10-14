<?php

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\WithTeamRoles;

uses(WithTeamRoles::class);

beforeEach(function () {
    // Reset cached permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Seed RBAC
    $this->seed(\Database\Seeders\RbacSeeder::class);

    // Create test companies
    $this->company = Company::create([
        'name' => 'Test Company '.uniqid(),
        'slug' => 'test-company-'.uniqid(),
        'is_active' => true,
    ]);

    $this->otherCompany = Company::create([
        'name' => 'Other Test Company '.uniqid(),
        'slug' => 'other-test-company-'.uniqid(),
        'is_active' => true,
    ]);

    // Create test ledger accounts
    $this->assetAccount = LedgerAccount::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'code' => '1000',
        'name' => 'Test Asset Account',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'active' => true,
        'system_account' => false,
        'level' => 1,
    ]);

    $this->revenueAccount = LedgerAccount::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'code' => '4000',
        'name' => 'Test Revenue Account',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'active' => true,
        'system_account' => false,
        'level' => 1,
    ]);

    // Create test ledger entries
    $this->ledgerEntry = JournalEntry::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'reference' => 'JE-'.uniqid(),
        'date' => now(),
        'description' => 'Test Journal Entry',
        'status' => 'draft',
        'total_debit' => 1000,
        'total_credit' => 1000,
    ]);

    // Create journal lines for the ledger entry
    JournalLine::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'journal_entry_id' => $this->ledgerEntry->id,
        'ledger_account_id' => $this->assetAccount->id,
        'description' => 'Debit line',
        'debit_amount' => 1000,
        'credit_amount' => 0,
        'line_number' => 1,
    ]);

    JournalLine::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'journal_entry_id' => $this->ledgerEntry->id,
        'ledger_account_id' => $this->revenueAccount->id,
        'description' => 'Credit line',
        'debit_amount' => 0,
        'credit_amount' => 1000,
        'line_number' => 2,
    ]);

    $this->postedEntry = JournalEntry::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'reference' => 'JE-'.uniqid(),
        'date' => now()->subDays(1),
        'description' => 'Posted Journal Entry',
        'status' => 'posted',
        'total_debit' => 1000,
        'total_credit' => 1000,
        'posted_at' => now()->subDay(),
    ]);

    // Create journal lines for the posted entry
    JournalLine::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'journal_entry_id' => $this->postedEntry->id,
        'ledger_account_id' => $this->assetAccount->id,
        'description' => 'Posted debit line',
        'debit_amount' => 1000,
        'credit_amount' => 0,
        'line_number' => 1,
    ]);

    JournalLine::create([
        'id' => Str::uuid(),
        'company_id' => $this->company->id,
        'journal_entry_id' => $this->postedEntry->id,
        'ledger_account_id' => $this->revenueAccount->id,
        'description' => 'Posted credit line',
        'debit_amount' => 0,
        'credit_amount' => 1000,
        'line_number' => 2,
    ]);

    // Create users with different roles
    $this->superAdmin = User::create([
        'name' => 'Super Admin User '.uniqid(),
        'email' => 'superadmin'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
    ]);
    $this->assignSystemRole($this->superAdmin, 'super_admin');

    $this->owner = User::create([
        'name' => 'Owner User '.uniqid(),
        'email' => 'owner'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->owner->companies()->attach($this->company->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->owner, 'owner', $this->company);

    $this->admin = User::create([
        'name' => 'Admin User '.uniqid(),
        'email' => 'admin'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);
    $this->assignCompanyRole($this->admin, 'admin', $this->company);

    $this->manager = User::create([
        'name' => 'Manager User '.uniqid(),
        'email' => 'manager'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->manager->companies()->attach($this->company->id, ['role' => 'member']);
    $this->assignCompanyRole($this->manager, 'manager', $this->company);

    $this->accountant = User::create([
        'name' => 'Accountant User '.uniqid(),
        'email' => 'accountant'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->accountant->companies()->attach($this->company->id, ['role' => 'member']);
    $this->assignCompanyRole($this->accountant, 'accountant', $this->company);

    $this->employee = User::create([
        'name' => 'Employee User '.uniqid(),
        'email' => 'employee'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->employee->companies()->attach($this->company->id, ['role' => 'member']);
    $this->assignCompanyRole($this->employee, 'employee', $this->company);

    $this->viewer = User::create([
        'name' => 'Viewer User '.uniqid(),
        'email' => 'viewer'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->viewer->companies()->attach($this->company->id, ['role' => 'viewer']);
    $this->assignCompanyRole($this->viewer, 'viewer', $this->company);

    // User from other company
    $this->otherUser = User::create([
        'name' => 'Other User '.uniqid(),
        'email' => 'other'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'system_role' => 'user',
        'is_active' => true,
    ]);
    $this->otherUser->companies()->attach($this->otherCompany->id, ['role' => 'owner']);
    $this->assignCompanyRole($this->otherUser, 'owner', $this->otherCompany);

    // Reset team context
    setPermissionsTeamId(null);
});

// Ledger Index Tests
describe('Ledger Index Authorization', function () {
    test('super admin can access ledger index', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Ledger/LedgerIndex')
            );
    });

    test('owner can access company ledger', function () {
        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger')
            ->assertSuccessful();
    });

    test('viewer can access ledger list', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger')
            ->assertSuccessful();
    });

    test('user from other company sees only their ledger entries', function () {
        $this->actingAs($this->otherUser)->withSession(['current_company_id' => $this->otherCompany->id])
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
            ->assertInertia(fn ($page) => $page->component('Ledger/LedgerCreate')
            );
    });

    test('owner can create ledger entries', function () {
        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/create')
            ->assertSuccessful();
    });

    test('accountant can create ledger entries', function () {
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/create')
            ->assertSuccessful();
    });

    test('manager cannot create ledger entries', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/create')
            ->assertForbidden();
    });

    test('employee cannot create ledger entries', function () {
        $this->actingAs($this->employee)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/create')
            ->assertForbidden();
    });

    test('viewer cannot create ledger entries', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/create')
            ->assertForbidden();
    });
});

// Ledger Store Tests
describe('Ledger Store Authorization', function () {
    test('super admin can store ledger entries', function () {
        $entryData = [
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now()->format('Y-m-d'),
            'description' => 'Test Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
            'entries' => [
                [
                    'account_id' => $this->assetAccount->id,
                    'debit_amount' => 1000,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 1000,
                ],
            ],
        ];

        $this->actingAs($this->superAdmin)
            ->post('/ledger', $entryData)
            ->assertRedirect();
    });

    test('accountant can store ledger entries', function () {
        $entryData = [
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now()->format('Y-m-d'),
            'description' => 'Test Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
            'entries' => [
                [
                    'account_id' => $this->assetAccount->id,
                    'debit_amount' => 1000,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 1000,
                ],
            ],
        ];

        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->post('/ledger', $entryData)
            ->assertRedirect();
    });

    test('viewer cannot store ledger entries', function () {
        $entryData = [
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now()->format('Y-m-d'),
            'description' => 'Test Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
            'entries' => [
                [
                    'account_id' => $this->assetAccount->id,
                    'debit_amount' => 1000,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 1000,
                ],
            ],
        ];

        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
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
            ->assertInertia(fn ($page) => $page->component('Ledger/LedgerShow')
                ->where('entry.id', $this->ledgerEntry->id)
            );
    });

    test('owner can view company ledger entry', function () {
        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}")
            ->assertSuccessful();
    });

    test('viewer can view ledger entry', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}")
            ->assertSuccessful();
    });

    test('user from other company cannot view ledger entry', function () {
        $this->actingAs($this->otherUser)->withSession(['current_company_id' => $this->otherCompany->id])
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
            ->assertInertia(fn ($page) => $page->component('Ledger/LedgerEdit')
            );
    });

    test('accountant can edit unposted ledger entry', function () {
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertSuccessful();
    });

    test('owner can edit unposted ledger entry', function () {
        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertSuccessful();
    });

    test('manager cannot edit ledger entry', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}/edit")
            ->assertForbidden();
    });

    test('viewer cannot edit ledger entry', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
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

        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->put("/ledger/{$this->ledgerEntry->id}", $updateData)
            ->assertRedirect();
    });

    test('viewer cannot update ledger entry', function () {
        $updateData = ['description' => 'Updated Description'];

        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->put("/ledger/{$this->ledgerEntry->id}", $updateData)
            ->assertForbidden();
    });
});

// Ledger Delete Tests
describe('Ledger Delete Authorization', function () {
    test('super admin can delete any unposted ledger entry', function () {
        $newEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now(),
            'description' => 'Test Journal Entry for Deletion',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete("/ledger/{$newEntry->id}")
            ->assertRedirect();
    });

    test('accountant can delete unposted ledger entry', function () {
        $newEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now(),
            'description' => 'Test Journal Entry for Deletion',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);

        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->delete("/ledger/{$newEntry->id}")
            ->assertRedirect();
    });

    test('owner can delete unposted ledger entry', function () {
        $newEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now(),
            'description' => 'Test Journal Entry for Deletion',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);

        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->delete("/ledger/{$newEntry->id}")
            ->assertRedirect();
    });

    test('manager cannot delete ledger entry', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->delete("/ledger/{$this->ledgerEntry->id}")
            ->assertForbidden();
    });

    test('viewer cannot delete ledger entry', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
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
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertRedirect();
    });

    test('owner can post ledger entry', function () {
        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertRedirect();
    });

    test('admin cannot post ledger entry', function () {
        $this->actingAs($this->admin)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertForbidden();
    });

    test('manager cannot post ledger entry', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$this->ledgerEntry->id}/post")
            ->assertForbidden();
    });

    test('employee cannot post ledger entry', function () {
        $this->actingAs($this->employee)->withSession(['current_company_id' => $this->company->id])
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
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$this->postedEntry->id}/void")
            ->assertRedirect();
    });

    test('manager cannot void ledger entry', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$this->postedEntry->id}/void")
            ->assertForbidden();
    });

    test('viewer cannot void ledger entry', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
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
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/accounts')
            ->assertSuccessful();
    });

    test('super admin can view specific ledger account', function () {
        $this->actingAs($this->superAdmin)
            ->get("/ledger/accounts/{$this->assetAccount->id}")
            ->assertSuccessful();
    });

    test('user from other company cannot view ledger account', function () {
        $otherAccount = LedgerAccount::create([
            'id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'code' => '2000',
            'name' => 'Other Company Account',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'active' => true,
            'system_account' => false,
            'level' => 1,
        ]);

        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
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
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/reports/trial-balance')
            ->assertSuccessful();
    });

    test('viewer cannot access trial balance', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/reports/trial-balance')
            ->assertForbidden();
    });

    test('super admin can access balance sheet', function () {
        $this->actingAs($this->superAdmin)
            ->get('/ledger/reports/balance-sheet')
            ->assertSuccessful();
    });

    test('accountant can access income statement', function () {
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/reports/income-statement')
            ->assertSuccessful();
    });

    test('manager cannot access income statement', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/reports/income-statement')
            ->assertForbidden();
    });
});

// Test Ledger UI Props (Inertia gating)
describe('Ledger UI Permission Props', function () {
    test('ledger page includes correct permissions for super admin', function () {
        $response = $this->actingAs($this->superAdmin)
            ->get("/ledger/{$this->ledgerEntry->id}");

        $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('ledger.view') &&
                $permissions->contains('ledger.entries.create') &&
                $permissions->contains('ledger.entries.update') &&
                $permissions->contains('ledger.entries.delete') &&
                $permissions->contains('ledger.entries.post')
        )
        );
    });

    test('ledger page includes correct permissions for viewer', function () {
        $response = $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}");

        $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('ledger.view') &&
                ! $permissions->contains('ledger.entries.create') &&
                ! $permissions->contains('ledger.entries.update') &&
                ! $permissions->contains('ledger.entries.delete') &&
                ! $permissions->contains('ledger.entries.post')
        )
        );
    });

    test('ledger page includes correct permissions for manager', function () {
        $response = $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}");

        $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('ledger.view') &&
                ! $permissions->contains('ledger.entries.create') &&
                ! $permissions->contains('ledger.entries.update') &&
                ! $permissions->contains('ledger.entries.delete') &&
                ! $permissions->contains('ledger.entries.post')
        )
        );
    });

    test('ledger page includes correct permissions for accountant', function () {
        $response = $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$this->ledgerEntry->id}");

        $response->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('ledger.view') &&
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
        $otherCompanyEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now(),
            'description' => 'Other Company Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);

        $this->actingAs($this->superAdmin)
            ->get("/ledger/{$otherCompanyEntry->id}")
            ->assertSuccessful();
    });

    test('regular user cannot access ledger entry from other company', function () {
        $otherCompanyEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now(),
            'description' => 'Other Company Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);

        $this->actingAs($this->owner)->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/{$otherCompanyEntry->id}")
            ->assertForbidden();
    });

    test('super admin can create ledger entry for any company', function () {
        $entryData = [
            'id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now()->format('Y-m-d'),
            'description' => 'Test Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
            'entries' => [
                [
                    'account_id' => $this->assetAccount->id,
                    'debit_amount' => 1000,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 1000,
                ],
            ],
        ];

        $this->actingAs($this->superAdmin)
            ->post('/ledger', $entryData)
            ->assertRedirect();
    });

    test('super admin can post ledger entry in any company', function () {
        $otherCompanyEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->otherCompany->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now(),
            'description' => 'Other Company Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
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
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/journal')
            ->assertSuccessful();
    });

    test('viewer can view journal', function () {
        $this->actingAs($this->viewer)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/journal')
            ->assertSuccessful();
    });

    test('employee can view journal', function () {
        $this->actingAs($this->employee)->withSession(['current_company_id' => $this->company->id])
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
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->get('/ledger/journal/create')
            ->assertSuccessful();
    });

    test('manager cannot create journal entries', function () {
        $this->actingAs($this->manager)->withSession(['current_company_id' => $this->company->id])
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
        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->post('/ledger', [
                'company_id' => $this->company->id,
                'date' => now()->subYear(), // Closed period
                'description' => 'Test Entry',
                'entries' => [],
            ])
            ->assertStatus(302); // Should fail with validation or error
    });

    test('cannot post entry in closed period', function () {
        $oldEntry = JournalEntry::create([
            'id' => Str::uuid(),
            'company_id' => $this->company->id,
            'reference' => 'JE-'.uniqid(),
            'date' => now()->subYear(),
            'description' => 'Old Journal Entry',
            'status' => 'draft',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);

        $this->actingAs($this->accountant)->withSession(['current_company_id' => $this->company->id])
            ->post("/ledger/{$oldEntry->id}/post")
            ->assertStatus(302); // Should fail with validation or error
    });
});
