<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\ServiceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ServiceContextTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_context_from_request_with_user_and_company()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        // Assign user to company
        $user->assignToCompany($company, 'company_owner');
        
        // Mock request
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company->id);
        
        $context = ServiceContext::fromRequest($request);
        
        $this->assertTrue($context->hasUser());
        $this->assertTrue($context->hasCompany());
        $this->assertEquals($user->id, $context->getUserId());
        $this->assertEquals($company->id, $context->getCompanyId());
        $this->assertFalse($context->isSystemContext());
    }

    /** @test */
    public function it_validates_user_company_access()
    {
        $user = User::factory()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        // Assign user to company1 only
        $user->assignToCompany($company1, 'accounting_operator');
        
        // Mock request with company1
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company1->id);
        
        $context = ServiceContext::fromRequest($request);
        
        // Should have access to company1
        $this->assertTrue($context->canAccessCompany($company1->id));
        $this->assertTrue($context->canAccessCompany()); // current company
        
        // Should not have access to company2
        $this->assertFalse($context->canAccessCompany($company2->id));
    }

    /** @test */
    public function it_creates_system_context()
    {
        $context = ServiceContext::forSystem(['operation' => 'maintenance']);
        
        $this->assertFalse($context->hasUser());
        $this->assertFalse($context->hasCompany());
        $this->assertTrue($context->isSystemContext());
        $this->assertEquals('system', $context->getMetadata()['source']);
        $this->assertEquals('maintenance', $context->getMetadata()['operation']);
    }

    /** @test */
    public function it_creates_cli_context()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        $context = ServiceContext::forCli($user, $company, ['command' => 'test:command']);
        
        $this->assertTrue($context->hasUser());
        $this->assertTrue($context->hasCompany());
        $this->assertFalse($context->isSystemContext());
        $this->assertEquals('cli', $context->getMetadata()['source']);
        $this->assertEquals('test:command', $context->getMetadata()['command']);
    }

    /** @test */
    public function it_validates_context_correctly()
    {
        // Valid user context
        $user = User::factory()->create();
        $context = ServiceContext::forCli($user);
        $this->assertTrue($context->validate());
        
        // Valid system context
        $systemContext = ServiceContext::forSystem();
        $this->assertTrue($systemContext->validate());
        
        // Invalid context (no user and not system)
        $invalidContext = new ServiceContext();
        $this->assertFalse($invalidContext->validate());
    }

    /** @test */
    public function it_validates_company_operations()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        // Assign user to company
        $user->assignToCompany($company, 'company_owner');
        
        // Valid company context
        $context = ServiceContext::forCli($user, $company);
        $this->assertTrue($context->validateForCompanyOperation());
        
        // Invalid - user but no company
        $contextNoCompany = ServiceContext::forCli($user);
        $this->assertFalse($contextNoCompany->validateForCompanyOperation());
        
        // Invalid - company user has no access to
        $otherCompany = Company::factory()->create();
        $contextWrongCompany = ServiceContext::forCli($user, $otherCompany);
        $this->assertFalse($contextWrongCompany->validateForCompanyOperation());
    }

    /** @test */
    public function it_provides_audit_context()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        $context = ServiceContext::forCli($user, $company, ['operation' => 'test']);
        $auditContext = $context->getAuditContext();
        
        $this->assertArrayHasKey('request_id', $auditContext);
        $this->assertArrayHasKey('user_id', $auditContext);
        $this->assertArrayHasKey('company_id', $auditContext);
        $this->assertArrayHasKey('metadata', $auditContext);
        $this->assertArrayHasKey('timestamp', $auditContext);
        
        $this->assertEquals($user->id, $auditContext['user_id']);
        $this->assertEquals($company->id, $auditContext['company_id']);
    }

    /** @test */
    public function it_switches_context_immutably()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        $originalContext = ServiceContext::forCli($user1, $company1);
        
        // Switch user
        $newUserContext = $originalContext->withUser($user2);
        $this->assertEquals($user1->id, $originalContext->getUserId());
        $this->assertEquals($user2->id, $newUserContext->getUserId());
        
        // Switch company
        $newCompanyContext = $originalContext->withCompany($company2);
        $this->assertEquals($company1->id, $originalContext->getCompanyId());
        $this->assertEquals($company2->id, $newCompanyContext->getCompanyId());
        
        // Add metadata
        $newMetadataContext = $originalContext->withMetadata(['new_key' => 'new_value']);
        $this->assertArrayNotHasKey('new_key', $originalContext->getMetadata());
        $this->assertArrayHasKey('new_key', $newMetadataContext->getMetadata());
    }

    /** @test */
    public function it_converts_to_array_for_debugging()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        $context = ServiceContext::forCli($user, $company);
        $array = $context->toArray();
        
        $expectedKeys = [
            'user_id',
            'company_id', 
            'request_id',
            'has_user',
            'has_company',
            'is_system',
            'metadata',
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
        
        $this->assertEquals($user->id, $array['user_id']);
        $this->assertEquals($company->id, $array['company_id']);
        $this->assertTrue($array['has_user']);
        $this->assertTrue($array['has_company']);
        $this->assertFalse($array['is_system']);
    }

    /** @test */
    public function it_sets_database_context_safely()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        $context = ServiceContext::forCli($user, $company);
        
        // This should not throw an exception
        $context->setDatabaseContext();
        
        // Verify database variables were set (this is hard to test directly)
        // In a real application, you would check the PostgreSQL session variables
        $this->assertTrue(true); // Placeholder assertion
    }

    /** @test */
    public function it_handles_missing_company_gracefully()
    {
        $user = User::factory()->create();
        
        // Mock request without company
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        
        $context = ServiceContext::fromRequest($request);
        
        $this->assertTrue($context->hasUser());
        $this->assertFalse($context->hasCompany());
        $this->assertNull($context->getCompanyId());
    }

    /** @test */
    public function it_handles_invalid_company_access()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        // Mock request with company user doesn't have access to
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company->id);
        
        $context = ServiceContext::fromRequest($request);
        
        // Should have user but no company due to access check
        $this->assertTrue($context->hasUser());
        $this->assertFalse($context->hasCompany());
        $this->assertNull($context->getCompanyId());
    }
}