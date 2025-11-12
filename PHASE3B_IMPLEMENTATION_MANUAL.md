# Phase 3B: Expense Cycle (Accounts Payable) - Implementation Manual
**For Junior Developers**
*Generated: 2025-11-11*
*Test Results: CRITICAL FEATURES MISSING*

## 🚨 CRITICAL STATUS: PHASE 3B NOT IMPLEMENTED

Based on interactive testing using Playwright, **ALL Phase 3B features are currently missing** from the Haasib application. The following URLs return 404 errors, confirming complete absence of Expense Cycle functionality:

- `/vendors` - ❌ Not Found (404)
- `/purchase-orders` - ❌ Not Found (404)
- `/expenses` - ❌ Connection Failed
- `/bills` - ❌ Not Tested (likely missing)
- `/payments` - ❌ Not Tested (likely missing)

## 📋 IMPLEMENTATION ROADMAP

### 🔥 IMMEDIATE PRIORITY (Critical Path)

#### 1. Vendor/Supplier Management
**URL**: `/vendors`
**Status**: ❌ **MISSING** - 404 Error

**Database Tables Required**:
```sql
-- Core vendor table
CREATE TABLE vendors (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    vendor_code VARCHAR(50) UNIQUE NOT NULL,
    legal_name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255),
    tax_id VARCHAR(50), -- EIN, SSN, etc.
    vendor_type ENUM('individual', 'company', 'other') DEFAULT 'company',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    website VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Vendor contact information
CREATE TABLE vendor_contacts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    vendor_id BIGINT NOT NULL,
    contact_type ENUM('primary', 'billing', 'technical', 'other') DEFAULT 'primary',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    mobile VARCHAR(50),
    is_primary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Vendor payment terms
CREATE TABLE vendor_payment_terms (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    vendor_id BIGINT NOT NULL,
    default_terms_id BIGINT,
    net_days INT DEFAULT 30,
    discount_days INT DEFAULT 0,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (default_terms_id) REFERENCES payment_terms(id)
);

-- Vendor bank accounts for ACH/wire payments
CREATE TABLE vendor_bank_accounts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    vendor_id BIGINT NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(100) NOT NULL, -- Encrypted
    routing_number VARCHAR(100) NOT NULL,
    account_type ENUM('checking', 'savings') DEFAULT 'checking',
    is_default BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);
```

**Laravel Controllers & Models Needed**:
```bash
# Models
php artisan make:model Vendor -m
php artisan make:model VendorContact -m
php artisan make:model VendorPaymentTerm -m
php artisan make:model VendorBankAccount -m

# Controllers
php artisan make:controller VendorController --resource
php artisan make:controller VendorContactController --resource
```

**Key Files to Create**:
- `app/Models/Vendor.php`
- `app/Models/VendorContact.php`
- `app/Http/Controllers/VendorController.php`
- `resources/js/Pages/Vendors/Index.vue`
- `resources/js/Pages/Vendors/Create.vue`
- `resources/js/Pages/Vendors/Edit.vue`
- `resources/js/Pages/Vendors/Show.vue`

**Routes to Add** (`routes/web.php`):
```php
// Vendor Management Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('vendors', VendorController::class);
    Route::get('vendors/{vendor}/contacts', [VendorContactController::class, 'index'])->name('vendors.contacts.index');
    Route::post('vendors/{vendor}/contacts', [VendorContactController::class, 'store'])->name('vendors.contacts.store');
});
```

#### 2. Purchase Order Processing
**URL**: `/purchase-orders`
**Status**: ❌ **MISSING** - 404 Error

**Database Tables Required**:
```sql
-- Purchase orders table
CREATE TABLE purchase_orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_id BIGINT NOT NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'sent', 'partial_received', 'received', 'closed', 'cancelled') DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_delivery_date DATE,
    currency VARCHAR(3) DEFAULT 'USD',
    exchange_rate DECIMAL(12,6) DEFAULT 1.000000,
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    notes TEXT,
    internal_notes TEXT,
    approved_by BIGINT,
    approved_at TIMESTAMP NULL,
    sent_to_vendor_at TIMESTAMP NULL,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Purchase order line items
CREATE TABLE purchase_order_lines (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    po_id BIGINT NOT NULL,
    line_number INT NOT NULL,
    product_id BIGINT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    unit_price DECIMAL(15,6) NOT NULL DEFAULT 0.000000,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    tax_rate DECIMAL(8,5) DEFAULT 0.00000,
    line_total DECIMAL(15,2) GENERATED ALWAYS AS (
        (quantity * unit_price) * (1 - discount_percentage/100)
    ) STORED,
    received_quantity DECIMAL(12,4) DEFAULT 0.0000,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);
```

#### 3. Bills Management (Vendor Bills)
**URL**: `/bills`
**Status**: ❌ **MISSING** - Not Tested (Likely 404)

**Database Tables Required**:
```sql
-- Vendor bills table
CREATE TABLE bills (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    bill_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_id BIGINT NOT NULL,
    po_id BIGINT NULL, -- Optional: created from PO
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('draft', 'approved', 'scheduled', 'partial_paid', 'paid', 'disputed', 'void') DEFAULT 'draft',
    currency VARCHAR(3) DEFAULT 'USD',
    exchange_rate DECIMAL(12,6) DEFAULT 1.000000,
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance_due DECIMAL(15,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    notes TEXT,
    vendor_invoice_number VARCHAR(100),
    approved_by BIGINT,
    approved_at TIMESTAMP NULL,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Bill line items
CREATE TABLE bill_lines (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    bill_id BIGINT NOT NULL,
    line_number INT NOT NULL,
    account_id BIGINT NOT NULL, -- GL account for expense classification
    description TEXT NOT NULL,
    quantity DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    unit_price DECIMAL(15,6) NOT NULL DEFAULT 0.000000,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    tax_rate DECIMAL(8,5) DEFAULT 0.00000,
    line_total DECIMAL(15,2) GENERATED ALWAYS AS (
        (quantity * unit_price) * (1 - discount_percentage/100)
    ) STORED,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);
```

#### 4. Expense Management
**URL**: `/expenses`
**Status**: ❌ **MISSING** - Connection Failed

**Database Tables Required**:
```sql
-- Expense reports table
CREATE TABLE expense_reports (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL, -- User who submitted
    report_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('draft', 'submitted', 'manager_approved', 'finance_approved', 'rejected', 'paid') DEFAULT 'draft',
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    submitted_at TIMESTAMP NULL,
    manager_approved_by BIGINT,
    manager_approved_at TIMESTAMP NULL,
    finance_approved_by BIGINT,
    finance_approved_at TIMESTAMP NULL,
    rejected_by BIGINT,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT,
    notes TEXT,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (manager_approved_by) REFERENCES users(id),
    FOREIGN KEY (finance_approved_by) REFERENCES users(id),
    FOREIGN KEY (rejected_by) REFERENCES users(id)
);

-- Expense line items
CREATE TABLE expense_lines (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    expense_report_id BIGINT NOT NULL,
    expense_date DATE NOT NULL,
    category_id BIGINT NOT NULL,
    account_id BIGINT NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    receipt_path VARCHAR(255), -- File path to receipt image/PDF
    mileage_km DECIMAL(8,2) DEFAULT 0.00,
    receipt_required BOOLEAN DEFAULT TRUE,
    receipt_uploaded BOOLEAN DEFAULT FALSE,
    notes TEXT,
    FOREIGN KEY (expense_report_id) REFERENCES expense_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- Expense categories
CREATE TABLE expense_categories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    requires_receipt BOOLEAN DEFAULT TRUE,
    mileage_rate DECIMAL(8,4) DEFAULT 0.0000, -- Per km/mile rate
    max_amount DECIMAL(10,2) DEFAULT 99999.99,
    is_active BOOLEAN DEFAULT TRUE,
    parent_id BIGINT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (parent_id) REFERENCES expense_categories(id)
);
```

#### 5. Payment Processing (Vendor Payments)
**URL**: `/payments` or `/vendor-payments`
**Status**: ❌ **MISSING** - Not Tested (Likely 404)

**Database Tables Required**:
```sql
-- Payment batches table
CREATE TABLE payment_batches (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    batch_number VARCHAR(50) UNIQUE NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('check', 'ach', 'wire', 'credit_card', 'other') NOT NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'processed', 'cancelled') DEFAULT 'draft',
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    number_of_payments INT DEFAULT 0,
    bank_account_id BIGINT,
    approved_by BIGINT,
    approved_at TIMESTAMP NULL,
    processed_by BIGINT,
    processed_at TIMESTAMP NULL,
    notes TEXT,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (bank_account_id) REFERENCES company_bank_accounts(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Individual payments within batch
CREATE TABLE payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_batch_id BIGINT NOT NULL,
    bill_id BIGINT NOT NULL,
    vendor_id BIGINT NOT NULL,
    payment_amount DECIMAL(15,2) NOT NULL,
    discount_taken DECIMAL(15,2) DEFAULT 0.00,
    payment_method ENUM('check', 'ach', 'wire', 'credit_card', 'other') NOT NULL,
    check_number VARCHAR(50),
    reference_number VARCHAR(100),
    status ENUM('scheduled', 'processed', 'failed', 'void') DEFAULT 'scheduled',
    processed_date DATE,
    notes TEXT,
    FOREIGN KEY (payment_batch_id) REFERENCES payment_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);
```

## 🛠️ IMPLEMENTATION STEPS FOR JUNIOR DEVELOPER

### Step 1: Setup Database Foundation
1. **Create all migrations** listed above in order
2. **Run migrations**: `php artisan migrate`
3. **Add foreign key constraints** after all tables created
4. **Create seeders** for initial data (payment terms, expense categories)

### Step 2: Create Models with Relationships
**Example Vendor Model**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'vendor_code', 'legal_name', 'display_name',
        'tax_id', 'vendor_type', 'status', 'website', 'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts()
    {
        return $this->hasMany(VendorContact::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(VendorContact::class)->where('is_primary', true);
    }

    public function paymentTerms()
    {
        return $this->hasOne(VendorPaymentTerm::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(VendorBankAccount::class);
    }

    public function defaultBankAccount()
    {
        return $this->hasOne(VendorBankAccount::class)->where('is_default', true);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
```

### Step 3: Create Controllers with Resource Methods
**Basic Controller Structure**:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:create-vendors')->only(['create', 'store']);
        $this->middleware('permission:edit-vendors')->only(['edit', 'update']);
        $this->middleware('permission:delete-vendors')->only(['destroy']);
    }

    public function index()
    {
        $vendors = Vendor::where('company_id', current_company_id())
            ->with(['primaryContact'])
            ->paginate(25);

        return inertia('Vendors/Index', compact('vendors'));
    }

    public function create()
    {
        return inertia('Vendors/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors',
            'tax_id' => 'nullable|string|max:50',
            'vendor_type' => 'required|in:individual,company,other',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'contacts' => 'required|array|min:1',
            'contacts.*.first_name' => 'required|string|max:100',
            'contacts.*.last_name' => 'required|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
        ]);

        $vendor = Vendor::create([
            'company_id' => current_company_id(),
            'vendor_code' => $validated['vendor_code'],
            'legal_name' => $validated['legal_name'],
            'display_name' => $validated['display_name'] ?? $validated['legal_name'],
            'tax_id' => $validated['tax_id'] ?? null,
            'vendor_type' => $validated['vendor_type'],
            'website' => $validated['website'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'active',
        ]);

        // Create contacts
        foreach ($validated['contacts'] as $index => $contactData) {
            VendorContact::create([
                'vendor_id' => $vendor->id,
                'contact_type' => $index === 0 ? 'primary' : 'other',
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'is_primary' => $index === 0,
            ]);
        }

        return redirect()->route('vendors.show', $vendor)
            ->with('success', 'Vendor created successfully.');
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['contacts', 'paymentTerms', 'bankAccounts', 'purchaseOrders', 'bills']);
        return inertia('Vendors/Show', compact('vendor'));
    }

    public function edit(Vendor $vendor)
    {
        $vendor->load(['contacts', 'paymentTerms', 'bankAccounts']);
        return inertia('Vendors/Edit', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        // Validation and update logic similar to store()
    }

    public function destroy(Vendor $vendor)
    {
        // Check if vendor has transactions before allowing deletion
        if ($vendor->purchaseOrders()->exists() || $vendor->bills()->exists()) {
            return back()->with('error', 'Cannot delete vendor with existing transactions.');
        }

        $vendor->delete();
        return redirect()->route('vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }
}
```

### Step 4: Create Vue.js Frontend Components

**Key Files to Create**:
- `resources/js/Pages/Vendors/Index.vue` - Vendor listing with search/filter
- `resources/js/Pages/Vendors/Create.vue` - Vendor creation form
- `resources/js/Pages/Vendors/Show.vue` - Vendor details with tabs
- `resources/js/Pages/Vendors/Edit.vue` - Vendor editing form

**Basic Vue Component Structure**:
```vue
<template>
  <AppLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-semibold text-gray-900">Vendors</h1>
          <Link :href="route('vendors.create')" class="btn btn-primary">
            Add Vendor
          </Link>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input
              v-model="search"
              type="text"
              placeholder="Search vendors..."
              class="form-input"
            />
            <select v-model="statusFilter" class="form-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <select v-model="typeFilter" class="form-select">
              <option value="">All Types</option>
              <option value="company">Company</option>
              <option value="individual">Individual</option>
            </select>
          </div>
        </div>

        <!-- Vendors Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="vendor in vendors.data" :key="vendor.id">
                <td class="px-6 py-4 whitespace-nowrap">
                  <Link :href="route('vendors.show', vendor)" class="text-blue-600 hover:text-blue-800">
                    {{ vendor.display_name || vendor.legal_name }}
                  </Link>
                  <div class="text-sm text-gray-500">{{ vendor.vendor_code }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                    {{ vendor.vendor_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ vendor.primary_contact?.email }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusClass(vendor.status)">
                    {{ vendor.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <Link :href="route('vendors.edit', vendor)" class="text-blue-600 hover:text-blue-900 mr-3">
                    Edit
                  </Link>
                  <button @click="deleteVendor(vendor)" class="text-red-600 hover:text-red-900">
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <Pagination :links="vendors.links" class="mt-6" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  vendors: Object
})

const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')

watch([search, statusFilter, typeFilter], () => {
  router.get(
    route('vendors.index'),
    {
      search: search.value,
      status: statusFilter.value,
      type: typeFilter.value
    },
    { preserveState: true, preserveScroll: true }
  )
})

function getStatusClass(status) {
  return {
    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full': true,
    'bg-green-100 text-green-800': status === 'active',
    'bg-red-100 text-red-800': status === 'inactive'
  }
}

function deleteVendor(vendor) {
  if (confirm('Are you sure you want to delete this vendor?')) {
    router.delete(route('vendors.destroy', vendor))
  }
}
</script>
```

### Step 5: Add Navigation and Routing

**Add to Main Navigation** (`resources/js/layouts/AppLayout.vue`):
```vue
<!-- In Accounting Operations menu -->
<template #accountingOperations>
  <div class="space-y-1">
    <Link href="/vendors" class="menu-item">🏪 Vendors</Link>
    <Link href="/purchase-orders" class="menu-item">📋 Purchase Orders</Link>
    <Link href="/bills" class="menu-item">🧾 Bills</Link>
    <Link href="/expenses" class="menu-item">💳 Expense Reports</Link>
    <Link href="/vendor-payments" class="menu-item">💰 Vendor Payments</Link>
  </div>
</template>
```

### Step 6: Update Company Permissions

**Add to Company Permissions Seeder**:
```php
// In database/seeders/CompanyPermissionSeeder.php
$permissions = [
    // Existing permissions...

    // Phase 3B: Expense Cycle Permissions
    'view-vendors',
    'create-vendors',
    'edit-vendors',
    'delete-vendors',
    'view-purchase-orders',
    'create-purchase-orders',
    'approve-purchase-orders',
    'view-bills',
    'create-bills',
    'approve-bills',
    'view-expense-reports',
    'create-expense-reports',
    'approve-expense-reports',
    'process-vendor-payments',
    'approve-vendor-payments',
];
```

## 🧪 TESTING REQUIREMENTS

### Manual Testing Checklist
1. **Vendor CRUD Operations**
   - [ ] Create vendor with required fields
   - [ ] Add multiple contacts to vendor
   - [ ] Edit vendor information
   - [ ] Delete vendor without transactions
   - [ ] Try to delete vendor with transactions (should fail)

2. **Purchase Order Workflow**
   - [ ] Create draft purchase order
   - [ ] Add line items with products/services
   - [ ] Submit PO for approval
   - [ ] Approve PO (as manager)
   - [ ] Send PO to vendor
   - [ ] Receive partial quantity
   - [ ] Close PO when fully received

3. **Bill Management**
   - [ ] Create bill from PO
   - [ ] Create direct bill (no PO)
   - [ ] Three-way match validation
   - [ ] Calculate due dates based on terms
   - [ ] Apply early payment discounts

4. **Expense Reports**
   - [ ] Create expense report
   - [ ] Add multiple expense lines
   - [ ] Upload receipts
   - [ ] Submit for approval
   - [ ] Manager approval workflow
   - [ ] Finance approval workflow

5. **Payment Processing**
   - [ ] Create payment batch
   - [ ] Select multiple bills for payment
   - [ ] Choose payment method (check/ACH)
   - [ ] Approve payment batch
   - [ ] Process payments
   - [ ] Generate check numbers/ACH files

### Automated Testing with Playwright
**Create Test File**: `tests/e2e/phase3b-expense-cycle.spec.ts`

```typescript
import { test, expect } from '@playwright/test';

test.describe('Phase 3B: Expense Cycle', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    // Login as test user
  });

  test('Vendor Management - Complete CRUD', async ({ page }) => {
    // Navigate to vendors
    await page.goto('/vendors');

    // Create new vendor
    await page.click('text=Add Vendor');
    await page.fill('[name="legal_name"]', 'Test Vendor Corp');
    await page.fill('[name="vendor_code"]', 'VEND001');
    await page.fill('[name="email"]', 'contact@testvendor.com');
    await page.click('text=Save');

    // Verify vendor created
    await expect(page.locator('text=Test Vendor Corp')).toBeVisible();
    await expect(page.locator('text=VEND001')).toBeVisible();

    // Edit vendor
    await page.click('text=Edit');
    await page.fill('[name="legal_name"]', 'Test Vendor Corp Updated');
    await page.click('text=Save');

    // Verify vendor updated
    await expect(page.locator('text=Test Vendor Corp Updated')).toBeVisible();
  });

  test('Purchase Order - Complete Workflow', async ({ page }) => {
    // Create PO first, then test full workflow
    await page.goto('/purchase-orders/create');

    // Fill PO details
    await page.selectOption('[name="vendor_id"]', 'Test Vendor Corp');
    await page.fill('[name="order_date"]', '2025-11-11');
    await page.click('text=Add Line Item');
    await page.fill('[name="line_items[0][description]"]', 'Test Product');
    await page.fill('[name="line_items[0][quantity]"]', '10');
    await page.fill('[name="line_items[0][unit_price]"]', '100');
    await page.click('text=Save as Draft');

    // Submit for approval
    await page.click('text=Submit for Approval');
    await expect(page.locator('text=Pending Approval')).toBeVisible();
  });

  test('Bill Management - Create from PO', async ({ page }) => {
    // This test requires PO to exist first
    await page.goto('/bills/create');
    await page.selectOption('[name="po_id"]', 'PO-001');
    // Verify bill details auto-populate from PO
    await page.click('text=Create Bill');
    await expect(page.locator('text=Bill Created Successfully')).toBeVisible();
  });

  test('Expense Report - Complete Workflow', async ({ page }) => {
    await page.goto('/expenses/create');

    await page.fill('[name="title"]', 'Business Travel Expenses');
    await page.click('text=Add Expense');
    await page.fill('[name="expenses[0][description]"]', 'Hotel Stay');
    await page.fill('[name="expenses[0][amount]"]', '250');
    await page.fill('[name="expenses[0][expense_date]"]', '2025-11-10');
    await page.click('text=Submit Report');

    await expect(page.locator('text=Submitted for Approval')).toBeVisible();
  });

  test('Payment Batch - Create and Process', async ({ page }) => {
    await page.goto('/vendor-payments/create');

    await page.click('text=Select Bills');
    await page.check('input[name="bills[]"]'); // Select first bill
    await page.click('text=Create Payment Batch');

    await page.fill('[name="payment_date"]', '2025-11-15');
    await page.selectOption('[name="payment_method"]', 'ACH');
    await page.click('text=Submit for Approval');

    await expect(page.locator('text=Payment Batch Created')).toBeVisible();
  });
});
```

## 🚨 CRITICAL NEXT STEPS

1. **IMMEDIATE**: Start with Vendor Management (highest priority, foundation for everything else)
2. **SECOND**: Implement Purchase Order processing
3. **THIRD**: Add Bills Management
4. **FOURTH**: Create Expense Management
5. **FIFTH**: Build Payment Processing

## 📞 SUPPORT CONTACTS

- **Database Schema Issues**: Contact Senior Developer
- **Laravel Backend Problems**: Ask for Code Review
- **Vue.js Frontend Issues**: Check documentation first, then ask for help
- **Testing Problems**: Review test examples above

## ⏰ ESTIMATED TIMELINE

- **Vendor Management**: 3-5 days
- **Purchase Orders**: 5-7 days
- **Bills Management**: 4-6 days
- **Expense Management**: 4-6 days
- **Payment Processing**: 5-7 days
- **Testing & Bug Fixes**: 3-5 days

**Total Estimated Time: 24-36 days** (4-7 weeks)

---

## 🚀 SUCCESS CRITERIA

Phase 3B will be considered complete when:
- ✅ All URLs return 200 OK responses
- ✅ Full CRUD operations work for all entities
- ✅ Workflow approvals function correctly
- ✅ Three-way matching (PO vs Receipt vs Bill) works
- ✅ Payment processing generates correct accounting entries
- ✅ All automated tests pass
- ✅ Manual testing validates all business rules

**Good luck, Junior Developer! You've got this! 💪**