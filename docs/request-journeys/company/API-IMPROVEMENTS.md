# API & Authorization Improvements

This document outlines recommended improvements to standardize and enhance the company management API implementation.

## 1. Command Pattern Standardization

### Current Issue
Company creation uses the command pattern (`POST /commands` with `X-Action` header), but other operations use direct routes.

### Recommended Implementation
All company operations should use the command pattern for consistency:

### Updated Routes
```php
// routes/web.php
Route::middleware('auth')->group(function () {
    // Company operations via commands
    Route::post('/commands', [CommandController::class, 'execute']);
    
    // Direct routes still exist for backward compatibility
    Route::patch('/web/companies/{company}/activate', [CompanyController::class, 'activate']);
    Route::patch('/web/companies/{company}/deactivate', [CompanyController::class, 'deactivate']);
    Route::delete('/web/companies/{company}', [CompanyController::class, 'destroy']);
});
```

### Frontend Implementation
```vue
// resources/js/Pages/Admin/Companies/Index.vue
methods: {
    async activateCompany(company) {
        await this.$http.post('/commands', {
            command: 'company.activate',
            payload: { company: company.id }
        });
    },
    
    async deactivateCompany(company) {
        await this.$http.post('/commands', {
            command: 'company.deactivate',
            payload: { company: company.id }
        });
    },
    
    async deleteCompany(company) {
        await this.$http.post('/commands', {
            command: 'company.delete',
            payload: { company: company.id }
        });
    }
}
```

## 2. Authorization with Policies

### Current Implementation
Authorization checks are scattered in controllers:
```php
// Current approach in CompanyController
public function activate(string $company)
{
    abort_unless($user->isSuperAdmin(), 403);
    // ...
}
```

### Recommended Policy Implementation

### Create CompanyPolicy
```php
// app/Policies/CompanyPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Company;

class CompanyPolicy
{
    public function create(User $user)
    {
        return $user->isSuperAdmin();
    }
    
    public function activate(User $user, Company $company)
    {
        return $user->isSuperAdmin();
    }
    
    public function deactivate(User $user, Company $company)
    {
        return $user->isSuperAdmin();
    }
    
    public function delete(User $user, Company $company)
    {
        return $user->isSuperAdmin();
    }
    
    public function manageUsers(User $user, Company $company)
    {
        return $user->isSuperAdmin() || 
               $company->users()->where('user_id', $user->id)->where('role', 'owner')->exists();
    }
}
```

### Register Policy
```php
// app/Providers/AuthServiceProvider.php
protected $policies = [
    Company::class => CompanyPolicy::class,
];
```

### Update Controller to Use Policy
```php
// app/Http/Controllers/CompanyController.php
public function activate(Company $company)
{
    $this->authorize('activate', $company);
    
    $company->activate();
    
    return response()->json([
        'message' => 'Company activated successfully'
    ]);
}
```

## 3. Simplified Client-Side Validation

### Current Implementation
Manual validation in Vue components:
```javascript
// Current approach
if (!this.formData.name) {
    this.errors.name = 'Name is required';
    return;
}
```

### Recommended Implementation
Remove manual validation, rely on Inertia's error handling:

```vue
<!-- resources/js/Pages/Admin/Companies/Create.vue -->
<template>
    <form @submit.prevent="submit">
        <div>
            <label>Name</label>
            <input v-model="form.name" type="text">
            <div v-if="form.errors.name" class="error">
                {{ form.errors.name }}
            </div>
        </div>
        
        <div>
            <label>Base Currency</label>
            <select v-model="form.base_currency">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
            </select>
            <div v-if="form.errors.base_currency" class="error">
                {{ form.errors.base_currency }}
            </div>
        </div>
        
        <button type="submit" :disabled="form.processing">
            Create Company
        </button>
    </form>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    setup() {
        const form = useForm({
            name: '',
            base_currency: 'AED',
            language: 'en',
            locale: 'en-US',
            settings: {}
        });
        
        function submit() {
            form.post('/commands', {
                data: {
                    command: 'company.create',
                    payload: form.data()
                },
                onSuccess: () => {
                    // Handle success
                }
            });
        }
        
        return { form, submit };
    }
}
</script>
```

## 4. Consistent Company Context Handling

### Current Implementation
Multiple ways to get company context:
```php
// Current approach in InvoiceApiController
private function company(Request $request): Company
{
    $companyId = $request->input('current_company_id')
        ?? $request->header('X-Company-Id')
        ?? $request->user()?->current_company_id;
    return Company::findOrFail($companyId);
}
```

### Recommended Middleware Implementation

### Create/Set Company Context Middleware
```php
// app/Http/Middleware/SetCompanyContext.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        // Get company from various sources in order of preference
        $companyId = $request->header('X-Company-Id')
            ?? $request->input('company_id')
            ?? $request->user()?->current_company_id;
            
        if ($companyId) {
            $company = \App\Models\Company::find($companyId);
            if ($company) {
                // Add company to request
                $request->merge(['company' => $company]);
                
                // Set tenant context
                tenancy()->initialize($company);
            }
        }
        
        return $next($request);
    }
}
```

### Register Middleware
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SetCompanyContext::class,
    ]);
})
```

### Simplified Controller Methods
```php
// app/Http/Controllers/Api/InvoiceApiController.php
private function company(Request $request): Company
{
    // Company is now set by middleware
    return $request->company 
        ?? throw new \Exception('Company context not found');
}

// Or even better - use route model binding
public function index(Company $company)
{
    // Company is automatically injected and authorized
    $this->authorize('view', $company);
    
    return Invoice::where('company_id', $company->id)->get();
}
```

## 5. Enhanced API Documentation

### Updated Company Creation Flow
```
Frontend → POST /commands → CommandController → CompanyStoreRequest → CompanyPolicy → Company Model → Database
```

### Standardized Request Format
```json
{
    "command": "company.{action}",
    "payload": {
        // Action-specific data
    },
    "meta": {
        "idempotency_key": "uuid"
    }
}
```

### Standardized Response Format
```json
{
    "success": true,
    "data": {
        // Response data
    },
    "meta": {
        "timestamp": "2024-01-15T10:30:00Z",
        "request_id": "uuid"
    },
    "errors": null
}
```

## 6. Implementation Benefits

### 1. Consistency
- All operations use the same pattern
- Uniform request/response format
- Standardized error handling

### 2. Maintainability
- Authorization logic centralized in policies
- Business logic in action classes
- Reusable middleware

### 3. Testability
- Policies can be unit tested
- Actions can be tested independently
- Middleware can be mocked

### 4. Security
- Consistent authorization checks
- Proper scoping of queries
- Audit trail through middleware

### 5. Performance
- Middleware runs once per request
- Route model binding optimization
- Efficient database queries