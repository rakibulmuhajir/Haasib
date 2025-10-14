# ServiceContext PR Review Checklist

> **ARCHIVE NOTICE**: This checklist applied to legacy ServiceContext migrations. Use the Constitution (v2.2.0) and ContextService patterns for current reviews.

## 📋 Overview
This checklist helps reviewers ensure that ServiceContext is properly implemented in new code and that existing code is migrated correctly.

## ✅ Mandatory Checks

### 1. Service Method Signatures
- [ ] All new service methods include `ServiceContext $context` as the last parameter
- [ ] ServiceContext parameter is NOT nullable (required)
- [ ] Method signature follows pattern: `methodName(...params, ServiceContext $context)`
- [ ] PHPDoc includes `@param ServiceContext $context` description

### 2. ServiceContext Usage
- [ ] No `auth()` calls within the service method
- [ ] User context obtained from `$context->getActingUser()`
- [ ] Company context obtained from `$context->getCompanyId()`
- [ ] Idempotency key from `$context->getIdempotencyKey()`
- [ ] Audit logging uses ServiceContext parameter

### 3. Controller Integration
- [ ] Controllers use `ServiceContextHelper::fromRequest($request)`
- [ ] Context passed to all service method calls
- [ ] No direct `auth()` calls in controllers (use ServiceContext)

### 4. Queue Jobs
- [ ] Jobs serialize ServiceContext in constructor
- [ ] Jobs use `ServiceContext::fromArray()` when deserializing
- [ ] Job handle method passes context to services

## 🔄 Migration Checklist (Existing Code)

### When Adding ServiceContext to Existing Service
- [ ] Updated all method signatures to include ServiceContext
- [ ] Added `?ServiceContext $context = null` for backward compatibility (temporary)
- [ ] Updated all callers to pass ServiceContext
- [ ] Removed all `auth()` calls from service
- [ ] Created tests for new ServiceContext behavior

### When Calling Updated Services
- [ ] Check if service method now requires ServiceContext
- [ ] Create appropriate ServiceContext (fromRequest, forUser, forSystem)
- [ ] Update all call sites
- [ ] Update tests to pass ServiceContext

## ⚠️ Common Issues to Watch

### 1. Missing Context
```php
// ❌ Wrong
$service->doSomething($param1, $param2);

// ✅ Correct
$service->doSomething($param1, $param2, $context);
```

### 2. Incorrect Context Creation
```php
// ❌ Wrong - using auth() in service
public function doSomething(ServiceContext $context) {
    $userId = auth()->id(); // Don't do this!
}

// ✅ Correct
public function doSomething(ServiceContext $context) {
    $userId = $context->getActingUser()?->id;
}
```

### 3. Wrong Context Source
```php
// ❌ Wrong - creating context in service
public function doSomething() {
    $context = ServiceContext::fromRequest(request()); // Don't!
}

// ✅ Correct - context is injected
public function doSomething(ServiceContext $context) {
    // Use injected context
}
```

## 📝 PR Template Addition

Add this section to your PR template:

```
## ServiceContext Changes

- [ ] This PR adds/modifies service methods
- [ ] All service methods include ServiceContext parameter
- [ ] All auth() calls removed from services
- [ ] Controllers properly pass ServiceContext
- [ ] Tests updated for ServiceContext
- [ ] No breaking changes for existing callers
```

## 🔍 Quick Verification Commands

### 1. Check for auth() in services
```bash
grep -r "auth()" app/Services/ --include="*.php"
grep -r "auth()->" app/Services/ --include="*.php"
```

### 2. Check ServiceContext usage
```bash
grep -r "ServiceContext" app/ --include="*.php" | grep -v test
```

### 3. Find services without ServiceContext
```bash
find app/Services -name "*.php" -exec grep -L "ServiceContext" {} \;
```

## 📊 Review Metrics

Track these metrics in your PR reviews:
- **% of services using ServiceContext**: Target 100%
- **auth() calls in services**: Target 0
- **ServiceContext coverage in tests**: Target >90%

## 🚨 Red Flags

Reject PRs that:
1. Add new service methods without ServiceContext parameter
2. Use `auth()` calls within services
3. Pass `null` for required ServiceContext parameters
4. Create ServiceContext inside service methods

## 💡 Best Practices

### 1. Controller Pattern
```php
public function store(Request $request)
{
    $context = ServiceContextHelper::fromRequest($request);
    
    $result = $this->service->create(
        $param1,
        $param2,
        $context
    );
    
    return response()->json($result);
}
```

### 2. Job Pattern
```php
class ProcessSomething implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        private array $data,
        private ServiceContext $context
    ) {}
    
    public function handle()
    {
        $this->service->process($this->data, $this->context);
    }
}
```

### 3. Test Pattern
```php
test('service method works with context', function () {
    $user = User::factory()->create();
    $context = ServiceContext::forUser($user, $company->id);
    
    $result = $service->doSomething($param, $context);
    
    expect($result)->toBeSuccessful();
});
```

## 📚 Additional Resources

- [ServiceContext Guide](./ServiceContext-Guide.md)
- [Monitoring Guide](./ServiceContext-Monitoring.md)
- [Rollout Plan](./user-context-refactoring-plan.md)

---

**Remember**: ServiceContext is now mandatory for all service methods. Every PR should either use it correctly or be migrating existing code to use it.
