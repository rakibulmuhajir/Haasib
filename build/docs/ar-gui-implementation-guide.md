# AR (Accounts Receivable) GUI Implementation Guide

This guide explains how to create web GUI CRUD for the remaining AR entities by following the **Customer** implementation pattern.

## Entities to Implement

| Entity | Model | Actions Exist | Priority |
|--------|-------|---------------|----------|
| Invoice | `modules/Accounting/Models/Invoice.php` | Yes | 1 |
| Payment | `modules/Accounting/Models/Payment.php` | Yes | 2 |
| CreditNote | `modules/Accounting/Models/CreditNote.php` | Yes | 3 |
| RecurringSchedule | `modules/Accounting/Models/RecurringSchedule.php` | Partial | 4 |

---

## Architecture Overview

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Vue Page      │────▶│   Controller     │────▶│   CommandBus    │
│  (Inertia form) │     │  (thin, routes)  │     │   dispatch()    │
└─────────────────┘     └──────────────────┘     └────────┬────────┘
                                                          │
                                                          ▼
                                                 ┌─────────────────┐
                                                 │  PaletteAction  │
                                                 │ (business logic)│
                                                 └─────────────────┘
```

- **Controller**: Renders Inertia pages, validates via FormRequest, dispatches to Actions
- **FormRequest**: Authorization + validation (mirrors Action::rules())
- **Actions**: Already exist - contain business logic (reused by CLI palette AND web GUI)
- **Vue Pages**: Use `PageShell`, `DataTable`, `useForm` from Inertia

---

## Reference Files (Customer Implementation)

Use these as templates:

### Backend
| File | Copy For |
|------|----------|
| `modules/Accounting/Http/Controllers/CustomerController.php` | Controller pattern |
| `modules/Accounting/Http/Requests/StoreCustomerRequest.php` | Store FormRequest |
| `modules/Accounting/Http/Requests/UpdateCustomerRequest.php` | Update FormRequest |

### Frontend
| File | Copy For |
|------|----------|
| `resources/js/pages/accounting/customers/Index.vue` | List page with DataTable |
| `resources/js/pages/accounting/customers/Create.vue` | Create form |
| `resources/js/pages/accounting/customers/Show.vue` | Detail view |
| `resources/js/pages/accounting/customers/Edit.vue` | Edit form |

### Routes
| File | Section |
|------|---------|
| `routes/web.php` | Customer routes block (lines 49-56) |

### Sidebar
| File | Section |
|------|---------|
| `resources/js/components/AppSidebar.vue` | mainNavItems computed |

---

## Step-by-Step: Implementing Invoice GUI

### 1. Create Directory Structure

```bash
mkdir -p modules/Accounting/Http/Controllers
mkdir -p modules/Accounting/Http/Requests
mkdir -p resources/js/pages/accounting/invoices
```

### 2. Read Existing Action Rules

Check `modules/Accounting/Actions/Invoice/CreateAction.php` for validation rules:

```php
// Copy these rules to your FormRequest
public function rules(): array
{
    return [
        'customer_id' => 'required|uuid',
        'currency' => 'required|string|size:3',
        // ... etc
    ];
}
```

### 3. Create FormRequests

**StoreInvoiceRequest.php**
```php
<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::INVOICE_CREATE) ?? false;
    }

    public function rules(): array
    {
        // Copy from CreateAction::rules()
        return [
            'customer_id' => ['required', 'uuid'],
            // ... add all rules
        ];
    }
}
```

### 4. Create Controller

**InvoiceController.php**
```php
<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreInvoiceRequest;
use App\Modules\Accounting\Http\Requests\UpdateInvoiceRequest;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $invoices = Invoice::where('company_id', $company->id)
            ->with('customer:id,name')  // Load customer name
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return Inertia::render('accounting/invoices/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'invoices' => $invoices,
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('invoice.create', $request->validated(), $request->user());

        return redirect()
            ->route('invoices.show', ['company' => $company->slug, 'invoice' => $result['data']['id']])
            ->with('success', $result['message']);
    }

    // ... implement create(), show(), edit(), update(), destroy()
    // Follow CustomerController.php pattern
}
```

### 5. Add Routes

In `routes/web.php`, inside the `identify.company` middleware group:

```php
use App\Modules\Accounting\Http\Controllers\InvoiceController;

// Invoice routes
Route::get('/{company}/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('/{company}/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
Route::post('/{company}/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
Route::get('/{company}/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::get('/{company}/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
Route::put('/{company}/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
Route::delete('/{company}/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
```

### 6. Create Vue Pages

Copy from customers and modify:

**Index.vue** - Key changes:
- Update interface for InvoiceRow (invoice_number, customer_name, total, status, due_date)
- Update table columns
- Change route names

**Create.vue** - Key changes:
- Add customer selector (dropdown/search)
- Add line items section (dynamic rows)
- Add invoice-specific fields (due_date, terms, etc.)

**Show.vue** - Key changes:
- Display invoice details + line items
- Add action buttons (Send, Void, Record Payment)

**Edit.vue** - Key changes:
- Pre-populate with invoice data
- Handle line items editing

### 7. Add to Sidebar

In `resources/js/components/AppSidebar.vue`:

```typescript
import { FileText } from 'lucide-vue-next'  // Add import

// In mainNavItems computed, after Customers:
{
  title: 'Invoices',
  href: `/${slug}/invoices`,
  icon: FileText,
}
```

### 8. Test

```bash
# Verify routes
php artisan route:list --name=invoices

# Build frontend
npm run build

# Test in browser
# Navigate to /{company-slug}/invoices
```

---

## Entity-Specific Notes

### Invoice
- Has line items (InvoiceLineItem) - needs nested form
- Status workflow: draft → sent → viewed → partial → paid / overdue / void
- Links to Customer (customer_id)
- Calculated fields: subtotal, tax, total, balance

### Payment
- Links to Customer and optionally Invoice
- Has allocations (PaymentAllocation) for partial payments
- Status: pending → completed → void

### CreditNote
- Similar to Invoice but for credits
- Can be applied to invoices (CreditNoteApplication)
- Status: draft → issued → partial → applied → void

### RecurringSchedule
- Template for auto-generating invoices
- Has frequency settings (daily, weekly, monthly, yearly)
- Links to Customer
- May need to create missing Actions first

---

## Checklist for Each Entity

- [ ] Read existing Actions to understand validation rules and permissions
- [ ] Create `StoreXxxRequest.php` FormRequest
- [ ] Create `UpdateXxxRequest.php` FormRequest
- [ ] Create `XxxController.php` with all 7 methods
- [ ] Add routes to `routes/web.php`
- [ ] Create `Index.vue` page
- [ ] Create `Create.vue` page
- [ ] Create `Show.vue` page
- [ ] Create `Edit.vue` page
- [ ] Add to sidebar in `AppSidebar.vue`
- [ ] Run `npm run build` to verify no errors
- [ ] Test all CRUD operations in browser

---

## Common Patterns

### Loading Related Data in Controller
```php
$invoices = Invoice::where('company_id', $company->id)
    ->with(['customer:id,name', 'lineItems'])
    ->paginate(25);
```

### Dispatching to CommandBus
```php
$commandBus = app(CommandBus::class);
$result = $commandBus->dispatch('invoice.create', $request->validated(), $request->user());
```

### Inertia Form Submission (Vue)
```typescript
const form = useForm({ /* fields */ })

form.post(`/${company.slug}/invoices`, {
  onSuccess: () => { /* handle success */ }
})
```

### Flash Messages
Controller returns with flash:
```php
return redirect()->route('invoices.index', ['company' => $company->slug])
    ->with('success', 'Invoice created successfully.');
```

Frontend handles via `useFlashMessages` composable (already configured globally).

---

## Files Summary

After implementing all entities, you should have:

```
modules/Accounting/Http/
├── Controllers/
│   ├── CustomerController.php    ✅ Done
│   ├── InvoiceController.php     TODO
│   ├── PaymentController.php     TODO
│   ├── CreditNoteController.php  TODO
│   └── RecurringScheduleController.php  TODO
└── Requests/
    ├── StoreCustomerRequest.php  ✅ Done
    ├── UpdateCustomerRequest.php ✅ Done
    ├── StoreInvoiceRequest.php   TODO
    ├── UpdateInvoiceRequest.php  TODO
    └── ... (2 per entity)

resources/js/pages/accounting/
├── customers/                    ✅ Done
│   ├── Index.vue
│   ├── Create.vue
│   ├── Show.vue
│   └── Edit.vue
├── invoices/                     TODO
├── payments/                     TODO
├── credit-notes/                 TODO
└── recurring-schedules/          TODO
```

---

## Questions?

- Check existing Customer implementation for patterns
- Read the Action files for business logic understanding
- Check `app/Constants/Permissions.php` for permission constants
- Refer to `docs/contracts/acct-schema.md` for database schema details
