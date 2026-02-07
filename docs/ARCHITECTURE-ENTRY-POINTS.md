# Haasib Entry Points & Feature Map

**Route hierarchy, navigation structure, and how to navigate the application.**

---

## Quick Navigation

1. **Public routes (no auth)** → [Public Routes](#public-routes)
2. **Authenticated routes** → [Authenticated Routes](#authenticated-routes)
3. **Company-scoped routes** → [Company-Scoped Routes](#company-scoped-routes)
4. **Feature modules** → [Feature Module Tree](#feature-module-tree)
5. **Navigation registry** → [Frontend Navigation](#frontend-navigation)

---

## Route Hierarchy

```
┌─ PUBLIC (no auth required)
│  ├─ GET /                                 # Landing page
│  ├─ GET /invite/{token}                   # Accept company invitation
│  ├─ POST /invite/{token}                  # Process invitation
│  └─ POST /forgot-password                 # Fortify: Password reset email
│
├─ AUTH ROUTES (Fortify - /login, /register, etc.)
│  ├─ GET /login                            # Login form
│  ├─ POST /login                           # Process login
│  ├─ GET /register                         # Registration form
│  ├─ POST /register                        # Create account
│  ├─ GET /forgot-password                  # Password reset form
│  ├─ POST /forgot-password                 # Send reset email
│  ├─ GET /reset-password/{token}           # Password reset form
│  ├─ POST /reset-password                  # Update password
│  ├─ GET /verify-email                     # Email verification form
│  └─ POST /verify-email                    # Send verification email
│
├─ AUTHENTICATED GLOBAL (requires auth, no company)
│  ├─ GET /dashboard                        # Global dashboard
│  ├─ GET /companies                        # Company list + create form
│  ├─ POST /companies                       # Create new company
│  └─ GET /settings                         # User settings
│
└─ COMPANY-SCOPED (requires auth + company access)
   ├─ GET /{company}                        # Company dashboard
   ├─ GET /{company}/invoices               # Invoice list
   ├─ POST /{company}/invoices              # Create invoice
   ├─ GET /{company}/invoices/{id}          # View invoice
   ├─ PUT /{company}/invoices/{id}          # Update invoice
   ├─ DELETE /{company}/invoices/{id}       # Delete invoice
   ├─ POST /{company}/invoices/{id}/approve # Approve invoice (workflow)
   ├─ POST /{company}/invoices/{id}/void    # Void invoice (workflow)
   └─ ... (30+ more company-scoped routes)
```

---

## Public Routes

These routes require **no authentication**.

### Landing Page
```
Route: GET /
Controller: None (static/blade)
Purpose: Welcome page / marketing
Redirects:
  - If authenticated → /companies (company selector)
  - If not → Login form
```

### Company Invitation Flow
```
Route: GET /invite/{token}
Controller: InvitationController@show
Purpose: Display invitation acceptance form
Query Params:
  - email: Pre-filled email (optional)
Response: Inertia 'Invitation/Accept' page
  - Props: { token, email }

Route: POST /invite/{token}
Controller: InvitationController@store
Request: FormRequest validation
  - email (required, email format)
  - password (required, min 8)
Body: { "email": "user@example.com", "password": "..." }
Response: Redirects to /companies (creates user + company membership)
Errors: Invalid token, expired token, email mismatch
```

---

## Authenticated Routes (No Company Required)

These routes require **authentication** but work globally across all companies.

### User Authentication (Fortify)

All handled by Laravel Fortify middleware/routes.

```
GET  /login                    # Show login form
POST /login                    # Process login
POST /logout                   # Process logout
GET  /register                 # Show registration form
POST /register                 # Create account
GET  /forgot-password          # Password reset request form
POST /forgot-password          # Send reset email
GET  /reset-password/{token}   # Password reset form
POST /reset-password           # Update password
GET  /verify-email             # Email verification form
POST /verify-email             # Resend verification email
```

**Location:** Handled by `laravel/fortify` package, routes auto-registered in `bootstrap/app.php`

### Company Selection & Management
```
Route: GET /companies
Controller: CompanyController@index
Purpose: List user's companies + create new company form
Response: Inertia 'Companies/Index' page
  - Props: {
      companies: [{ id, name, owner_id, ... }],
      canCreate: bool
    }
Display: List of companies + "Create New" button

Route: POST /companies
Controller: CompanyController@store
FormRequest: StoreCompanyRequest
  - name (required, string, max 255)
  - industry (optional, enum)
Purpose: Create new company
Response: Redirect to /{company}/onboarding
Workflow: Creates company → Adds user as owner → Starts onboarding flow

Route: GET /{company}/onboarding
Controller: CompanyOnboardingController@index
Purpose: First-time setup (accounting defaults, tax config, etc.)
Response: Inertia 'Company/Onboarding' page
```

### User Settings
```
Route: GET /settings
Controller: SettingController@index
Purpose: User account settings (profile, password, 2FA, etc.)
Response: Inertia 'Settings/Index' page
  - Props: { user: { email, name, ... } }
```

---

## Company-Scoped Routes

**URL Pattern:** `/{company}/{resource}` or `/{company}/{resource}/{id}`

**Middleware Chain:**
1. `auth` - User authenticated
2. `identify.company` - Company extracted from URL, user validated to access
3. `HandleInertiaRequests` - Props injected
4. `CheckFirstTimeUser` - First-time flow check

**Company ID Format:** UUID (URL-friendly, from database)

### Route Structure

```php
// From build/routes/web.php
Route::middleware(['auth', 'identify.company'])->prefix('{company}')->group(function () {
    // All routes here have {company} in URL
    // Company context automatically available via app(CurrentCompany::class)->get()
});
```

### Company Dashboard
```
Route: GET /{company}
Controller: DashboardController@index
Purpose: Dashboard overview
Response: Inertia 'Dashboard' page
  - Props: {
      company: { id, name, ... },
      metrics: { invoices_pending, revenue_ytd, ... },
      recentInvoices: [...],
      ...
    }
Display: KPIs, recent transactions, charts
```

### Core Features (Company-Scoped)

#### Invoices (AR)
```
LIST:  GET    /{company}/invoices
CREATE: GET   /{company}/invoices/create
STORE: POST   /{company}/invoices
VIEW:  GET    /{company}/invoices/{invoice}
EDIT:  GET    /{company}/invoices/{invoice}/edit
UPDATE: PUT   /{company}/invoices/{invoice}
DELETE: DELETE/{company}/invoices/{invoice}

WORKFLOW ACTIONS:
POST   /{company}/invoices/{invoice}/approve
POST   /{company}/invoices/{invoice}/void
POST   /{company}/invoices/{invoice}/duplicate
POST   /{company}/invoices/{invoice}/send-email

FormRequest: StoreInvoiceRequest, UpdateInvoiceRequest
Permissions: invoices.view, invoices.create, invoices.approve, invoices.void
```

#### Customers (AR)
```
LIST:  GET    /{company}/customers
CREATE: GET   /{company}/customers/create
STORE: POST   /{company}/customers
VIEW:  GET    /{company}/customers/{customer}
EDIT:  GET    /{company}/customers/{customer}/edit
UPDATE: PUT   /{company}/customers/{customer}
DELETE: DELETE/{company}/customers/{customer}

FormRequest: StoreCustomerRequest, UpdateCustomerRequest
Permissions: customers.view, customers.create, customers.manage
```

#### Bills (AP)
```
LIST:  GET    /{company}/bills
CREATE: GET   /{company}/bills/create
STORE: POST   /{company}/bills
VIEW:  GET    /{company}/bills/{bill}
EDIT:  GET    /{company}/bills/{bill}/edit
UPDATE: PUT   /{company}/bills/{bill}
DELETE: DELETE/{company}/bills/{bill}

WORKFLOW ACTIONS:
POST   /{company}/bills/{bill}/approve
POST   /{company}/bills/{bill}/void

FormRequest: StoreBillRequest, UpdateBillRequest
Permissions: bills.view, bills.create, bills.approve, bills.void
```

#### Vendors (AP)
```
LIST:  GET    /{company}/vendors
CREATE: GET   /{company}/vendors/create
STORE: POST   /{company}/vendors
VIEW:  GET    /{company}/vendors/{vendor}
EDIT:  GET    /{company}/vendors/{vendor}/edit
UPDATE: PUT   /{company}/vendors/{vendor}
DELETE: DELETE/{company}/vendors/{vendor}

FormRequest: StoreVendorRequest, UpdateVendorRequest
Permissions: vendors.view, vendors.create, vendors.manage
```

#### Payments (AR Collections)
```
LIST:  GET    /{company}/payments
CREATE: GET   /{company}/payments/create
STORE: POST   /{company}/payments
VIEW:  GET    /{company}/payments/{payment}

SPECIAL ACTIONS:
POST   /{company}/payments/{payment}/allocate
  Allocate payment to invoices

FormRequest: StorePaymentRequest
Permissions: payments.view, payments.create
```

#### Chart of Accounts
```
LIST:  GET    /{company}/accounts
CREATE: GET   /{company}/accounts/create
STORE: POST   /{company}/accounts
VIEW:  GET    /{company}/accounts/{account}
EDIT:  GET    /{company}/accounts/{account}/edit
UPDATE: PUT   /{company}/accounts/{account}

FormRequest: StoreAccountRequest, UpdateAccountRequest
Permissions: accounting.accounts.manage
```

#### Fiscal Years & Periods
```
LIST:  GET    /{company}/fiscal-years
CREATE: GET   /{company}/fiscal-years/create
STORE: POST   /{company}/fiscal-years
VIEW:  GET    /{company}/fiscal-years/{fiscal_year}

LIST:  GET    /{company}/accounting-periods
CREATE: GET   /{company}/accounting-periods/create
STORE: POST   /{company}/accounting-periods

FormRequest: StoreFiscalYearRequest
Permissions: accounting.fiscal_years.manage
```

#### Journal Entries (Manual GL)
```
LIST:  GET    /{company}/journals
CREATE: GET   /{company}/journals/create
STORE: POST   /{company}/journals
VIEW:  GET    /{company}/journals/{journal}

FormRequest: StoreJournalEntryRequest
Permissions: accounting.journals.create
```

### Inventory Features (Company-Scoped)

#### Items (Inventory Master)
```
LIST:  GET    /{company}/items
CREATE: GET   /{company}/items/create
STORE: POST   /{company}/items
VIEW:  GET    /{company}/items/{item}
EDIT:  GET    /{company}/items/{item}/edit
UPDATE: PUT   /{company}/items/{item}
DELETE: DELETE/{company}/items/{item}

FormRequest: StoreItemRequest, UpdateItemRequest
Permissions: inventory.items.view, inventory.items.manage
```

#### Item Categories
```
LIST:  GET    /{company}/item-categories
STORE: POST   /{company}/item-categories
UPDATE: PUT   /{company}/item-categories/{category}
DELETE: DELETE/{company}/item-categories/{category}

FormRequest: StoreItemCategoryRequest
Permissions: inventory.categories.manage
```

#### Stock Movements
```
LIST:  GET    /{company}/stock-movements
CREATE: GET   /{company}/stock-movements/create
STORE: POST   /{company}/stock-movements
  (In: purchase, Adjust: correction, Out: sales)

FormRequest: StoreStockMovementRequest
Permissions: inventory.stock.manage
```

### Payroll Features (Company-Scoped)

#### Employees
```
LIST:  GET    /{company}/employees
CREATE: GET   /{company}/employees/create
STORE: POST   /{company}/employees
VIEW:  GET    /{company}/employees/{employee}
EDIT:  GET    /{company}/employees/{employee}/edit
UPDATE: PUT   /{company}/employees/{employee}

FormRequest: StoreEmployeeRequest, UpdateEmployeeRequest
Permissions: payroll.employees.view, payroll.employees.manage
```

#### Earning Types
```
LIST:  GET    /{company}/earning-types
STORE: POST   /{company}/earning-types
UPDATE: PUT   /{company}/earning-types/{type}
DELETE: DELETE/{company}/earning-types/{type}

FormRequest: StoreEarningTypeRequest
Permissions: payroll.config.manage
```

#### Deduction Types
```
LIST:  GET    /{company}/deduction-types
STORE: POST   /{company}/deduction-types
UPDATE: PUT   /{company}/deduction-types/{type}
DELETE: DELETE/{company}/deduction-types/{type}

FormRequest: StoreDeductionTypeRequest
Permissions: payroll.config.manage
```

#### Leave Types
```
LIST:  GET    /{company}/leave-types
STORE: POST   /{company}/leave-types
UPDATE: PUT   /{company}/leave-types/{type}
DELETE: DELETE/{company}/leave-types/{type}

FormRequest: StoreLeaveTypeRequest
Permissions: payroll.leaves.manage
```

#### Leave Requests
```
LIST:  GET    /{company}/leave-requests
CREATE: GET   /{company}/leave-requests/create
STORE: POST   /{company}/leave-requests
VIEW:  GET    /{company}/leave-requests/{request}

WORKFLOW ACTIONS:
POST   /{company}/leave-requests/{request}/approve
POST   /{company}/leave-requests/{request}/reject

FormRequest: StoreLeaveRequestRequest
Permissions: payroll.leaves.view, payroll.leaves.request
```

### Banking Features (Company-Scoped)

#### Bank Accounts
```
LIST:  GET    /{company}/banking
CREATE: GET   /{company}/banking/create
STORE: POST   /{company}/banking
VIEW:  GET    /{company}/banking/{account}

FormRequest: StoreBankAccountRequest
Permissions: banking.accounts.manage
```

### Company Settings

#### Company Settings
```
Route: GET /{company}/settings
Controller: CompanySettingController@index
Purpose: Company configuration (name, tax ID, currency, etc.)
Response: Inertia 'Company/Settings' page

Route: PUT /{company}/settings
Controller: CompanySettingController@update
Purpose: Update company settings
FormRequest: UpdateCompanySettingRequest
Permissions: company.settings.manage
```

#### Company Users & Roles
```
Route: GET /{company}/users
Controller: CompanyUserController@index
Purpose: View company users

Route: POST /{company}/users
Controller: CompanyUserController@store
Purpose: Invite new user to company
FormRequest: InviteCompanyUserRequest
  - email (required)
  - roles (array of role IDs)
Permissions: company.users.manage

Route: PUT /{company}/users/{user}
Controller: CompanyUserController@update
Purpose: Update user roles
FormRequest: UpdateCompanyUserRequest
  - roles (array)
Permissions: company.users.manage

Route: DELETE /{company}/users/{user}
Controller: CompanyUserController@destroy
Purpose: Remove user from company
Permissions: company.users.manage
```

---

## Frontend Navigation

### Navigation Registry

**Location:** `build/resources/js/navigation/registry.ts`

Defines navigation structure that auto-generates from routes.

```typescript
export const navigationRegistry = {
  // Core navigation (top-level)
  main: [
    { label: 'Dashboard', href: '/invoices', icon: 'dashboard' },
    { label: 'Accounting', href: '/accounting', icon: 'calculator' },
    { label: 'Inventory', href: '/inventory', icon: 'package' },
    { label: 'Payroll', href: '/payroll', icon: 'users' },
    { label: 'Banking', href: '/banking', icon: 'bank' },
  ],

  // Accounting sub-navigation
  accounting: [
    { label: 'Invoices', href: '/invoices' },
    { label: 'Bills', href: '/bills' },
    { label: 'Customers', href: '/customers' },
    { label: 'Vendors', href: '/vendors' },
    { label: 'Chart of Accounts', href: '/accounts' },
    { label: 'Fiscal Years', href: '/fiscal-years' },
  ],

  // Inventory sub-navigation
  inventory: [
    { label: 'Items', href: '/items' },
    { label: 'Categories', href: '/item-categories' },
    { label: 'Stock Movements', href: '/stock-movements' },
  ],

  // Payroll sub-navigation
  payroll: [
    { label: 'Employees', href: '/employees' },
    { label: 'Leave Requests', href: '/leave-requests' },
    { label: 'Payroll Runs', href: '/payroll-runs' },
  ],

  // Settings
  settings: [
    { label: 'Company Settings', href: '/settings' },
    { label: 'Users & Roles', href: '/users' },
    { label: 'Tax Configuration', href: '/settings/tax' },
  ],
}
```

### Page Resolver

**Location:** `build/resources/js/app.ts`

Dynamic page component resolver:

```typescript
// Tries to resolve pages from two locations:
// 1. build/resources/js/pages/{module}/{action}.vue
// 2. build/resources/js/routes/{module}/{action}/Page.vue

resolve: (name) => {
  // First try local pages
  const pages = import.meta.glob('./pages/**/*.vue', { eager: true })
  if (pages[`./pages/${name}.vue`]) {
    return pages[`./pages/${name}.vue`]
  }

  // Then try module-based routes
  const routes = import.meta.glob('./routes/**/Page.vue', { eager: true })
  // ...
}
```

### Breadcrumb Navigation

**Component:** `build/resources/js/components/Breadcrumbs.vue`

Auto-generates breadcrumbs from current route:

```
Dashboard > Accounting > Invoices > Edit
  ↓         ↓           ↓          ↓
  /         /accounting /invoices  /invoices/123/edit
```

---

## Feature Module Tree

### Module Organization

Each feature module is organized as:

```
build/resources/js/routes/{feature-name}/
├── Page.vue                 # Main list/view page
├── Create.vue              # Create form (optional)
├── Edit.vue                # Edit form (optional)
├── Show.vue                # Detail view (optional)
├── composables/            # Composables specific to module
│   ├── useInvoices.ts
│   ├── useInlineEdit.ts
│   └── ...
├── types.ts                # TypeScript types
├── constants.ts            # Constants/enums
└── utils.ts                # Helper functions
```

### Core Modules

#### 1. Invoices (`/resources/js/routes/invoices/`)
- **Pages:** List, Create, Edit, Show
- **Actions:** Create, Update, Delete, Approve, Void
- **Permissions:** invoices.view, invoices.create, invoices.approve
- **Components:** InvoiceForm, InvoiceTable, InvoiceDetail

#### 2. Bills (`/resources/js/routes/bills/`)
- **Pages:** List, Create, Edit, Show
- **Actions:** Create, Update, Approve, Void
- **Permissions:** bills.view, bills.create, bills.approve

#### 3. Customers (`/resources/js/routes/customers/`)
- **Pages:** List, Create, Edit, Show
- **Actions:** Create, Update, Delete
- **Permissions:** customers.view, customers.create, customers.manage

#### 4. Vendors (`/resources/js/routes/vendors/`)
- **Pages:** List, Create, Edit, Show
- **Actions:** Create, Update, Delete
- **Permissions:** vendors.view, vendors.create, vendors.manage

#### 5. Items (`/resources/js/routes/items/`)
- **Pages:** List, Create, Edit, Show
- **Actions:** Create, Update, Delete
- **Permissions:** inventory.items.view, inventory.items.manage

#### 6. Employees (`/resources/js/routes/employees/`)
- **Pages:** List, Create, Edit, Show
- **Actions:** Create, Update, Delete
- **Permissions:** payroll.employees.view, payroll.employees.manage

### Complete Feature List (49+ Modules)

**Accounting:**
- invoices, bills, customers, vendors, payments
- accounts, fiscal-years, accounting-periods
- journals, credit-notes

**Inventory:**
- items, item-categories, stock-movements

**Payroll:**
- employees, earning-types, deduction-types
- leave-types, leave-requests

**Banking:**
- banking (accounts, reconciliation)

**Company:**
- company-settings, users

**Other:**
- partners, appearance, dashboard

---

## API Routes

**Location:** `build/routes/api.php`

REST API for mobile/external clients.

### Public Endpoints
```
POST /api/login             # Authenticate, receive token
POST /api/register          # Create account
```

### Authenticated Endpoints (Bearer Token)
```
GET    /api/{company}/invoices
GET    /api/{company}/invoices/{id}
POST   /api/{company}/invoices
PUT    /api/{company}/invoices/{id}
DELETE /api/{company}/invoices/{id}

// Similar for: customers, bills, vendors, items, employees, etc.
```

**Authentication:** Laravel Sanctum (Bearer token in `Authorization: Bearer {token}` header)

---

## Related Documentation

- **System Architecture:** `ARCHITECTURE.md`
- **Request Journey:** `ARCHITECTURE-REQUEST-FLOW.md`
- **Permissions:** `ARCHITECTURE-PERMISSIONS.md`
- **Schema Details:** `docs/contracts/00-master-index.md`
- **Quick Reference:** `CLAUDE.md`

---

*Last updated: 2025-01-21*
