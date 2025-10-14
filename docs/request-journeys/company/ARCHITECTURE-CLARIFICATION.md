# Company API Architecture Clarification

## Current Implementation Status

After analyzing the codebase, there's a **mixed approach** to company operations:

### Using Command Pattern (via `/commands`)
These operations use the CommandController with `X-Action` headers:

1. **Company Creation** - `company.create`
   - Route: `POST /commands`
   - Action: `App\Actions\DevOps\CompanyCreate`
   - Headers: `X-Action: company.create`

2. **Company Deletion** - `company.delete`
   - Route: `POST /commands`
   - Action: `App\Actions\DevOps\CompanyDelete`
   - Headers: `X-Action: company.delete`

3. **Company Assignment** - `company.assign`
   - Route: `POST /commands`
   - Action: `App\Actions\DevOps\CompanyAssign`

4. **Company Unassignment** - `company.unassign`
   - Route: `POST /commands`
   - Action: `App\Actions\DevOps\CompanyUnassign`

### Using Direct Routes
These operations use direct controller methods:

1. **Company Activation** 
   - Route: `PATCH /web/companies/{company}/activate`
   - Controller: `CompanyController@activate`

2. **Company Deactivation**
   - Route: `PATCH /web/companies/{company}/deactivate`
   - Controller: `CompanyController@deactivate`

3. **Company Deletion** (Alternative)
   - Route: `DELETE /web/companies/{company}`
   - Controller: `CompanyController@destroy`

## Why the Mixed Approach?

### Command Pattern Benefits
1. **Idempotency**: Automatic duplicate request prevention
2. **Audit Logging**: Built-in audit trail for all commands
3. **Centralized Authorization**: Via `Gate::authorize('command.execute')`
4. **Consistent Error Handling**: Standardized response format

### Direct Route Benefits
1. **Simplicity**: Direct controller methods
2. **RESTful**: Follows REST conventions
3. **Browser Compatibility**: Works with regular form submissions
4. **Route Model Binding**: Automatic model resolution

## Recommendations

### Option 1: Full Command Pattern (Recommended)
Move all operations to use the command pattern:

```php
// routes/web.php
Route::post('/commands', [CommandController::class, 'execute']);
// Remove direct routes

// Frontend
this.$http.post('/commands', {
    command: 'company.activate',
    payload: { company: company.id }
});
```

### Option 2: Full Direct Routes
Move all operations to direct routes:

```php
// routes/web.php
Route::post('/companies', [CompanyController::class, 'store']);
Route::patch('/companies/{company}/activate', [CompanyController::class, 'activate']);
Route::patch('/companies/{company}/deactivate', [CompanyController::class, 'deactivate']);
Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
```

### Option 3: Hybrid with Clear Rules
Keep the mixed approach but define clear rules:

**Use Command Pattern For:**
- Operations that need idempotency (creation, deletion)
- Operations that need audit logging
- Complex business logic operations

**Use Direct Routes For:**
- Simple state toggles (activate/deactivate)
- Operations that benefit from route model binding
- Public-facing operations

## Current Frontend Implementation

The frontend should handle both patterns:

```javascript
// For command operations
async executeCommand(command, payload) {
    return await this.$http.post('/commands', {
        command,
        payload
    }, {
        headers: {
            'X-Action': command,
            'X-Idempotency-Key': generateUUID()
        }
    });
}

// For direct routes
async activateCompany(companyId) {
    return await this.$http.patch(`/web/companies/${companyId}/activate`);
}
```

## Decision Factors

1. **Team Preference**: Which pattern is easier for your team?
2. **Consistency Needs**: How important is consistent API design?
3. **Audit Requirements**: Do all operations need audit logging?
4. **Idempotency Needs**: Which operations must be idempotent?

## Example: Migrating to Full Command Pattern

If choosing Option 1, here's how to migrate:

### 1. Add Missing Commands
```php
// config/command-bus.php
return [
    // Existing commands...
    'company.activate' => App\Actions\Company\ActivateCompany::class,
    'company.deactivate' => App\Actions\Company\DeactivateCompany::class,
];
```

### 2. Update Frontend
```javascript
// Replace all direct API calls with command pattern
// Before:
await this.$http.patch(`/web/companies/${id}/activate`);

// After:
await this.executeCommand('company.activate', { company: id });
```

### 3. Remove Direct Routes
Clean up routes file to only have the command endpoint.