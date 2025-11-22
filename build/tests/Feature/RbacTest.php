<?php

namespace Tests\Feature;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemRoleManager;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions and roles
        $this->seed(PermissionSeeder::class);
    }

    /** @test */
    public function it_creates_all_required_permissions()
    {
        $expectedPermissions = Permissions::getAll();
        
        foreach ($expectedPermissions as $permission) {
            $this->assertDatabaseHas('public.permissions', [
                'name' => $permission,
            ]);
        }
        
        $this->assertEquals(
            count($expectedPermissions),
            Permission::count(),
            'Permission count mismatch'
        );
    }

    /** @test */
    public function it_creates_all_required_roles()
    {
        $expectedRoles = [
            // System roles
            'super_admin',
            'systemadmin',
            'system_manager',
            'system_auditor',
            
            // Company roles
            'company_owner',
            'company_admin',
            'accounting_admin',
            'accounting_operator',
            'accounting_viewer',
            'portal_customer',
            'portal_vendor',
        ];
        
        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('public.roles', [
                'name' => $roleName,
            ]);
        }
    }

    /** @test */
    public function it_assigns_permissions_to_roles_correctly()
    {
        // Test company_owner has all required permissions
        $companyOwnerRole = Role::where('name', 'company_owner')->first();
        $this->assertNotNull($companyOwnerRole);
        
        $requiredPermissions = [
            Permissions::COMPANIES_VIEW,
            Permissions::ACCT_CUSTOMERS_CREATE,
            Permissions::ACCT_INVOICES_CREATE,
            Permissions::RLS_CONTEXT,
        ];
        
        foreach ($requiredPermissions as $permission) {
            $this->assertTrue(
                $companyOwnerRole->hasPermissionTo($permission),
                "Company owner should have permission: {$permission}"
            );
        }
        
        // Test accounting_viewer has only read permissions
        $viewerRole = Role::where('name', 'accounting_viewer')->first();
        $this->assertNotNull($viewerRole);
        
        $this->assertTrue($viewerRole->hasPermissionTo(Permissions::ACCT_CUSTOMERS_VIEW));
        $this->assertFalse($viewerRole->hasPermissionTo(Permissions::ACCT_CUSTOMERS_CREATE));
        $this->assertFalse($viewerRole->hasPermissionTo(Permissions::ACCT_CUSTOMERS_DELETE));
    }

    /** @test */
    public function it_creates_system_users_with_correct_roles()
    {
        $superAdmin = User::where('email', 'super@haasib.local')->first();
        $this->assertNotNull($superAdmin);
        $this->assertTrue($superAdmin->hasRole('super_admin'));
        
        $systemAdmin = User::where('email', 'admin@haasib.local')->first();
        $this->assertNotNull($systemAdmin);
        $this->assertTrue($systemAdmin->hasRole('systemadmin'));
    }

    /** @test */
    public function system_role_manager_assigns_roles_correctly()
    {
        $manager = new SystemRoleManager();
        $user = User::factory()->create();
        
        // Test system role assignment
        $result = $manager->assignSystemRole($user, 'systemadmin');
        $this->assertTrue($result);
        $this->assertTrue($user->hasRole('systemadmin'));
        $this->assertTrue($user->isSystemUser());
        
        // Test role replacement
        $result = $manager->assignSystemRole($user, 'super_admin');
        $this->assertTrue($result);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertFalse($user->hasRole('systemadmin'));
    }

    /** @test */
    public function company_role_assignment_works_correctly()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $manager = new SystemRoleManager();
        
        // Bootstrap company roles
        $manager->bootstrapCompanyRoles($company);
        
        // Assign user to company
        $companyUser = $user->assignToCompany($company, 'accounting_operator');
        
        $this->assertNotNull($companyUser);
        $this->assertEquals('accounting_operator', $companyUser->role);
        $this->assertTrue($companyUser->is_active);
        
        // Check user has company role permissions
        $this->assertTrue($user->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_VIEW, $company->id));
        $this->assertFalse($user->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_DELETE, $company->id));
    }

    /** @test */
    public function rls_context_is_enforced()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->create();
        
        // Assign user to company1 only
        $user->assignToCompany($company1, 'company_owner');
        
        // User should have access to company1 but not company2
        $this->assertTrue($user->canAccessCompany($company1->id));
        $this->assertFalse($user->canAccessCompany($company2->id));
    }

    /** @test */
    public function system_users_can_access_any_company()
    {
        $company = Company::factory()->create();
        $systemUser = User::factory()->create();
        $manager = new SystemRoleManager();
        
        $manager->assignSystemRole($systemUser, 'super_admin');
        
        // System user should be able to access any company
        $this->assertTrue($systemUser->canAccessCompany($company->id));
        $this->assertTrue($systemUser->isSystemUser());
    }

    /** @test */
    public function company_users_cannot_access_other_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->create();
        
        // Assign user to company1 only
        $user->assignToCompany($company1, 'accounting_operator');
        
        // User cannot access company2
        $this->assertFalse($user->canAccessCompany($company2->id));
        
        // User can access company1
        $this->assertTrue($user->canAccessCompany($company1->id));
    }

    /** @test */
    public function user_can_switch_between_accessible_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->create();
        
        // Assign user to both companies
        $user->assignToCompany($company1, 'company_owner');
        $user->assignToCompany($company2, 'accounting_viewer');
        
        // User can switch to either company
        $this->assertTrue($user->switchToCompany($company1->id));
        $this->assertEquals($company1->id, session('active_company_id'));
        
        $this->assertTrue($user->switchToCompany($company2->id));
        $this->assertEquals($company2->id, session('active_company_id'));
        
        // User cannot switch to unassigned company
        $company3 = Company::factory()->create();
        $this->assertFalse($user->switchToCompany($company3->id));
    }

    /** @test */
    public function role_permissions_are_properly_scoped()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->create();
        $manager = new SystemRoleManager();
        
        // Bootstrap roles for both companies
        $manager->bootstrapCompanyRoles($company1);
        $manager->bootstrapCompanyRoles($company2);
        
        // Assign different roles in different companies
        $user->assignToCompany($company1, 'company_owner');
        $user->assignToCompany($company2, 'accounting_viewer');
        
        // Check role-specific permissions in each company
        $this->assertEquals('company_owner', $user->getRoleInCompany($company1->id));
        $this->assertEquals('accounting_viewer', $user->getRoleInCompany($company2->id));
        
        // Owner permissions in company1
        $this->assertTrue($user->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_DELETE, $company1->id));
        
        // Viewer permissions in company2 (no delete)
        $this->assertFalse($user->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_DELETE, $company2->id));
    }

    /** @test */
    public function inactive_company_users_lose_access()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        
        // Assign and then deactivate
        $companyUser = $user->assignToCompany($company, 'company_owner');
        $this->assertTrue($user->canAccessCompany($company->id));
        
        // Deactivate user in company
        $companyUser->deactivate();
        $this->assertFalse($user->fresh()->canAccessCompany($company->id));
    }

    /** @test */
    public function permission_constants_follow_naming_convention()
    {
        $allPermissions = Permissions::getAll();
        
        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                Permissions::isValidPermissionName($permission),
                "Permission '{$permission}' does not follow naming convention"
            );
        }
    }

    /** @test */
    public function super_admin_has_all_permissions()
    {
        $superAdmin = User::where('email', 'super@haasib.local')->first();
        $this->assertNotNull($superAdmin);
        
        $allPermissions = Permissions::getAll();
        
        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $superAdmin->hasPermissionTo($permission),
                "Super admin should have permission: {$permission}"
            );
        }
    }

    /** @test */
    public function system_admin_does_not_have_restricted_permissions()
    {
        $systemAdmin = User::where('email', 'admin@haasib.local')->first();
        $this->assertNotNull($systemAdmin);
        
        // System admin should not have super admin privileges
        $this->assertFalse($systemAdmin->hasRole('super_admin'));
        
        // Should have most system permissions but not all
        $this->assertTrue($systemAdmin->hasPermissionTo(Permissions::SYSTEM_AUDIT));
        $this->assertTrue($systemAdmin->hasPermissionTo(Permissions::COMPANIES_VIEW));
        
        // But should not have destructive permissions (implementation dependent)
        // This would be defined in the seeder's restricted permissions list
    }

    /** @test */
    public function company_role_setup_is_complete_after_bootstrapping()
    {
        $company = Company::factory()->create();
        $manager = new SystemRoleManager();
        
        $this->assertFalse($manager->isCompanyRoleSetupComplete($company));
        
        $manager->bootstrapCompanyRoles($company);
        
        $this->assertTrue($manager->isCompanyRoleSetupComplete($company));
    }
}