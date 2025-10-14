# User Context Refactoring Plan ✅ COMPLETED

> **ARCHIVE NOTICE**: This plan reflects the deprecated ServiceContext DTO. The active implementation uses `App\Services\ContextService`; reference this file only for historical notes.

## Overview
Replace global `auth()` calls in services with explicit user context injection to improve testability and enable queued processing.

**Status**: ✅ **COMPLETED** - All phases successfully implemented

## Current Problem
Services currently reach into `auth()` global for:
- Audit logging (`auth()->user()`)
- User ID tracking (`auth()->id()`)
- Authorization checks

This makes services:
- Hard to test (requires authenticated context)
- Unable to be queued (no auth context in queue workers)
- Tightly coupled to HTTP request lifecycle

## Solution: Service Context DTO
```php
// Already created: app/Support/ServiceContext.php
class ServiceContext
{
    public function __construct(
        private readonly ?User $actingUser = null,
        private readonly ?string $companyId = null,
        private readonly ?string $idempotencyKey = null
    );
    
    // No longer needed - fromAuth() method removed after cleanup
    
    // Getters and with* methods for immutable updates
}
```

## Rollout Strategy

### Phase 1: Core Infrastructure ✅
- [x] Create ServiceContext DTO
- [x] Update AuditLogging trait to accept context
- [x] Create tests documenting new behavior

### Phase 2: PaymentService (High Impact, Isolated)
**Why first?**  
- Self-contained domain
- Clear user/company boundaries
- High value for queuing payments

**Changes needed:**
```php
// Before
public function createPayment(Company $company, Customer $customer, ...): Payment
{
    // auth()->user() used internally
}

// After  
public function createPayment(
    Company $company, 
    Customer $customer, 
    ...,
    ?ServiceContext $context = null
): Payment
{
    // ServiceContext is now required parameter - no fallback
    // Use context for all auth-related operations
}
```

**Caller updates:**
```php
// Controllers
$payment = $service->createPayment($company, $customer, ..., $context);

// Queue jobs
$payment = $service->createPayment($company, $customer, ..., new ServiceContext($systemUser, $company->id));
```

### Phase 3: InvoiceService (Medium Impact)
- Similar pattern to PaymentService
- More complex due to PDF generation and email flows
- Update controllers and related services

### Phase 4: LedgerIntegrationService (Lower Impact)
- Primarily internal service
- Called by other services (already have context)
- Focus on auth()->id() replacements

### Phase 5: Remaining Services
- Any other services using auth()
- Domain-specific services as needed

## Implementation Details

### Method Signature Pattern
1. **COMPLETED**: Add optional `?ServiceContext $context = null` parameter
2. **COMPLETED**: Default to `ServiceContext::fromAuth()` for backward compatibility
3. **COMPLETED**: Use context for all auth/audit operations
4. **COMPLETED**: Remove direct `auth()` calls
5. **COMPLETED**: Remove fallback behavior and make ServiceContext required
6. **COMPLETED**: Remove `fromAuth()` static method from ServiceContext

### Controller Integration
```php
// Create context once per request
$context = new ServiceContext(
    actingUser: auth()->user(),
    companyId: $company->id,
    idempotencyKey: $request->header('Idempotency-Key')
);

// Pass to all service calls
$result = $service->doSomething($context);
```

### Queue Job Integration
```php
class ProcessPaymentJob implements ShouldQueue
{
    public function __construct(
        private readonly array $paymentData,
        private readonly ServiceContext $context
    ) {}
    
    public function handle(PaymentService $service)
    {
        // Context already contains user/company info
        $payment = $service->createPayment(..., $this->context);
    }
}
```

## Testing Strategy

### Unit Tests
- Test services with mock contexts
- Test authorization logic separately
- Verify audit logging with different contexts

### Integration Tests  
- Test controller → service → database flow
- Test queued job → service → database flow
- Verify user/company scoping

### Migration Tests
- Test that old signature still works (backward compatibility)
- Test new explicit context usage
- Verify audit logs contain correct user info

## Risk Mitigation

### Backward Compatibility
- All context parameters are optional
- Default to auth() when no context provided
- Existing code continues to work unchanged

### Gradual Rollout
- Service by service, not big bang
- Each service can be merged independently
- Teams can adopt at their own pace

### Runtime Safety
- Type hints prevent null context issues
- Exceptions clear if required context missing
- Audit logs preserve existing behavior

## Success Criteria ✅ COMPLETED

1. ✅ No direct `auth()` calls in services
2. ✅ All services testable without HTTP context  
3. ✅ Services can be queued with explicit context
4. ✅ Audit logs correctly record acting users
5. ✅ Existing functionality unchanged
6. ✅ Performance impact minimal

## Timeline Estimate

- Phase 2 (PaymentService): 2-3 days
- Phase 3 (InvoiceService): 3-4 days  
- Phase 4 (LedgerService): 1-2 days
- Phase 5 (Remaining): 2-3 days
- Testing & Buffer: 3-4 days

**Total: 11-16 days**

## Implementation Summary ✅ COMPLETED

### What Was Delivered:
- ✅ **ServiceContext DTO**: Created `/app/Support/ServiceContext.php` with user, company, and idempotency key support
- ✅ **ServiceContextHelper**: Created helper methods for common context creation patterns
- ✅ **Facade**: Created `ServiceContext` facade for easy access
- ✅ **Middleware**: Created `AddServiceContextToRequest` middleware for automatic context injection
- ✅ **AuditLogging Trait**: Updated to use ServiceContext instead of auth()
- ✅ **PaymentService**: Completely refactored to require ServiceContext
- ✅ **InvoiceService**: Refactored to use ServiceContext
- ✅ **LedgerIntegrationService**: Updated to use ServiceContext
- ✅ **Controller Integration**: All controllers updated to pass ServiceContext to services
- ✅ **Comprehensive Testing**: Full test coverage for ServiceContext integration
- ✅ **Documentation**: Complete migration guide and usage documentation

### Files Created/Modified:
- `app/Support/ServiceContext.php` - Core DTO
- `app/Support/ServiceContextHelper.php` - Helper methods
- `app/Support/Facades/ServiceContext.php` - Facade
- `app/Http/Middleware/AddServiceContextToRequest.php` - Middleware
- `app/Traits/AuditLogging.php` - Updated trait
- `app/Services/PaymentService.php` - Refactored service
- `app/Services/InvoiceService.php` - Refactored service
- `app/Services/LedgerIntegrationService.php` - Refactored service
- All controllers updated to use ServiceContext
- Comprehensive test suite added

## Documentation
For detailed usage information, see: `/docs/ServiceContext-Guide.md`
