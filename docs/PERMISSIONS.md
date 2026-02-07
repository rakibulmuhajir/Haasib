# Haasib RBAC & Permissions Guide

**Understanding the role-based access control system and all 151+ permissions**

---

## Quick Navigation

1. **[Overview](#rbac-overview)** - How permissions work
2. **[Roles](#roles)** - Default roles and hierarchies
3. **[Permissions by Module](#permissions-by-module)** - All 151 permissions organized
4. **[How Permissions Work](#how-permissions-work)** - Permission checking flow
5. **[Adding New Permissions](#adding-new-permissions)** - Step-by-step guide

---

## RBAC Overview

Haasib uses **Spatie Laravel Permission** with **company-level scoping**:

```
User ──[1:many]──→ Role ──[1:many]──→ Permission
                    ↓
              (Company-Scoped)
              → Permissions only valid
                within assigned company
```

### Key Concepts

- **Role:** A label assigned to a user within a company (Owner, Admin, Viewer, etc.)
- **Permission:** An action a user can perform (invoice.create, report.view, etc.)
- **Team (Company):** Scope for permissions - permissions are company-specific
- **User:** Has roles which have permissions, all scoped to a company

---

## Roles

### Default Roles (Config: `config/role-permissions.php`)

#### 1. **Owner**
- **Description:** Full access to all features and settings
- **Permissions:** ALL (151 permissions)
- **Use Case:** Company founder/administrator
- **Cannot be:** Removed from a company (self-service deletion only)

#### 2. **Admin**
- **Description:** Full feature access, limited settings
- **Permissions:** All feature permissions except company deletion/user management
- **Use Case:** Day-to-day administrator

#### 3. **Manager**
- **Description:** Core business operations
- **Permissions:** Create/view/update for invoices, bills, customers, vendors
- **Use Case:** Operations manager

#### 4. **Accountant**
- **Description:** GL, invoices, bills, reports
- **Permissions:** Full accounting module access
- **Use Case:** Finance professional

#### 5. **Viewer / Read-Only**
- **Description:** View all data, no modifications
- **Permissions:** All `.view` permissions only
- **Use Case:** External auditor or stakeholder

#### 6. **Custom Roles**
- **Description:** Company admins can create custom roles
- **Permissions:** Mix and match permissions as needed
- **Use Case:** Department-specific or specialized roles

---

## Permissions by Module

### Accounting Module (36 permissions)

#### Invoices (6)
- `invoice.create` - Create new sales invoice
- `invoice.view` - View invoices
- `invoice.update` - Edit invoice details
- `invoice.delete` - Delete invoice
- `invoice.send` - Send invoice to customer
- `invoice.void` - Void/cancel invoice

#### Customers (4)
- `customer.create` - Create new customer
- `customer.view` - View customer details
- `customer.update` - Edit customer info
- `customer.delete` - Delete customer

#### Payments (4)
- `payment.create` - Record customer payment
- `payment.view` - View payments
- `payment.delete` - Delete payment
- `payment.void` - Void/reverse payment

#### Credit Notes (5)
- `credit_note.create` - Create credit note
- `credit_note.view` - View credit notes
- `credit_note.update` - Edit credit note
- `credit_note.apply` - Apply credit to invoice
- `credit_note.void` - Void credit note

#### Bills (6)
- `bill.create` - Create purchase bill
- `bill.view` - View bills
- `bill.update` - Edit bill
- `bill.delete` - Delete bill
- `bill.pay` - Record bill payment
- `bill.void` - Void bill

#### Vendors (4)
- `vendor.create` - Create vendor
- `vendor.view` - View vendor details
- `vendor.update` - Edit vendor
- `vendor.delete` - Delete vendor

#### Vendor Credits (4)
- `vendor_credit.create` - Create vendor credit
- `vendor_credit.view` - View credits
- `vendor_credit.apply` - Apply to bill
- `vendor_credit.void` - Void credit

#### Chart of Accounts (5)
- `account.create` - Create new account
- `account.view` - View accounts
- `account.update` - Edit account
- `account.reconcile` - Reconcile account balance
- `account.delete` - Delete account

#### Manual Entries (2)
- `journal.create` - Create manual journal entry
- `journal.view` - View manual entries

#### Posting Templates (4)
- `posting_template.create` - Create template
- `posting_template.view` - View templates
- `posting_template.update` - Edit template
- `posting_template.delete` - Delete template

#### Expenses (4)
- `expense.create` - Create expense
- `expense.view` - View expenses
- `expense.update` - Edit expense
- `expense.delete` - Delete expense

### Inventory Module (11 permissions)

#### Items (4)
- `item.create` - Create new item/SKU
- `item.view` - View items
- `item.update` - Edit item
- `item.delete` - Delete item

#### Item Categories (4)
- `item_category.create` - Create category
- `item_category.view` - View categories
- `item_category.update` - Edit category
- `item_category.delete` - Delete category

#### Stock & Warehouses (7)
- `warehouse.create` - Create warehouse
- `warehouse.view` - View warehouses
- `warehouse.update` - Edit warehouse
- `warehouse.delete` - Delete warehouse
- `stock.view` - View stock levels
- `stock.adjust` - Adjust inventory
- `stock.count` - Count stock
- `stock.transfer` - Transfer between warehouses

### Payroll Module (16 permissions)

#### Employees (4)
- `employee.create` - Create employee
- `employee.view` - View employee details
- `employee.update` - Edit employee
- `employee.delete` - Terminate employee

#### Payroll Runs (4)
- `payroll_run.create` - Create payroll batch
- `payroll_run.view` - View payroll runs
- `payroll_run.close` - Close payroll (finalize)
- `payroll_run.delete` - Delete payroll run

#### Payslips (5)
- `payslip.create` - Create payslip
- `payslip.view` - View payslips
- `payslip.approve` - Approve payslip
- `payslip.pay` - Mark as paid
- `payslip.delete` - Delete payslip

#### Payroll Settings (2)
- `payroll.settings.view` - View payroll config
- `payroll.settings.update` - Edit payroll settings

#### Leave Requests (5)
- `leave_request.create` - Create leave request
- `leave_request.view` - View requests
- `leave_request.update` - Edit request
- `leave_request.approve` - Approve/deny request
- `leave_request.delete` - Delete request

### Banking Module (12 permissions)

#### Bank Accounts (4)
- `bank_account.create` - Add bank account
- `bank_account.view` - View bank accounts
- `bank_account.update` - Edit bank account
- `bank_account.delete` - Remove bank account

#### Bank Transactions (3)
- `bank_transaction.create` - Create transaction
- `bank_transaction.view` - View transactions
- `bank_transaction.import` - Import bank feed

#### Reconciliation (4)
- `bank_reconciliation.create` - Start reconciliation
- `bank_reconciliation.view` - View reconciliation
- `bank_reconciliation.complete` - Finalize reconciliation
- `bank_reconciliation.cancel` - Cancel reconciliation

#### Bank Feeds (2)
- `bank_feed.view` - View bank feeds
- `bank_feed.resolve` - Match/resolve feeds

#### Bank Rules (4)
- `bank_rule.create` - Create matching rule
- `bank_rule.view` - View rules
- `bank_rule.update` - Edit rule
- `bank_rule.delete` - Delete rule

### Tax Module (17 permissions)

- `tax.manage` - Full tax management
- `tax.view` - View tax config
- `tax.rate.create`, `tax.rate.update`, `tax.rate.delete` - Tax rates
- `tax.group.create`, `tax.group.update`, `tax.group.delete` - Tax groups
- `tax.registration.create`, `tax.registration.update`, `tax.registration.delete` - Tax registrations
- `tax.exemption.create`, `tax.exemption.update`, `tax.exemption.delete` - Tax exemptions
- `tax.settings.update` - Update tax settings
- `tax.calculate` - Calculate taxes

### Fuel Station Module (23 permissions) [Industry-Specific]

#### Pumps (6)
- `pump.create` - Install new pump
- `pump.view` - View pumps
- `pump.update` - Configure pump
- `pump.delete` - Decommission pump
- `pump_reading.create` - Record pump reading
- `pump_reading.view` - View readings

#### Products & Operations (1)
- `fuel_product.setup` - Configure fuel products

#### Rates & Sales (2)
- `fuel_rate.update` - Update fuel prices
- `fuel_sale.create` - Record fuel sale

#### Tank Readings (3)
- `tank_reading.create` - Record tank level
- `tank_reading.update` - Edit reading
- `tank_reading.view` - View tank readings

#### Daily Close (5)
- `daily_close.create` - Perform daily close
- `daily_close.view` - View close reports
- `daily_close.lock` - Lock close (prevent edits)
- `daily_close.unlock` - Reopen close
- `daily_close.amend` - Amend locked close

#### Handovers (2)
- `handover.create` - Create shift handover
- `handover.view` - View handovers

#### Investors (3)
- `investor.create` - Add investor
- `investor.view` - View investors
- `investor.update` - Edit investor

#### Amanat (Deposit) (2)
- `amanat.deposit` - Record deposit
- `amanat.withdraw` - Withdraw deposit

### Company & User Management (8 permissions)

- `company.create` - Create new company
- `company.view` - View company details
- `company.update` - Edit company settings
- `company.delete` - Delete company (DANGEROUS)
- `company.invite-user` - Send user invitations
- `company.manage-users` - Manage user access
- `company.delete-user` - Remove user from company
- `company.manage-roles` - Create/edit roles

### Reporting (2 permissions)

- `report.view` - Access reports
- `report.export` - Export reports

---

## How Permissions Work

### Permission Checking Flow

```
1. HTTP Request Arrives
   └─ URL: POST /{company}/invoices

2. Middleware Chain Executes
   ├─ IdentifyCompany
   │  └─ Extracts {company} from URL
   │  └─ Sets CurrentCompany singleton
   │  └─ Sets auth()->user()->setTeam($company)
   └─ Auth middleware verified user is logged in

3. FormRequest::authorize() Called
   ├─ Calls: auth()->user()->hasPermissionTo('invoice.create')
   ├─ Spatie checks user's role/permission
   └─ Returns: true or false

4. If authorize() returns false
   └─ Throw 403 Unauthorized

5. If authorize() returns true
   ├─ FormRequest::rules() validation
   └─ Controller action executes
```

### Code Example: Checking Permissions

**In FormRequest:**
```php
public function authorize(): bool
{
    return auth()->user()
        ->hasPermissionTo(Permissions::INVOICE_CREATE);
}
```

---

## Adding New Permissions

### Step 1: Define Constant

**File:** `build/app/Constants/Permissions.php`

```php
public const INVOICE_APPROVE = 'invoice.approve';
```

### Step 2: Sync to Database

```bash
php artisan rbac:sync-permissions
```

### Step 3: Map to Roles

**File:** `build/config/role-permissions.php`

```php
'Admin' => [
    Permissions::INVOICE_APPROVE,
],
```

### Step 4: Sync Mappings

```bash
php artisan rbac:sync-role-permissions
```

---

## References

- **Code:** `build/app/Constants/Permissions.php` (151 permissions)
- **Config:** `build/config/role-permissions.php`
- **Package:** [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6)
