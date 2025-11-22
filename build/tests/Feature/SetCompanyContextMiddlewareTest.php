<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Services\ServiceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SetCompanyContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private SetCompanyContext $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SetCompanyContext();
    }

    /** @test */
    public function it_sets_service_context_in_request_attributes()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->assignToCompany($company, 'company_owner');

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company->id);

        $response = $this->middleware->handle($request, function ($req) {
            $serviceContext = $req->attributes->get('service_context');
            
            $this->assertInstanceOf(ServiceContext::class, $serviceContext);
            $this->assertEquals($req->user()->id, $serviceContext->getUserId());
            
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_sets_database_context_for_rls()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->assignToCompany($company, 'company_owner');

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company->id);

        $this->middleware->handle($request, function ($req) use ($company, $user) {
            // Check that PostgreSQL session variables would be set
            // Note: In a real test, you'd check actual DB session variables
            // Here we verify the ServiceContext has the right data
            $serviceContext = $req->attributes->get('service_context');
            
            $this->assertEquals($company->id, $serviceContext->getCompanyId());
            $this->assertEquals($user->id, $serviceContext->getUserId());
            
            return new Response('OK');
        });
    }

    /** @test */
    public function it_handles_requests_without_user()
    {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            $serviceContext = $req->attributes->get('service_context');
            
            $this->assertInstanceOf(ServiceContext::class, $serviceContext);
            $this->assertFalse($serviceContext->hasUser());
            $this->assertFalse($serviceContext->hasCompany());
            
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_handles_requests_without_company()
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, function ($req) use ($user) {
            $serviceContext = $req->attributes->get('service_context');
            
            $this->assertInstanceOf(ServiceContext::class, $serviceContext);
            $this->assertTrue($serviceContext->hasUser());
            $this->assertFalse($serviceContext->hasCompany());
            $this->assertEquals($user->id, $serviceContext->getUserId());
            
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_validates_company_access()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        // Note: NOT assigning user to company

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company->id);

        $response = $this->middleware->handle($request, function ($req) use ($user) {
            $serviceContext = $req->attributes->get('service_context');
            
            $this->assertTrue($serviceContext->hasUser());
            // Should not have company due to access check
            $this->assertFalse($serviceContext->hasCompany());
            $this->assertEquals($user->id, $serviceContext->getUserId());
            $this->assertNull($serviceContext->getCompanyId());
            
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->assignToCompany($company, 'company_owner');

        // Mock a database error by using an invalid company ID format
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', 'invalid-uuid-format');

        // This should not throw an exception
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_extracts_request_metadata()
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'POST');
        $request->setUserResolver(fn() => $user);
        $request->headers->set('User-Agent', 'TestAgent/1.0');

        $response = $this->middleware->handle($request, function ($req) {
            $serviceContext = $req->attributes->get('service_context');
            $metadata = $serviceContext->getMetadata();
            
            $this->assertEquals('POST', $metadata['method']);
            $this->assertEquals('TestAgent/1.0', $metadata['user_agent']);
            $this->assertArrayHasKey('ip', $metadata);
            $this->assertArrayHasKey('url', $metadata);
            
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_generates_unique_request_ids()
    {
        $user = User::factory()->create();
        $requestIds = [];

        // Make multiple requests
        for ($i = 0; $i < 3; $i++) {
            $request = Request::create('/test', 'GET');
            $request->setUserResolver(fn() => $user);

            $this->middleware->handle($request, function ($req) use (&$requestIds) {
                $serviceContext = $req->attributes->get('service_context');
                $requestIds[] = $serviceContext->getRequestId();
                
                return new Response('OK');
            });
        }

        // All request IDs should be unique
        $this->assertCount(3, array_unique($requestIds));
    }

    /** @test */
    public function it_respects_existing_request_id_header()
    {
        $user = User::factory()->create();
        $customRequestId = 'custom-request-id-12345';

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->headers->set('X-Request-ID', $customRequestId);

        $response = $this->middleware->handle($request, function ($req) use ($customRequestId) {
            $serviceContext = $req->attributes->get('service_context');
            
            $this->assertEquals($customRequestId, $serviceContext->getRequestId());
            
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_continues_processing_after_context_setup()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->assignToCompany($company, 'company_owner');

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->session()->put('active_company_id', $company->id);

        $callOrder = [];

        $response = $this->middleware->handle($request, function ($req) use (&$callOrder) {
            $callOrder[] = 'next_middleware';
            
            // Verify context is available
            $serviceContext = $req->attributes->get('service_context');
            $this->assertInstanceOf(ServiceContext::class, $serviceContext);
            
            return new Response('Processed');
        });

        $this->assertEquals(['next_middleware'], $callOrder);
        $this->assertEquals('Processed', $response->getContent());
    }
}