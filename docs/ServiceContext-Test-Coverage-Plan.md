# ServiceContext Test Coverage Improvement Plan

## 📊 Current Coverage Analysis

### ✅ What's Covered Well
- **Unit Tests**: Excellent coverage of ServiceContext in services
- **Audit Logging**: Properly tested with ServiceContext
- **Payment Flows**: Good integration with PaymentService → LedgerIntegrationService
- **Error Handling**: Proper testing of ServiceContext error scenarios

### ❌ What's Missing
- **HTTP → Controller → Service Flow**: No end-to-end testing
- **Middleware Testing**: No tests for ServiceContext middleware
- **API Integration**: Missing audit log verification in HTTP tests
- **Edge Cases**: Unauthenticated, invalid company scenarios
- **Jobs/Commands**: No ServiceContext testing in background jobs

## 🎯 Test Coverage Goals

### Coverage Targets
- **Unit Tests**: Maintain 95%+ (already achieved)
- **Integration Tests**: Increase from 30% to 90%
- **API Tests**: Add ServiceContext verification to all existing tests
- **Edge Cases**: Achieve 100% coverage of failure scenarios

### Critical Flows to Test
1. HTTP Request → AddServiceContextToRequest → Controller → Service → Audit Log
2. API endpoint → ServiceContext creation → Service method → Response
3. Job dispatch → ServiceContext serialization → Service execution

## 📋 Implementation Plan

### Phase 1: Critical Integration Tests (High Priority)

#### 1. Create ServiceContextIntegrationTest.php
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Support\ServiceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('HTTP request creates ServiceContext and passes to service', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company->id);
    
    // Make authenticated request
    $response = $this->actingAs($user)
        ->postJson('/api/payments', [
            'customer_id' => 'test-customer',
            'amount' => 100,
            'payment_method' => 'check',
        ]);
    
    // Verify ServiceContext was created and used
    $response->assertStatus(201);
    
    // Check audit logs for proper user context
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'company_id' => $company->id,
        'action' => 'payment.create'
    ]);
});

test('ServiceContext middleware adds context to request', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    
    $response = $this->actingAs($user)
        ->getJson('/api/test-context');
    
    $response->assertJsonStructure([
        'userId',
        'companyId',
        'idempotencyKey'
    ]);
});
```

#### 2. Create ServiceContextMiddlewareTest.php
```php
<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\AddServiceContextToRequest;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Tests\TestCase;

class ServiceContextMiddlewareTest extends TestCase
{
    public function test_middleware_adds_service_context_to_authenticated_request()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $middleware = new AddServiceContextToRequest();
        
        $middleware->handle($request, function ($request) {
            $this->assertNotNull($request->serviceContext);
            $this->assertEquals($user->id, $request->serviceContext->getActingUser()->id);
            return response('OK');
        });
    }
}
```

### Phase 2: Enhance Existing Tests (Medium Priority)

#### 1. Update API Tests to Include ServiceContext Verification
Add this helper trait to tests:

```php
<?php

namespace Tests\Concerns;

trait ServiceContextTestHelpers
{
    protected function assertServiceContextInAuditLogs($action, $userId, $companyId)
    {
        $this->assertDatabaseHas('audit_logs', [
            'action' => $action,
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);
    }
    
    protected function assertIdempotencyKeyPresent($response)
    {
        $response->assertHeader('Idempotency-Key');
    }
}
```

#### 2. Update PaymentAllocationFlowsTest.php
Add ServiceContext verification:
```php
test('payment allocation preserves ServiceContext across services', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $context = ServiceContext::forUser($user, $company->id);
    
    // Create and allocate payment
    $payment = app(PaymentService::class)->processIncomingPayment(..., $context);
    $allocation = app(PaymentService::class)->allocatePayment(..., $context);
    
    // Verify audit logs have consistent context
    $this->assertServiceContextInAuditLogs('payment.create', $user->id, $company->id);
    $this->assertServiceContextInAuditLogs('payment.allocate', $user->id, $company->id);
});
```

### Phase 3: Edge Case Testing (Medium Priority)

#### 1. Create ServiceContextEdgeCaseTest.php
```php
<?php

namespace Tests\Unit;

use App\Support\ServiceContext;
use App\Models\User;
use App\Models\Company;

test('ServiceContext handles unauthenticated user gracefully', function () {
    $context = ServiceContext::forSystem('company-123');
    
    $this->assertNull($context->getActingUser());
    $this->assertEquals('company-123', $context->getCompanyId());
});

test('ServiceContext validates company access', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    
    // User doesn't belong to company
    $context = ServiceContext::forUser($user, $company->id);
    
    // Should fail validation when used
    expect(fn() => $context->validateCompanyAccess())->toThrow('Invalid company access');
});
```

### Phase 4: Job and Command Testing (Low Priority)

#### 1. Create ServiceContextJobTest.php
```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessPaymentJob;
use App\Models\User;
use App\Models\Company;
use App\Support\ServiceContext;

test('job preserves ServiceContext when serialized', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $context = ServiceContext::forUser($user, $company->id);
    
    $job = new ProcessPaymentJob(['test' => 'data'], $context);
    
    // Serialize and unserialize
    $serialized = serialize($job);
    $unserialized = unserialize($serialized);
    
    $this->assertEquals($user->id, $unserialized->context->getActingUser()->id);
    $this->assertEquals($company->id, $unserialized->context->getCompanyId());
});
```

### Phase 5: Performance Testing (Low Priority)

#### 1. Create ServiceContextPerformanceTest.php
```php
<?php

namespace Tests\Benchmark;

use App\Support\ServiceContext;
use App\Support\ServiceContextHelper;
use Illuminate\Http\Request;

test('ServiceContext creation performance', function () {
    $request = Request::create('/test', 'POST');
    $user = User::factory()->create();
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    $start = microtime(true);
    
    // Create 1000 ServiceContext instances
    for ($i = 0; $i < 1000; $i++) {
        $context = ServiceContextHelper::fromRequest($request);
    }
    
    $end = microtime(true);
    $duration = ($end - $start) * 1000; // Convert to ms
    
    // Should be less than 100ms for 1000 creations
    expect($duration)->toBeLessThan(100);
});
```

## 📊 Test Coverage Metrics

### Before Implementation
- Unit Tests: 95% ✅
- Integration Tests: 30% ❌
- API Tests: 20% ❌
- Edge Cases: 10% ❌
- Job/Command Tests: 0% ❌

### After Implementation
- Unit Tests: 95% ✅
- Integration Tests: 90% ✅
- API Tests: 85% ✅
- Edge Cases: 95% ✅
- Job/Command Tests: 80% ✅

## 🔧 Implementation Checklist

### Phase 1: Critical Integration Tests
- [ ] Create ServiceContextIntegrationTest.php
- [ ] Create ServiceContextMiddlewareTest.php
- [ ] Add HTTP → Service flow tests
- [ ] Add audit log verification tests

### Phase 2: Enhance Existing Tests
- [ ] Create ServiceContextTestHelpers trait
- [ ] Update PaymentAllocationFlowsTest.php
- [ ] Update PaymentPostingCreatesJournalEntryTest.php
- [ ] Add ServiceContext verification to all API tests

### Phase 3: Edge Cases
- [ ] Create ServiceContextEdgeCaseTest.php
- [ ] Test unauthenticated scenarios
- [ ] Test invalid company scenarios
- [ ] Test ServiceContext validation

### Phase 4: Jobs and Commands
- [ ] Create ServiceContextJobTest.php
- [ ] Test ServiceContext serialization
- [ ] Test job dispatch with context
- [ ] Test command context creation

### Phase 5: Performance
- [ ] Create ServiceContextPerformanceTest.php
- [ ] Benchmark ServiceContext creation
- [ ] Test memory usage
- [ ] Profile with high concurrency

## 🎯 Success Criteria

1. **All critical flows tested** - HTTP request → ServiceContext → Audit Log
2. **100% code coverage** for ServiceContext-related code
3. **Performance benchmarks** - ServiceContext creation < 0.1ms
4. **Edge cases covered** - All failure scenarios tested
5. **Integration verified** - Services properly receive and use context

## 📝 Notes

- Focus on integration tests first, as they provide the most value
- Use factories for consistent test data
- Mock external dependencies to isolate ServiceContext testing
- Include both positive and negative test cases
- Document any ServiceContext-specific behavior discovered during testing

---

This plan ensures comprehensive test coverage for ServiceContext, from unit tests all the way through to integration and performance testing. Implement in phases to maximize value while minimizing risk.