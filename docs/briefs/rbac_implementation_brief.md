# RBAC Implementation Brief

**Version**: 2.0  
**Last Updated**: 2025-11-18  
**Status**: Complete Implementation Guide

---

## 🎯 Overview

This document provides comprehensive implementation guidance for Role-Based Access Control (RBAC) in the Haasib application. It addresses the common 403 permission errors and establishes standardized patterns for authorization throughout the system.

---

## 🔐 Core RBAC Components

### 1. **Permission Constants** (`app/Constants/Permissions.php`)

All permissions follow the standardized naming pattern: `{module}.{resource}.{action}`

```php
// Examples
Permissions::ACCT_CUSTOMERS_VIEW      // acct.customers.view
Permissions::ACCT_INVOICES_CREATE     // acct.invoices.create  
Permissions::LEDGER_ENTRIES_POST      // ledger.entries.post
Permissions::COMPANIES_MANAGE_USERS   // companies.manage_users
```

### 2. **Permission Seeder** (`database/seeders/PermissionSeeder.php`)

- Creates all standardized permissions
- Sets up role hierarchy (super_admin, company_admin, accounting_manager, accounting_clerk, viewer)
- Assigns appropriate permissions to each role

### 3. **BaseFormRequest Authorization** (`app/Http/Requests/BaseFormRequest.php`)

Enhanced with standardized authorization helpers:

```php
// Standard authorization patterns
$this->authorizeCustomerOperation('create');
$this->authorizeInvoiceOperation('update'); 
$this->authorizePaymentOperation('void');
$this->authorizeCompanyOperation('manage_users');
```

---

## 🚨 Fixing 403 Permission Errors

### **Root Causes of 403 Errors**

1. **Missing Authorization in FormRequests**
2. **Inconsistent Permission Naming**
3. **Missing RLS Context Validation**
4. **Incorrect Permission Checks**

### **Solution Pattern**

#### ✅ **CORRECT FormRequest Authorization**

```php
<?php

use App\Constants\Permissions;

class CreateCustomerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Use standardized authorization helper
        return $this->authorizeCustomerOperation('create');
        
        // OR manual pattern
        return $this->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_CREATE) &&
               $this->validateRlsContext();
    }
}
```

#### ❌ **INCORRECT Patterns (Causes 403s)**

```php
// Missing authorization method
public function authorize(): bool
{
    return true; // Always allows - security risk
}

// Wrong permission names
return $this->hasCompanyPermission('customer.create'); // Wrong format

// Missing RLS validation  
return $this->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_CREATE); // Missing RLS
```

---

## 📋 Implementation Checklist

### **For Each FormRequest Class**

- [ ] **Import Permissions**: `use App\Constants\Permissions;`
- [ ] **Extend BaseFormRequest**: `extends BaseFormRequest`
- [ ] **Implement authorize()**: Use standardized helpers
- [ ] **Include RLS Validation**: Always validate company context
- [ ] **Test Permission Boundaries**: Ensure 403s when unauthorized

### **Example Implementation**

```php
<?php

namespace App\Http\Requests;

use App\Constants\Permissions;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Standard pattern - fixes 403 errors
        return $this->authorizeInvoiceOperation('update');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date', 'after:today'],
            // ... other validation rules
        ];
    }
}
```

---

## 🎭 Role Definitions

### **super_admin**
- **Purpose**: System-wide administration
- **Access**: All permissions across all companies
- **Use Case**: Platform administrators

### **company_admin** 
- **Purpose**: Full company management
- **Access**: All company-specific permissions
- **Use Case**: Business owners, senior managers

### **accounting_manager**
- **Purpose**: Financial oversight and management
- **Access**: Full accounting module + reporting + audit
- **Use Case**: CFOs, accounting managers

### **accounting_clerk**
- **Purpose**: Day-to-day accounting operations  
- **Access**: Data entry, basic accounting functions
- **Use Case**: Bookkeepers, accounting staff

### **viewer**
- **Purpose**: Read-only access
- **Access**: View permissions only
- **Use Case**: Stakeholders, external accountants

---

## 🔧 Frontend Permission Integration

### **Controller Data Passing**

```php
return Inertia::render('Customers/Index', [
    'customers' => $customers,
    'can' => [
        'customers_create' => $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_CREATE),
        'customers_update' => $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_UPDATE),
        'customers_delete' => $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_DELETE),
    ],
]);
```

### **Vue Component Usage**

```vue
<template>
  <div>
    <!-- Conditional rendering based on permissions -->
    <Button 
      v-if="can.customers_create" 
      @click="createCustomer"
      label="Add Customer" 
    />
    
    <Button 
      v-if="can.customers_update" 
      @click="editCustomer"
      label="Edit" 
    />
  </div>
</template>

<script setup>
const props = defineProps({
  customers: Object,
  can: Object, // Permission flags
})
</script>
```

---

## 🧪 Testing RBAC Implementation

### **Permission Boundary Tests**

```php
// Test unauthorized access
test('cannot create customer without permission', function () {
    $user = User::factory()->create();
    // Don't assign permission
    
    $this->actingAs($user)
         ->postJson('/customers', $customerData)
         ->assertStatus(403);
});

// Test authorized access
test('can create customer with permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::ACCT_CUSTOMERS_CREATE);
    
    $this->actingAs($user)
         ->postJson('/customers', $customerData)
         ->assertStatus(201);
});
```

### **RLS Context Tests**

```php
test('cannot access other company data', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();
    $user = User::factory()->create();
    
    // User belongs to company1
    $user->companies()->attach($company1);
    
    // Try to access company2 data
    $this->actingAs($user)
         ->withSession(['current_company_id' => $company2->id])
         ->getJson('/customers')
         ->assertStatus(403);
});
```

---

## 📈 Performance Considerations

### **Permission Caching**
- Permissions are cached by Spatie package
- Clear cache after role/permission changes: `php artisan permission:cache-reset`

### **Database Optimization**
- RLS policies use indexes on `company_id`
- Permission checks are optimized with proper foreign keys

### **Frontend Optimization**
- Pass permission flags in initial page props
- Avoid API calls to check permissions in components

---

## 🔍 Debugging Permission Issues

### **Common Debug Steps**

1. **Check User Permissions**:
   ```php
   $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_CREATE); // Should return true
   ```

2. **Verify Company Context**:
   ```php
   session('current_company_id'); // Should match user's company
   ```

3. **Test RLS Policies**:
   ```sql
   SELECT * FROM acct.customers WHERE company_id = 'current-company-id';
   ```

4. **Check FormRequest Authorization**:
   - Ensure `authorize()` method exists and returns `true`
   - Verify correct permission constants are used

### **Debug Helper Commands**

```bash
# Check user permissions
php artisan tinker
>>> User::find(1)->getAllPermissions()

# Reset permission cache  
php artisan permission:cache-reset

# Seed permissions
php artisan db:seed --class=PermissionSeeder
```

---

## 🚀 Migration Integration

The RBAC system integrates with the migration plan:

1. **Phase 1**: Copy permission files first
2. **Phase 2**: Run permission seeder 
3. **Phase 3**: Update all FormRequests to use new patterns
4. **Phase 4**: Test permission boundaries
5. **Phase 5**: Update frontend components with permission checks

---

## 📚 Quick Reference

### **Permission Naming Pattern**
- System: `system.admin`, `system.audit`  
- Company: `companies.view`, `companies.create`
- Accounting: `acct.customers.view`, `acct.invoices.create`
- Ledger: `ledger.entries.post`, `ledger.period_close.execute`

### **Authorization Helpers**
- `$this->authorizeCustomerOperation('create')`
- `$this->authorizeInvoiceOperation('update')`  
- `$this->authorizePaymentOperation('void')`
- `$this->validateRlsContext()`

### **Role Hierarchy**
1. `super_admin` (full system access)
2. `company_admin` (full company access)  
3. `accounting_manager` (financial oversight)
4. `accounting_clerk` (data entry)
5. `viewer` (read-only)

---

**Status**: ✅ **Implementation Complete - Ready for Migration**