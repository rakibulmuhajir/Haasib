# ServiceContext Implementation Guide

> **ARCHIVE NOTICE**: The ServiceContext pattern has been superseded by `App\Services\ContextService` in the `stack/` workspace. Retain this file for historical reference only; do not base new specifications on it.

## Overview

ServiceContext is a comprehensive solution for managing user context throughout the application, replacing global `auth()` calls with explicit context injection. This improves testability, enables queued processing, and provides better audit logging.

## Table of Contents

1. [Core Concepts](#core-concepts)
2. [Installation](#installation)
3. [Basic Usage](#basic-usage)
4. [Advanced Patterns](#advanced-patterns)
5. [Migration from auth()](#migration-from-auth)
6. [Testing](#testing)
7. [Best Practices](#best-practices)
8. [API Reference](#api-reference)
9. [Examples](#examples)

## Core Concepts

### What is ServiceContext?

ServiceContext is a Data Transfer Object (DTO) that carries user, company, and idempotency information through the application. It provides a clean way to pass context without relying on global state.

### Key Components

1. **ServiceContext DTO** (`app/Support/ServiceContext.php`)
   - Core data structure
   - Immutable by design
   - Contains user, company, and idempotency information

2. **ServiceContextHelper** (`app/Support/ServiceContextHelper.php`)
   - Factory methods for common scenarios
   - Request-to-context conversion
   - Background job context creation

3. **ServiceContext Facade** (`app/Support/Facades/ServiceContext.php`)
   - Clean API access
   - Static method interface

4. **Middleware** (`app/Http/Middleware/AddServiceContextToRequest.php`)
   - Automatic context injection
   - Request attribute integration

## Installation

The ServiceContext implementation is already integrated into the application. No additional installation required.

### Configuration

Add the middleware to your HTTP kernel:

```php
// app/Http/Kernel.php
protected $middleware = [
    // ... other middleware
    \App\Http\Middleware\AddServiceContextToRequest::class,
];
```

## Basic Usage

### In Controllers

```php
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Support\ServiceContextHelper;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, PaymentService $paymentService)
    {
        // Create context from current request
        $context = ServiceContextHelper::fromRequest($request);
        
        // Pass context to service
        $payment = $paymentService->createPayment(
            company: $request->user()->current_company,
            customer: $customer,
            amount: $amount,
            context: $context
        );
        
        return response()->json($payment);
    }
}
```

### Using the Facade

```php
use App\Support\Facades\ServiceContext;

class InvoiceController extends Controller
{
    public function update(Request $request, Invoice $invoice, InvoiceService $service)
    {
        // Using facade for cleaner syntax
        $context = ServiceContext::fromRequest($request);
        
        $updated = $service->updateInvoice($invoice, $request->validated(), $context);
        
        return response()->json($updated);
    }
}
```

### Accessing Context from Request

The middleware automatically adds ServiceContext to the request attributes:

```php
public function show(Request $request, Payment $payment)
{
    // Access from request attributes
    $context = $request->attributes->get('service_context');
    
    // Use context for additional operations
    $allocations = $this->paymentService->getPaymentAllocations($payment, $context);
}
```

## Advanced Patterns

### Queue Jobs

ServiceContext enables proper context handling in queued jobs:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Support\ServiceContext;

class ProcessPaymentJob implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        private readonly array $paymentData,
        private readonly ServiceContext $context
    ) {}
    
    public function handle(PaymentService $service)
    {
        // Context already contains user/company info
        $payment = $service->processPaymentAsync($this->paymentData, $this->context);
        
        // Log with proper audit trail
        Log::info('Payment processed', [
            'payment_id' => $payment->id,
            'user_id' => $this->context->getActingUser()?->id,
            'company_id' => $this->context->getCompanyId()
        ]);
    }
}
```

### Dispatching with Context:

```php
public function queuePayment(Request $request, PaymentData $data)
{
    $context = ServiceContext::fromRequest($request);
    
    ProcessPaymentJob::dispatch($data->toArray(), $context);
}
```

### System Operations (No User Context)

For background processes where no user is acting:

```php
use App\Support\ServiceContextHelper;

class GenerateReportsCommand extends Command
{
    public function handle()
    {
        $context = ServiceContextHelper::forSystem(
            companyId: 'company-123',
            idempotencyKey: 'report-generation-' . now()->timestamp
        );
        
        $this->reportService->generateMonthlyReports($context);
    }
}
```

### Immutable Context Updates

ServiceContext is immutable, but provides methods to create modified copies:

```php
$originalContext = ServiceContext::fromRequest($request);

// Create new context with different company
$newContext = $originalContext->withCompanyId('new-company-id');

// Create new context with new idempotency key
$retryContext = $originalContext->withIdempotencyKey('retry-' . now()->timestamp);
```

## Migration from auth()

### Before (auth() usage)

```php
// Service method
public function createPayment(Company $company, Customer $customer, Money $amount): Payment
{
    $payment = new Payment([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'amount' => $amount->getAmount(),
        'created_by' => auth()->id(), // ❌ Global state dependency
    ]);
    
    $payment->save();
    
    // Audit logging
    $this->logAudit('payment.create', [
        'payment_id' => $payment->id,
        'user_id' => auth()->id(), // ❌ Another auth() call
    ]);
    
    return $payment;
}
```

### After (ServiceContext usage)

```php
// Service method
public function createPayment(
    Company $company, 
    Customer $customer, 
    Money $amount,
    ServiceContext $context
): Payment
{
    $payment = new Payment([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'amount' => $amount->getAmount(),
        'created_by' => $context->getActingUser()?->id, // ✅ Explicit context
    ]);
    
    $payment->save();
    
    // Audit logging
    $this->logAudit('payment.create', [
        'payment_id' => $payment->id,
        'user_id' => $context->getActingUser()?->id,
        'company_id' => $context->getCompanyId(),
        'idempotency_key' => $context->getIdempotencyKey(),
    ]);
    
    return $payment;
}
```

### Migration Steps

1. **Add ServiceContext parameter** to service methods:
   ```php
   // Old: public function createPayment(Company $company, Customer $customer, Money $amount): Payment
   // New: public function createPayment(Company $company, Customer $customer, Money $amount, ServiceContext $context): Payment
   ```

2. **Replace auth() calls** with context methods:
   ```php
   // auth()->user() → $context->getActingUser()
   // auth()->id() → $context->getActingUser()?->id
   ```

3. **Update controllers** to create and pass context:
   ```php
   $context = ServiceContextHelper::fromRequest($request);
   $result = $service->createPayment($company, $customer, $amount, $context);
   ```

4. **Update queue jobs** to include context:
   ```php
   public function __construct(private readonly ServiceContext $context) {}
   ```

## Testing

### Unit Testing Services

```php
use App\Services\PaymentService;
use App\Support\ServiceContext;
use App\Models\User;

test('payment creation uses ServiceContext correctly', function () {
    // Arrange
    $user = User::factory()->create();
    $context = new ServiceContext($user, 'company-123', 'test-key-456');
    
    $service = new PaymentService();
    
    // Act
    $payment = $service->createPayment(
        company: $company,
        customer: $customer,
        amount: Money::of(100, 'USD'),
        context: $context
    );
    
    // Assert
    expect($payment->created_by)->toBe($user->id);
    
    // Verify audit logging
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'company_id' => 'company-123',
        'idempotency_key' => 'test-key-456',
        'action' => 'payment.create'
    ]);
});
```

### Testing Queue Jobs

```php
use App\Jobs\ProcessPaymentJob;
use App\Support\ServiceContext;

test('queue job preserves ServiceContext', function () {
    // Arrange
    $context = new ServiceContext($user, 'company-123', 'job-key-789');
    $job = new ProcessPaymentJob($paymentData, $context);
    
    // Act
    $job->handle();
    
    // Assert
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'company_id' => 'company-123',
        'idempotency_key' => 'job-key-789'
    ]);
});
```

### Testing Controllers

```php
test('controller creates ServiceContext from request', function () {
    // Arrange
    $user = User::factory()->create();
    $request = Request::create('/payments', 'POST', [
        'amount' => 100,
        'customer_id' => $customer->id,
    ]);
    
    $request->setUserResolver(fn () => $user);
    
    // Act
    $context = ServiceContextHelper::fromRequest($request);
    
    // Assert
    expect($context->getActingUser())->toBe($user)
        ->and($context->getCompanyId())->toBe($user->current_company_id);
});
```

## Best Practices

### 1. Always Pass ServiceContext Explicitly

```php
// ✅ Good - Explicit context
public function processPayment(Payment $payment, ServiceContext $context): Payment
{
    // Processing logic
}

// ❌ Bad - Hidden dependencies
public function processPayment(Payment $payment): Payment
{
    $user = auth()->user(); // Hidden dependency
}
```

### 2. Use Factory Methods for Common Scenarios

```php
// ✅ Good - Use helper methods
$context = ServiceContext::fromRequest($request);
$context = ServiceContext::forSystem($companyId);
$context = ServiceContext::forUser($user, $companyId);

// ❌ Bad - Manual construction
$context = new ServiceContext(
    actingUser: $request->user(),
    companyId: $request->user()?->current_company_id,
    idempotencyKey: $request->header('Idempotency-Key')
);
```

### 3. Handle Null User Gracefully

```php
// ✅ Good - Handle system operations
public function processPayment(Payment $payment, ServiceContext $context): Payment
{
    $userId = $context->getActingUser()?->id;
    
    // Works for both user and system contexts
    $this->log('Processing payment', [
        'user_id' => $userId,
        'is_system' => $userId === null
    ]);
}

// ❌ Bad - Assumes user exists
public function processPayment(Payment $payment, ServiceContext $context): Payment
{
    $userId = $context->getActingUser()->id; // ❌ Could be null
}
```

### 4. Use Descriptive Idempotency Keys

```php
// ✅ Good - Descriptive keys
$context = ServiceContext::fromRequest($request)->withIdempotencyKey(
    'payment-' . $paymentId . '-process-' . now()->timestamp
);

// ❌ Bad - Generic keys
$context = ServiceContext::fromRequest($request)->withIdempotencyKey('key-123');
```

### 5. Prefer Context Over Global State

```php
// ✅ Good - Use context
public function logAction(string $action, array $data, ServiceContext $context): void
{
    $this->auditLog->create([
        'action' => $action,
        'user_id' => $context->getActingUser()?->id,
        'company_id' => $context->getCompanyId(),
        'idempotency_key' => $context->getIdempotencyKey(),
        'data' => $data
    ]);
}

// ❌ Bad - Use global state
public function logAction(string $action, array $data): void
{
    $this->auditLog->create([
        'action' => $action,
        'user_id' => auth()->id(), // ❌ Global state
        'company_id' => auth()->user()->current_company_id, // ❌ Global state
        'data' => $data
    ]);
}
```

## API Reference

### ServiceContext Class

#### Constructor
```php
public function __construct(
    private readonly ?User $actingUser = null,
    private readonly ?string $companyId = null,
    private readonly ?string $idempotencyKey = null
)
```

#### Methods
```php
// Get acting user
public function getActingUser(): ?User

// Get company ID
public function getCompanyId(): ?string

// Get idempotency key
public function getIdempotencyKey(): ?string

// Create new context with different company
public function withCompanyId(?string $companyId): static

// Create new context with different idempotency key
public function withIdempotencyKey(?string $idempotencyKey): static

// Static factory methods
public static function forUser(?User $user, ?string $companyId = null, ?string $idempotencyKey = null): static
public static function forSystem(?string $companyId = null, ?string $idempotencyKey = null): static
```

### ServiceContextHelper Class

#### Static Methods
```php
// Create context from HTTP request
public static function fromRequest(Request $request, ?string $companyId = null): ServiceContext

// Create context for system operations
public static function forSystem(string $companyId, ?string $idempotencyKey = null): ServiceContext

// Create context for specific user
public static function forUser(User $user, ?string $companyId = null, ?string $idempotencyKey = null): ServiceContext

// Create context for background jobs
public static function forJob(?string $userId = null, ?string $companyId = null, ?string $idempotencyKey = null): ServiceContext
```

### ServiceContext Facade

#### Static Methods (proxied to ServiceContextHelper)
```php
// All ServiceContextHelper methods available via facade:
ServiceContext::fromRequest($request, $companyId)
ServiceContext::forSystem($companyId, $idempotencyKey)
ServiceContext::forUser($user, $companyId, $idempotencyKey)
ServiceContext::forJob($userId, $companyId, $idempotencyKey)
```

## Examples

### Complete Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Customer;
use App\Models\Company;
use App\Services\PaymentService;
use App\Support\Facades\ServiceContext;
use Brick\Money\Money;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function store(StorePaymentRequest $request)
    {
        $customer = Customer::findOrFail($request->customer_id);
        $company = $request->user()->current_company;
        $amount = Money::ofMinor($request->amount, $request->currency);
        
        // Create context from request
        $context = ServiceContext::fromRequest($request, $company->id);
        
        // Process payment with context
        $payment = $this->paymentService->createPayment(
            company: $company,
            customer: $customer,
            amount: $amount,
            paymentMethod: $request->payment_method,
            context: $context
        );
        
        // Queue additional processing
        ProcessPaymentReceiptJob::dispatch($payment->id, $context);
        
        return response()->json([
            'success' => true,
            'payment' => $payment,
            'audit_trail' => [
                'user_id' => $context->getActingUser()?->id,
                'company_id' => $context->getCompanyId(),
                'idempotency_key' => $context->getIdempotencyKey()
            ]
        ]);
    }
    
    public function show(Request $request, $id)
    {
        // Access context from middleware
        $context = $request->attributes->get('service_context');
        
        $payment = $this->paymentService->getPaymentById($id, $context);
        
        return response()->json([
            'payment' => $payment,
            'context_info' => [
                'acting_user' => $context->getActingUser()?->name,
                'company_id' => $context->getCompanyId(),
                'request_id' => $context->getIdempotencyKey()
            ]
        ]);
    }
}
```

### Queue Job Example

```php
<?php

namespace App\Jobs;

use App\Services\PaymentService;
use App\Support\ServiceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessPaymentReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        private readonly string $paymentId,
        private readonly ServiceContext $context
    ) {}

    public function handle(PaymentService $paymentService)
    {
        // Context contains original user info
        $payment = $paymentService->generateReceipt($this->paymentId, $this->context);
        
        // Send receipt email
        if ($this->context->getActingUser()) {
            $paymentService->sendReceiptEmail($payment, $this->context);
        }
        
        // Log processing with context
        \Log::info('Payment receipt processed', [
            'payment_id' => $this->paymentId,
            'processed_by' => $this->context->getActingUser()?->id ?? 'system',
            'company_id' => $this->context->getCompanyId(),
            'job_id' => $this->job->getJobId()
        ]);
    }
}
```

### Service Method Example

```php
<?php

namespace App\Services;

use App\Models\Payment;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Brick\Money\Money;

class PaymentService
{
    use AuditLogging;

    public function createPayment(
        Company $company,
        Customer $customer,
        Money $amount,
        string $paymentMethod,
        ServiceContext $context
    ): Payment {
        // Create payment record
        $payment = new Payment([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'amount' => $amount->getAmount(),
            'currency_code' => $amount->getCurrency()->getCode(),
            'payment_method' => $paymentMethod,
            'created_by' => $context->getActingUser()?->id,
            'status' => 'pending'
        ]);
        
        $payment->save();
        
        // Audit log with context
        $this->logAudit('payment.create', [
            'payment_id' => $payment->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'amount' => $amount->getAmount(),
            'currency' => $amount->getCurrency()->getCode(),
            'payment_method' => $paymentMethod,
            'user_id' => $context->getActingUser()?->id,
            'idempotency_key' => $context->getIdempotencyKey()
        ], $context);
        
        return $payment;
    }
    
    public function processPayment(Payment $payment, ServiceContext $context): Payment
    {
        // Processing logic...
        
        // Log processing with same context
        $this->logAudit('payment.process', [
            'payment_id' => $payment->id,
            'status' => 'processed'
        ], $context);
        
        return $payment;
    }
}
```

## Troubleshooting

### Common Issues

1. **Missing ServiceContext Parameter**
   ```
   Error: Too few arguments to method, expected ServiceContext
   ```
   **Solution**: Add ServiceContext parameter to service method calls

2. **Null User in System Operations**
   ```
   Error: Call to a member function id() on null
   ```
   **Solution**: Use null-safe operator: `$context->getActingUser()?->id`

3. **Idempotency Key Conflicts**
   ```
   Error: Duplicate idempotency key detected
   ```
   **Solution**: Use unique keys for each operation: `operation-type-id-timestamp`

### Debug Tips

1. **Log Context Information**
   ```php
   Log::debug('ServiceContext', [
       'user_id' => $context->getActingUser()?->id,
       'company_id' => $context->getCompanyId(),
       'idempotency_key' => $context->getIdempotencyKey()
   ]);
   ```

2. **Check Middleware Registration**
   Ensure `AddServiceContextToRequest` middleware is registered in HTTP kernel

3. **Verify Context Creation**
   Use `dd($context)` to inspect context objects during development

## Conclusion

ServiceContext provides a clean, testable way to manage user context throughout your application. By replacing global `auth()` calls with explicit context injection, you gain:

- **Better Testability**: Services can be tested with mock contexts
- **Queue Support**: Jobs can be processed with proper audit trails
- **Clean Architecture**: Explicit dependencies make code more maintainable
- **Enhanced Auditing**: Comprehensive audit logging with idempotency tracking

For further questions or examples, refer to the existing implementation in `/app/Services/PaymentService.php` and the comprehensive test suite in `/app/tests/Unit/Services/PaymentServiceContextTest.php`.
