# Controller Remediation Prompt

## Task: Fix Controller Constitutional Violations

You are a **Laravel Architecture Expert** specialized in controller remediation for command bus-based systems.

## CURRENT VIOLATIONS TO FIX

### **Common Non-Compliant Patterns Found**

#### **1. Direct Service Calls (CRITICAL)**
```php
// BEFORE (VIOLATION)
class CustomerController extends Controller
{
    public function store(Request $request)
    {
        $customer = new CustomerService()->create($request->all()); // ❌ Direct service call
        return response()->json($customer);
    }
}

// AFTER (CONSTITUTIONAL)
class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $context = ServiceContextHelper::fromRequest($request);

        $customer = Bus::dispatch('customers.create', [
            'company_id' => $context->getCompanyId(),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ], $context); // ✅ Command bus dispatch

        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer),
            'message' => 'Customer created successfully'
        ], 201);
    }
}
```

#### **2. Missing FormRequest Validation (CRITICAL)**
```php
// BEFORE (VIOLATION)
class CustomerController extends Controller
{
    public function update(Request $request, $id) // ❌ Direct Request injection
    {
        $data = $request->all(); // ❌ No validation

        // Manual validation in controller
        $request->validate([
            'name' => 'required|max:255',
            'email' => 'email'
        ]);

        $customer = Customer::find($id);
        $customer->update($data);

        return response()->json($customer);
    }
}

// AFTER (CONSTITUTIONAL)
class CustomerController extends Controller
{
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        // ✅ FormRequest handles validation and authorization
        $context = ServiceContextHelper::fromRequest($request);

        $customer = Bus::dispatch('customers.update', [
            'id' => $id,
            'data' => $request->validated(), // ✅ Validated data
        ], $context);

        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer),
            'message' => 'Customer updated successfully'
        ]);
    }
}
```

#### **3. Business Logic in Controllers (CRITICAL)**
```php
// BEFORE (VIOLATION)
class CustomerController extends Controller
{
    public function delete($id)
    {
        $customer = Customer::find($id);

        // ❌ Business logic in controller
        if ($customer->invoices()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete customer with invoices'
            ], 422);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}

// AFTER (CONSTITUTIONAL)
class CustomerController extends Controller
{
    public function destroy(DestroyCustomerRequest $request, string $id): JsonResponse
    {
        $context = ServiceContextHelper::fromRequest($request);

        try {
            Bus::dispatch('customers.delete', [
                'id' => $id,
            ], $context); // ✅ Business logic in service layer

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);

        } catch (CustomerHasInvoicesException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with invoices',
                'errors' => ['invoices' => [$e->getMessage()]]
            ], 422);
        }
    }
}
```

#### **4. Non-Standard API Responses (CRITICAL)**
```php
// BEFORE (VIOLATION)
class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::all(); // ❌ No pagination or company context
        return $customers; // ❌ Not wrapped in standard response format
    }

    public function store(Request $request)
    {
        try {
            $customer = new Customer();
            $customer->name = $request->name;
            $customer->save();

            return $customer; // ❌ No success/message structure

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() // ❌ Different error format
            ], 500);
        }
    }
}

// AFTER (CONSTITUTIONAL)
class CustomerController extends Controller
{
    public function index(IndexCustomersRequest $request): JsonResponse
    {
        $context = ServiceContextHelper::fromRequest($request);

        $customers = Bus::dispatch('customers.list', [
            'filters' => $request->validated(),
            'pagination' => [
                'page' => $request->input('page', 1),
                'per_page' => $request->input('per_page', 20)
            ]
        ], $context);

        return response()->json([
            'success' => true,
            'data' => CustomerResource::collection($customers->items()),
            'meta' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage()
            ],
            'message' => 'Customers retrieved successfully'
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $customer = Bus::dispatch('customers.create', $request->validated(), $context);

            return response()->json([
                'success' => true,
                'data' => new CustomerResource($customer),
                'message' => 'Customer created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Customer creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'errors' => config('app.debug') ? [$e->getMessage()] : []
            ], 500);
        }
    }
}
```

## COMPLETE CONTROLLER TEMPLATE

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\IndexCustomersRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Requests\Customers\DestroyCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Services\ServiceContextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct()
    {
        // Apply middleware for authentication and basic security
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:60,1'); // Rate limiting
    }

    public function index(IndexCustomersRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $result = Bus::dispatch('customers.list', [
                'company_id' => $context->getCompanyId(),
                'filters' => $request->validated('filters', []),
                'pagination' => [
                    'page' => $request->validated('page', 1),
                    'per_page' => $request->validated('per_page', 20)
                ]
            ], $context);

            return response()->json([
                'success' => true,
                'data' => CustomerResource::collection($result['data']),
                'meta' => $result['meta'],
                'message' => 'Customers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Customer listing failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId() ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customers'
            ], 500);
        }
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $customer = Bus::dispatch('customers.create', [
                'company_id' => $context->getCompanyId(),
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'tax_id' => $request->validated('tax_id'),
                'credit_limit' => $request->validated('credit_limit', 0),
            ], $context);

            return response()->json([
                'success' => true,
                'data' => new CustomerResource($customer),
                'message' => 'Customer created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (InsufficientCreditException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credit limit validation failed',
                'errors' => ['credit_limit' => [$e->getMessage()]]
            ], 422);

        } catch (\Exception $e) {
            Log::error('Customer creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'errors' => config('app.debug') ? [$e->getMessage()] : []
            ], 500);
        }
    }

    public function show(ShowCustomerRequest $request, string $id): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $customer = Bus::dispatch('customers.show', [
                'id' => $id,
                'company_id' => $context->getCompanyId(),
                'include' => $request->validated('include', [])
            ], $context);

            return response()->json([
                'success' => true,
                'data' => new CustomerResource($customer),
                'message' => 'Customer retrieved successfully'
            ]);

        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Customer retrieval failed', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer'
            ], 500);
        }
    }

    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $customer = Bus::dispatch('customers.update', [
                'id' => $id,
                'company_id' => $context->getCompanyId(),
                'data' => $request->validated()
            ], $context);

            return response()->json([
                'success' => true,
                'data' => new CustomerResource($customer),
                'message' => 'Customer updated successfully'
            ]);

        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Customer update failed', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'errors' => config('app.debug') ? [$e->getMessage()] : []
            ], 500);
        }
    }

    public function destroy(DestroyCustomerRequest $request, string $id): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            Bus::dispatch('customers.delete', [
                'id' => $id,
                'company_id' => $context->getCompanyId()
            ], $context);

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);

        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (CustomerHasInvoicesException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with existing invoices',
                'errors' => ['invoices' => [$e->getMessage()]]
            ], 422);

        } catch (\Exception $e) {
            Log::error('Customer deletion failed', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'errors' => config('app.debug') ? [$e->getMessage()] : []
            ], 500);
        }
    }
}
```

## REQUIRED FORMREQUEST CLASSES

### **StoreCustomerRequest.php**
```php
<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                Rule::unique('acct.customers', 'email')->where(function ($query) {
                    $query->where('company_id', $this->user()->currentCompany()->id);
                })
            ],
            'tax_id' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
            'name.max' => 'Customer name cannot exceed 255 characters',
            'email.unique' => 'A customer with this email already exists in your company',
            'credit_limit.max' => 'Credit limit cannot exceed 99,999,999.99',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }
}
```

### **UpdateCustomerRequest.php**
```php
<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                Rule::unique('acct.customers', 'email')->where(function ($query) {
                    $query->where('company_id', $this->user()->currentCompany()->id);
                })->ignore($this->route('customer')),
            ],
            'tax_id' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }
}
```

## CHECKLIST FOR EVERY CONTROLLER

### **✅ Must Include:**
- [ ] Only FormRequest injection (no direct Request)
- [ ] ServiceContext creation via `ServiceContextHelper::fromRequest()`
- [ ] All write operations via `Bus::dispatch()`
- [ ] Standard JSON response format `{success, data, message}`
- [ ] Proper HTTP status codes
- [ ] Comprehensive error handling with try-catch
- [ ] Authorization checks via FormRequest
- [ ] Log errors with context information
- [ ] Return proper JSON error responses
- [ ] No business logic in controller methods
- [ ] Use API Resources for data formatting

### **❌ Must NOT Include:**
- [ ] Direct service calls (`new Service()`)
- [ ] Business logic validation in controller
- [ ] Direct model access
- [ ] Manual request validation
- [ ] Non-standard response formats
- [ ] Database queries in controller
- [ ] Hardcoded error messages

## VALIDATION COMMANDS

```bash
# Test controller endpoints
curl -X POST http://localhost/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name":"Test Customer","email":"test@example.com"}'

# Test validation errors
curl -X POST http://localhost/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name":""}' # Should return validation error

# Test command bus integration
php artisan tinker
> Bus::dispatch('customers.create', ['name' => 'Test'], ServiceContext::forTesting());
```

Apply this template to ALL controllers in your codebase.