# Accounting Module - Command Implementation Specification

## Overview

This document provides exact implementation details for all accounting-related palette commands. Each section includes the action class signature, validation rules, business logic, response format, and usage examples.

---

## Table of Contents

1. [Conventions](#conventions)
2. [Customer Commands](#customer-commands)
3. [Invoice Commands](#invoice-commands)
4. [Payment Commands](#payment-commands)
5. [Grammar Updates](#grammar-updates)
6. [Parser Inference Rules](#parser-inference-rules)
7. [Quick Actions](#quick-actions)
8. [Permissions Matrix](#permissions-matrix)

---

## Conventions

### File Locations
```
app/Actions/Customer/     → Customer actions
app/Actions/Invoice/      → Invoice actions
app/Actions/Payment/      → Payment actions
```

### Namespace Pattern
```php
namespace App\Actions\{Entity};
```

### Action Class Template
```php
<?php

namespace App\Actions\{Entity};

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Support\PaletteFormatter;

class {Verb}Action implements PaletteAction
{
    public function rules(): array
    {
        return [
            // Validation rules
        ];
    }

    public function permission(): ?string
    {
        return Permissions::{ENTITY}_{VERB};
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        // Business logic

        return [
            'message' => '...',
            'data' => [...],
            'redirect' => '...',  // Optional
        ];
    }
}
```

### Response Formats

**Mutation Response (create, update, delete, send, void):**
```php
[
    'message' => 'Human-readable success message',
    'data' => [
        'id' => 'uuid',
        // Key fields for display
    ],
    'redirect' => '/optional/url',  // Optional
    'undo' => [                      // Optional, for reversible actions
        'action' => 'entity.verb',
        'params' => [...],
        'expires_at' => timestamp,
    ],
]
```

**Query Response (list, view):**
```php
[
    'data' => PaletteFormatter::table(
        headers: ['Col1', 'Col2', ...],
        rows: [[...], [...], ...],
        footer: 'Summary text'
    ),
]
```

### Error Handling
- Throw `\Exception` for business rule violations
- Throw `\Illuminate\Validation\ValidationException` for input errors (handled by controller)
- Throw `\Illuminate\Auth\Access\AuthorizationException` for permission errors
- Throw `\Illuminate\Database\Eloquent\ModelNotFoundException` for not found

---

## Customer Commands

### 1. customer.create

**Purpose:** Create a new customer record.

**File:** `app/Actions/Customer/CreateAction.php`

**Command Syntax:**
```
customer create <name> [--email=<email>] [--phone=<phone>] [--currency=<code>] [--payment_terms=<days>]
```

**Examples:**
```
customer create "Acme Corporation"
customer create "John Doe" --email=john@example.com
cust new "Big Client" --currency=EUR --payment_terms=45
c add "Quick Customer"
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'name' => 'required|string|min:1|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'currency' => 'nullable|string|size:3|uppercase',
        'payment_terms' => 'nullable|integer|min:0|max:365',
    ];
}
```

**Permission:** `Permissions::CUSTOMER_CREATE`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    // Check for duplicate email within company (if email provided)
    if (!empty($params['email'])) {
        $existing = Customer::where('company_id', $company->id)
            ->where('email', $params['email'])
            ->exists();

        if ($existing) {
            throw new \Exception("Customer with email {$params['email']} already exists");
        }
    }

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => trim($params['name']),
        'email' => $params['email'] ?? null,
        'phone' => $params['phone'] ?? null,
        'currency_code' => strtoupper($params['currency'] ?? $company->base_currency),
        'payment_terms_days' => $params['payment_terms'] ?? 30,
        'is_active' => true,
        'created_by_user_id' => Auth::id(),
    ]);

    return [
        'message' => "Customer created: {$customer->name}",
        'data' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'currency' => $customer->currency_code,
        ],
    ];
}
```

**Response Example:**
```json
{
  "ok": true,
  "message": "Customer created: Acme Corporation",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Acme Corporation",
    "email": null,
    "currency": "USD"
  }
}
```

---

### 2. customer.list

**Purpose:** List customers with optional filtering.

**File:** `app/Actions/Customer/IndexAction.php`

**Command Syntax:**
```
customer list [--search=<term>] [--inactive] [--limit=<n>]
```

**Examples:**
```
customer list
customer list --search=acme
cust ls --inactive
c list --limit=10
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'search' => 'nullable|string|max:100',
        'inactive' => 'nullable|boolean',
        'limit' => 'nullable|integer|min:1|max:100',
    ];
}
```

**Permission:** `null` (any authenticated user can list)

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();
    $limit = $params['limit'] ?? 50;

    $query = Customer::where('company_id', $company->id)
        ->orderBy('name');

    // Filter by active status
    if (empty($params['inactive'])) {
        $query->where('is_active', true);
    }

    // Search filter
    if (!empty($params['search'])) {
        $term = $params['search'];
        $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('phone', 'ilike', "%{$term}%");
        });
    }

    $customers = $query->limit($limit)->get();

    // Calculate outstanding balances
    $customerIds = $customers->pluck('id');
    $balances = Invoice::whereIn('customer_id', $customerIds)
        ->whereIn('status', ['pending', 'sent', 'partial', 'overdue'])
        ->groupBy('customer_id')
        ->selectRaw('customer_id, SUM(balance_due) as total')
        ->pluck('total', 'customer_id');

    return [
        'data' => PaletteFormatter::table(
            headers: ['Name', 'Email', 'Phone', 'Balance', 'Status'],
            rows: $customers->map(fn($c) => [
                $c->name,
                $c->email ?? '{secondary}—{/}',
                $c->phone ?? '{secondary}—{/}',
                $this->formatBalance($balances[$c->id] ?? 0, $c->currency_code),
                $c->is_active ? '{success}● Active{/}' : '{secondary}○ Inactive{/}',
            ])->toArray(),
            footer: $customers->count() . ' customers'
        ),
    ];
}

private function formatBalance(float $amount, string $currency): string
{
    if ($amount <= 0) {
        return '{secondary}$0.00{/}';
    }
    return '{warning}' . PaletteFormatter::money($amount, $currency) . '{/}';
}
```

**Table Output:**
```
┌─────────────────┬─────────────────────┬──────────────┬───────────┬──────────┐
│ Name            │ Email               │ Phone        │ Balance   │ Status   │
├─────────────────┼─────────────────────┼──────────────┼───────────┼──────────┤
│ Acme Corp       │ billing@acme.com    │ 555-0100     │ $1,500.00 │ ● Active │
│ Big Client      │ —                   │ 555-0200     │ $0.00     │ ● Active │
│ Old Customer    │ old@example.com     │ —            │ $500.00   │ ○ Inactive│
└─────────────────┴─────────────────────┴──────────────┴───────────┴──────────┘
3 customers
```

---

### 3. customer.view

**Purpose:** Display detailed customer information.

**File:** `app/Actions/Customer/ViewAction.php`

**Command Syntax:**
```
customer view <identifier>
```
Where `identifier` can be: UUID, email, or name (fuzzy matched).

**Examples:**
```
customer view "Acme Corporation"
customer view acme@example.com
cust get 550e8400-e29b-41d4-a716-446655440000
c info acme
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'id' => 'required|string|max:255',
    ];
}
```

**Permission:** `null`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $customer = $this->resolveCustomer($params['id'], $company->id);

    // Get invoice stats
    $invoiceStats = Invoice::where('customer_id', $customer->id)
        ->selectRaw("
            COUNT(*) as total_count,
            COUNT(CASE WHEN status IN ('pending', 'sent', 'partial', 'overdue') THEN 1 END) as unpaid_count,
            SUM(total) as total_billed,
            SUM(balance_due) as total_outstanding
        ")
        ->first();

    // Get last invoice date
    $lastInvoice = Invoice::where('customer_id', $customer->id)
        ->orderBy('issue_date', 'desc')
        ->first();

    return [
        'data' => PaletteFormatter::table(
            headers: ['Field', 'Value'],
            rows: [
                ['Name', $customer->name],
                ['Email', $customer->email ?? '—'],
                ['Phone', $customer->phone ?? '—'],
                ['Currency', $customer->currency_code],
                ['Payment Terms', $customer->payment_terms_days . ' days'],
                ['Status', $customer->is_active ? '{success}Active{/}' : '{secondary}Inactive{/}'],
                ['Total Invoices', (string) $invoiceStats->total_count],
                ['Unpaid Invoices', (string) $invoiceStats->unpaid_count],
                ['Total Billed', PaletteFormatter::money($invoiceStats->total_billed ?? 0, $customer->currency_code)],
                ['Outstanding', $this->formatBalance($invoiceStats->total_outstanding ?? 0, $customer->currency_code)],
                ['Last Invoice', $lastInvoice ? $lastInvoice->issue_date->format('M j, Y') : '—'],
                ['Created', $customer->created_at->format('M j, Y')],
            ],
            footer: "Customer ID: {$customer->id}"
        ),
    ];
}

private function resolveCustomer(string $identifier, string $companyId): Customer
{
    // Try UUID
    if (Str::isUuid($identifier)) {
        $customer = Customer::where('id', $identifier)
            ->where('company_id', $companyId)
            ->first();
        if ($customer) return $customer;
    }

    // Try exact email
    $customer = Customer::where('company_id', $companyId)
        ->where('email', $identifier)
        ->first();
    if ($customer) return $customer;

    // Try exact name (case-insensitive)
    $customer = Customer::where('company_id', $companyId)
        ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
        ->first();
    if ($customer) return $customer;

    // Try fuzzy name match (requires pg_trgm extension)
    $customer = Customer::where('company_id', $companyId)
        ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
        ->orderByRaw('similarity(name, ?) DESC', [$identifier])
        ->first();
    if ($customer) return $customer;

    throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer not found: {$identifier}");
}
```

---

### 4. customer.update

**Purpose:** Update customer information.

**File:** `app/Actions/Customer/UpdateAction.php`

**Command Syntax:**
```
customer update <identifier> [--name=<name>] [--email=<email>] [--phone=<phone>] [--currency=<code>] [--payment_terms=<days>]
```

**Examples:**
```
customer update "Acme Corp" --email=new@acme.com
customer update acme --phone="555-0199"
cust edit "Big Client" --payment_terms=60
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'id' => 'required|string|max:255',
        'name' => 'nullable|string|min:1|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'currency' => 'nullable|string|size:3',
        'payment_terms' => 'nullable|integer|min:0|max:365',
    ];
}
```

**Permission:** `Permissions::CUSTOMER_UPDATE`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $customer = $this->resolveCustomer($params['id'], $company->id);

    $updates = [];
    $changes = [];

    if (isset($params['name']) && $params['name'] !== $customer->name) {
        $updates['name'] = trim($params['name']);
        $changes[] = "name → {$params['name']}";
    }

    if (isset($params['email']) && $params['email'] !== $customer->email) {
        // Check for duplicate
        if ($params['email']) {
            $existing = Customer::where('company_id', $company->id)
                ->where('email', $params['email'])
                ->where('id', '!=', $customer->id)
                ->exists();
            if ($existing) {
                throw new \Exception("Email {$params['email']} is already used by another customer");
            }
        }
        $updates['email'] = $params['email'] ?: null;
        $changes[] = "email → " . ($params['email'] ?: 'removed');
    }

    if (isset($params['phone'])) {
        $updates['phone'] = $params['phone'] ?: null;
        $changes[] = "phone → " . ($params['phone'] ?: 'removed');
    }

    if (isset($params['currency'])) {
        $updates['currency_code'] = strtoupper($params['currency']);
        $changes[] = "currency → {$params['currency']}";
    }

    if (isset($params['payment_terms'])) {
        $updates['payment_terms_days'] = $params['payment_terms'];
        $changes[] = "payment terms → {$params['payment_terms']} days";
    }

    if (empty($updates)) {
        throw new \Exception('No changes specified');
    }

    $customer->update($updates);

    return [
        'message' => "Customer updated: {$customer->name}",
        'data' => [
            'id' => $customer->id,
            'changes' => $changes,
        ],
    ];
}
```

---

### 5. customer.delete

**Purpose:** Soft-delete a customer (deactivate).

**File:** `app/Actions/Customer/DeleteAction.php`

**Command Syntax:**
```
customer delete <identifier>
```

**Examples:**
```
customer delete "Old Customer"
cust rm old@example.com
c del 550e8400-e29b-41d4-a716-446655440000
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'id' => 'required|string|max:255',
    ];
}
```

**Permission:** `Permissions::CUSTOMER_DELETE`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $customer = $this->resolveCustomer($params['id'], $company->id);

    // Check for unpaid invoices
    $unpaidCount = Invoice::where('customer_id', $customer->id)
        ->whereIn('status', ['pending', 'sent', 'partial', 'overdue'])
        ->count();

    if ($unpaidCount > 0) {
        throw new \Exception(
            "Cannot delete customer with {$unpaidCount} unpaid invoice(s). " .
            "Void or collect payment first."
        );
    }

    // Soft delete (deactivate)
    $customer->update(['is_active' => false]);

    return [
        'message' => "Customer deleted: {$customer->name}",
        'data' => [
            'id' => $customer->id,
            'name' => $customer->name,
        ],
    ];
}
```

---

### 6. customer.restore

**Purpose:** Restore a soft-deleted customer.

**File:** `app/Actions/Customer/RestoreAction.php`

**Command Syntax:**
```
customer restore <identifier>
```

**Examples:**
```
customer restore "Old Customer"
cust restore old@example.com
```

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    // Search including inactive
    $customer = Customer::where('company_id', $company->id)
        ->where('is_active', false)
        ->where(function ($q) use ($params) {
            $q->where('id', $params['id'])
              ->orWhere('email', $params['id'])
              ->orWhereRaw('LOWER(name) = ?', [strtolower($params['id'])]);
        })
        ->firstOrFail();

    $customer->update(['is_active' => true]);

    return [
        'message' => "Customer restored: {$customer->name}",
        'data' => ['id' => $customer->id],
    ];
}
```

---

## Invoice Commands

### 1. invoice.create

**Purpose:** Create a new invoice.

**File:** `app/Actions/Invoice/CreateAction.php`

**Command Syntax:**
```
invoice create <customer> <amount> [--due=<date>] [--description=<text>] [--draft] [--reference=<ref>]
```

**Examples:**
```
invoice create "Acme Corp" 1500
invoice create acme 2500.50 --due=2024-02-15
inv new "Big Client" 10000 --draft --description="Consulting services"
i add acme 500 --reference="PO-12345"
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'customer' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0.01|max:999999999.99',
        'due' => 'nullable|date|after_or_equal:today',
        'description' => 'nullable|string|max:1000',
        'draft' => 'nullable|boolean',
        'reference' => 'nullable|string|max:100',
    ];
}
```

**Permission:** `Permissions::INVOICE_CREATE`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    // Resolve customer (UUID, email, or fuzzy name match)
    $customer = $this->resolveCustomer($params['customer'], $company->id);

    return DB::transaction(function () use ($params, $company, $customer) {
        // Calculate due date
        $issueDate = now();
        $dueDate = !empty($params['due'])
            ? Carbon::parse($params['due'])
            : $issueDate->copy()->addDays($customer->payment_terms_days);

        // Generate invoice number
        $invoiceNumber = app(InvoiceNumberGenerator::class)->next($company);

        // Determine status
        $status = ($params['draft'] ?? false)
            ? Invoice::STATUS_DRAFT
            : Invoice::STATUS_PENDING;

        // Create invoice
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'reference' => $params['reference'] ?? null,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'subtotal' => $params['amount'],
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $params['amount'],
            'amount_paid' => 0,
            'balance_due' => $params['amount'],
            'currency_code' => $customer->currency_code,
            'exchange_rate' => 1.0,
            'status' => $status,
            'notes' => $params['description'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        // Create line item if description provided
        if (!empty($params['description'])) {
            $invoice->lines()->create([
                'description' => $params['description'],
                'quantity' => 1,
                'unit_price' => $params['amount'],
                'amount' => $params['amount'],
                'sort_order' => 0,
            ]);
        }

        $statusLabel = $status === Invoice::STATUS_DRAFT ? 'Draft' : 'Pending';

        return [
            'message' => "Invoice {$invoiceNumber} created ({$statusLabel}) for {$customer->name}",
            'data' => [
                'id' => $invoice->id,
                'number' => $invoiceNumber,
                'customer' => $customer->name,
                'total' => PaletteFormatter::money($invoice->total, $invoice->currency_code),
                'due_date' => $dueDate->format('M j, Y'),
                'status' => $status,
            ],
            'redirect' => "/{$company->slug}/invoices/{$invoice->id}",
        ];
    });
}

private function resolveCustomer(string $identifier, string $companyId): Customer
{
    // Try UUID
    if (Str::isUuid($identifier)) {
        $customer = Customer::where('id', $identifier)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();
        if ($customer) return $customer;
    }

    // Try exact email
    $customer = Customer::where('company_id', $companyId)
        ->where('email', $identifier)
        ->where('is_active', true)
        ->first();
    if ($customer) return $customer;

    // Try exact name (case-insensitive)
    $customer = Customer::where('company_id', $companyId)
        ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
        ->where('is_active', true)
        ->first();
    if ($customer) return $customer;

    // Try fuzzy match
    $customer = Customer::where('company_id', $companyId)
        ->where('is_active', true)
        ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
        ->orderByRaw('similarity(name, ?) DESC', [$identifier])
        ->first();
    if ($customer) return $customer;

    throw new \Exception("Customer not found: {$identifier}. Create with: customer create \"{$identifier}\"");
}
```

---

### 2. invoice.list

**Purpose:** List invoices with filtering.

**File:** `app/Actions/Invoice/IndexAction.php`

**Command Syntax:**
```
invoice list [--status=<status>] [--customer=<name>] [--unpaid] [--overdue] [--from=<date>] [--to=<date>] [--limit=<n>]
```

**Status Values:** `draft`, `pending`, `sent`, `partial`, `paid`, `overdue`, `void`

**Examples:**
```
invoice list
invoice list --unpaid
invoice list --overdue
invoice list --status=draft
invoice list --customer="Acme"
invoice list --from=2024-01-01 --to=2024-01-31
inv ls --unpaid --limit=10
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'status' => 'nullable|string|in:draft,pending,sent,partial,paid,overdue,void',
        'customer' => 'nullable|string|max:255',
        'unpaid' => 'nullable|boolean',
        'overdue' => 'nullable|boolean',
        'from' => 'nullable|date',
        'to' => 'nullable|date|after_or_equal:from',
        'limit' => 'nullable|integer|min:1|max:100',
    ];
}
```

**Permission:** `null`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();
    $limit = $params['limit'] ?? 50;

    $query = Invoice::with('customer')
        ->where('company_id', $company->id)
        ->orderBy('issue_date', 'desc')
        ->orderBy('invoice_number', 'desc');

    // Status filter
    if (!empty($params['status'])) {
        $query->where('status', $params['status']);
    }

    // Unpaid shorthand (pending + sent + partial + overdue)
    if (!empty($params['unpaid']) && $params['unpaid']) {
        $query->whereIn('status', [
            Invoice::STATUS_PENDING,
            Invoice::STATUS_SENT,
            Invoice::STATUS_PARTIAL,
            Invoice::STATUS_OVERDUE,
        ]);
    }

    // Overdue filter
    if (!empty($params['overdue']) && $params['overdue']) {
        $query->where('status', '!=', Invoice::STATUS_PAID)
              ->where('status', '!=', Invoice::STATUS_VOID)
              ->where('status', '!=', Invoice::STATUS_DRAFT)
              ->where('due_date', '<', now()->startOfDay());
    }

    // Customer filter
    if (!empty($params['customer'])) {
        $query->whereHas('customer', function ($q) use ($params) {
            $q->where('name', 'ilike', "%{$params['customer']}%");
        });
    }

    // Date range
    if (!empty($params['from'])) {
        $query->where('issue_date', '>=', $params['from']);
    }
    if (!empty($params['to'])) {
        $query->where('issue_date', '<=', $params['to']);
    }

    $invoices = $query->limit($limit)->get();

    // Calculate totals
    $totalOutstanding = $invoices->sum('balance_due');

    return [
        'data' => PaletteFormatter::table(
            headers: ['Number', 'Customer', 'Amount', 'Due', 'Status'],
            rows: $invoices->map(fn($inv) => [
                $inv->invoice_number,
                Str::limit($inv->customer->name, 20),
                PaletteFormatter::money($inv->total, $inv->currency_code),
                PaletteFormatter::relativeDate($inv->due_date),
                $this->formatStatus($inv),
            ])->toArray(),
            footer: $invoices->count() . ' invoices · ' .
                    PaletteFormatter::money($totalOutstanding, $company->base_currency) . ' outstanding'
        ),
    ];
}

private function formatStatus(Invoice $invoice): string
{
    // Check if overdue
    $isOverdue = !in_array($invoice->status, [
        Invoice::STATUS_PAID,
        Invoice::STATUS_VOID,
        Invoice::STATUS_DRAFT,
    ]) && $invoice->due_date->isPast();

    if ($isOverdue) {
        return '{error}⚠ Overdue{/}';
    }

    return match ($invoice->status) {
        Invoice::STATUS_DRAFT => '{secondary}○ Draft{/}',
        Invoice::STATUS_PENDING => '{warning}◐ Pending{/}',
        Invoice::STATUS_SENT => '{accent}◑ Sent{/}',
        Invoice::STATUS_PARTIAL => '{warning}◕ Partial{/}',
        Invoice::STATUS_PAID => '{success}● Paid{/}',
        Invoice::STATUS_VOID => '{secondary}✗ Void{/}',
        default => $invoice->status,
    };
}
```

---

### 3. invoice.view

**Purpose:** Display detailed invoice information.

**File:** `app/Actions/Invoice/ViewAction.php`

**Command Syntax:**
```
invoice view <number|id>
```

**Examples:**
```
invoice view INV-00001
invoice view 550e8400-e29b-41d4-a716-446655440000
inv get INV-00001
i info 00001
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'id' => 'required|string|max:255',
    ];
}
```

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $invoice = $this->resolveInvoice($params['id'], $company->id);
    $invoice->load('customer', 'lines');

    // Get payment history
    $payments = Payment::where('invoice_id', $invoice->id)
        ->orderBy('payment_date', 'desc')
        ->get();

    $rows = [
        ['Invoice Number', $invoice->invoice_number],
        ['Customer', $invoice->customer->name],
        ['Status', $this->formatStatusLong($invoice)],
        ['Issue Date', $invoice->issue_date->format('M j, Y')],
        ['Due Date', $invoice->due_date->format('M j, Y') .
            ($invoice->due_date->isPast() && $invoice->balance_due > 0 ? ' {error}(OVERDUE){/}' : '')],
        ['Reference', $invoice->reference ?? '—'],
        ['', ''],  // Spacer
        ['Subtotal', PaletteFormatter::money($invoice->subtotal, $invoice->currency_code)],
        ['Tax', PaletteFormatter::money($invoice->tax_amount, $invoice->currency_code)],
        ['Discount', $invoice->discount_amount > 0
            ? '-' . PaletteFormatter::money($invoice->discount_amount, $invoice->currency_code)
            : '—'],
        ['{bold}Total{/}', '{bold}' . PaletteFormatter::money($invoice->total, $invoice->currency_code) . '{/}'],
        ['Amount Paid', PaletteFormatter::money($invoice->amount_paid, $invoice->currency_code)],
        ['{bold}Balance Due{/}', $invoice->balance_due > 0
            ? '{warning}' . PaletteFormatter::money($invoice->balance_due, $invoice->currency_code) . '{/}'
            : '{success}$0.00{/}'],
    ];

    // Add line items if present
    if ($invoice->lines->isNotEmpty()) {
        $rows[] = ['', ''];  // Spacer
        $rows[] = ['{bold}Line Items{/}', ''];
        foreach ($invoice->lines as $i => $line) {
            $rows[] = [
                "  " . ($i + 1) . ". " . Str::limit($line->description, 30),
                PaletteFormatter::money($line->amount, $invoice->currency_code),
            ];
        }
    }

    // Add payment history if present
    if ($payments->isNotEmpty()) {
        $rows[] = ['', ''];
        $rows[] = ['{bold}Payments{/}', ''];
        foreach ($payments as $payment) {
            $rows[] = [
                "  " . $payment->payment_date->format('M j') . " ({$payment->method})",
                PaletteFormatter::money($payment->amount, $invoice->currency_code),
            ];
        }
    }

    return [
        'data' => PaletteFormatter::table(
            headers: ['Field', 'Value'],
            rows: $rows,
            footer: "Invoice ID: {$invoice->id}"
        ),
    ];
}

private function resolveInvoice(string $identifier, string $companyId): Invoice
{
    // Try UUID
    if (Str::isUuid($identifier)) {
        $invoice = Invoice::where('id', $identifier)
            ->where('company_id', $companyId)
            ->first();
        if ($invoice) return $invoice;
    }

    // Try invoice number (exact)
    $invoice = Invoice::where('company_id', $companyId)
        ->where('invoice_number', $identifier)
        ->first();
    if ($invoice) return $invoice;

    // Try partial number match (e.g., "00001" matches "INV-00001")
    $invoice = Invoice::where('company_id', $companyId)
        ->where('invoice_number', 'like', "%{$identifier}")
        ->first();
    if ($invoice) return $invoice;

    throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Invoice not found: {$identifier}");
}

private function formatStatusLong(Invoice $invoice): string
{
    $isOverdue = !in_array($invoice->status, [
        Invoice::STATUS_PAID,
        Invoice::STATUS_VOID,
        Invoice::STATUS_DRAFT,
    ]) && $invoice->due_date->isPast();

    if ($isOverdue) {
        $days = $invoice->due_date->diffInDays(now());
        return "{error}Overdue by {$days} days{/}";
    }

    return match ($invoice->status) {
        Invoice::STATUS_DRAFT => '{secondary}Draft{/}',
        Invoice::STATUS_PENDING => '{warning}Pending{/}',
        Invoice::STATUS_SENT => '{accent}Sent{/}',
        Invoice::STATUS_PARTIAL => '{warning}Partially Paid{/}',
        Invoice::STATUS_PAID => '{success}Paid in Full{/}',
        Invoice::STATUS_VOID => '{secondary}Voided{/}',
        default => $invoice->status,
    };
}
```

---

### 4. invoice.send

**Purpose:** Mark invoice as sent and optionally email to customer.

**File:** `app/Actions/Invoice/SendAction.php`

**Command Syntax:**
```
invoice send <number> [--email] [--to=<email>]
```

**Examples:**
```
invoice send INV-00001
invoice send INV-00001 --email
inv send 00001 --to=billing@customer.com
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'id' => 'required|string|max:255',
        'email' => 'nullable|boolean',
        'to' => 'nullable|email',
    ];
}
```

**Permission:** `Permissions::INVOICE_SEND`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $invoice = $this->resolveInvoice($params['id'], $company->id);

    // Validate status
    if ($invoice->status === Invoice::STATUS_VOID) {
        throw new \Exception("Cannot send voided invoice");
    }

    if ($invoice->status === Invoice::STATUS_PAID) {
        throw new \Exception("Invoice is already paid");
    }

    // Update status if draft or pending
    if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_PENDING])) {
        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    // Send email if requested
    $emailSent = false;
    if (($params['email'] ?? false) || !empty($params['to'])) {
        $recipientEmail = $params['to'] ?? $invoice->customer->email;

        if (!$recipientEmail) {
            throw new \Exception("No email address. Specify with --to=email@example.com");
        }

        // Dispatch email job
        dispatch(new SendInvoiceEmail($invoice, $recipientEmail));
        $emailSent = true;
    }

    $message = "Invoice {$invoice->invoice_number} marked as sent";
    if ($emailSent) {
        $message .= " and emailed to {$recipientEmail}";
    }

    return [
        'message' => $message,
        'data' => [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'emailed' => $emailSent,
        ],
    ];
}
```

---

### 5. invoice.void

**Purpose:** Void an invoice (cannot be reversed).

**File:** `app/Actions/Invoice/VoidAction.php`

**Command Syntax:**
```
invoice void <number> [--reason=<text>]
```

**Examples:**
```
invoice void INV-00001
invoice void INV-00001 --reason="Duplicate invoice"
inv void 00001 --reason="Customer cancelled order"
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'id' => 'required|string|max:255',
        'reason' => 'nullable|string|max:500',
    ];
}
```

**Permission:** `Permissions::INVOICE_VOID`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $invoice = $this->resolveInvoice($params['id'], $company->id);

    // Validate current status
    if ($invoice->status === Invoice::STATUS_VOID) {
        throw new \Exception("Invoice is already voided");
    }

    if ($invoice->status === Invoice::STATUS_PAID) {
        throw new \Exception("Cannot void a paid invoice. Create a credit note instead.");
    }

    // Check for payments
    if ($invoice->amount_paid > 0) {
        throw new \Exception(
            "Invoice has payments totaling " .
            PaletteFormatter::money($invoice->amount_paid, $invoice->currency_code) .
            ". Refund payments first."
        );
    }

    $invoice->update([
        'status' => Invoice::STATUS_VOID,
        'voided_at' => now(),
        'voided_reason' => $params['reason'] ?? null,
        'balance_due' => 0,
    ]);

    return [
        'message' => "Invoice {$invoice->invoice_number} voided",
        'data' => [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'reason' => $params['reason'] ?? null,
        ],
    ];
}
```

---

### 6. invoice.duplicate

**Purpose:** Create a copy of an existing invoice.

**File:** `app/Actions/Invoice/DuplicateAction.php`

**Command Syntax:**
```
invoice duplicate <number> [--customer=<name>] [--draft]
```

**Examples:**
```
invoice duplicate INV-00001
invoice duplicate INV-00001 --customer="Different Client"
inv dup 00001 --draft
```

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $source = $this->resolveInvoice($params['id'], $company->id);
    $source->load('lines');

    // Determine customer
    $customer = !empty($params['customer'])
        ? $this->resolveCustomer($params['customer'], $company->id)
        : $source->customer;

    return DB::transaction(function () use ($params, $company, $source, $customer) {
        $newNumber = app(InvoiceNumberGenerator::class)->next($company);

        $status = ($params['draft'] ?? false)
            ? Invoice::STATUS_DRAFT
            : Invoice::STATUS_PENDING;

        // Create new invoice
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => $newNumber,
            'reference' => null,
            'issue_date' => now(),
            'due_date' => now()->addDays($customer->payment_terms_days),
            'subtotal' => $source->subtotal,
            'tax_amount' => $source->tax_amount,
            'discount_amount' => $source->discount_amount,
            'total' => $source->total,
            'amount_paid' => 0,
            'balance_due' => $source->total,
            'currency_code' => $customer->currency_code,
            'exchange_rate' => 1.0,
            'status' => $status,
            'notes' => $source->notes,
            'terms' => $source->terms,
            'footer' => $source->footer,
            'created_by_user_id' => Auth::id(),
        ]);

        // Copy line items
        foreach ($source->lines as $line) {
            $invoice->lines()->create([
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
                'account_id' => $line->account_id,
                'tax_rate_id' => $line->tax_rate_id,
                'discount_percent' => $line->discount_percent,
                'sort_order' => $line->sort_order,
            ]);
        }

        return [
            'message' => "Invoice duplicated: {$source->invoice_number} → {$newNumber}",
            'data' => [
                'id' => $invoice->id,
                'number' => $newNumber,
                'source_number' => $source->invoice_number,
                'customer' => $customer->name,
                'total' => PaletteFormatter::money($invoice->total, $invoice->currency_code),
            ],
        ];
    });
}
```

---

## Payment Commands

### 1. payment.create

**Purpose:** Record a payment against an invoice.

**File:** `app/Actions/Payment/CreateAction.php`

**Command Syntax:**
```
payment create <invoice> <amount> [--method=<method>] [--date=<date>] [--reference=<ref>] [--notes=<text>]
```

**Method Values:** `cash`, `check`, `card`, `bank_transfer`, `other` (default: `bank_transfer`)

**Examples:**
```
payment create INV-00001 500
payment create INV-00001 1500 --method=card
pay new 00001 250.50 --date=2024-01-15 --reference="CHK-123"
p add INV-00001 1000 --method=check --notes="Partial payment"
```

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'invoice' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0.01|max:999999999.99',
        'method' => 'nullable|string|in:cash,check,card,bank_transfer,other',
        'date' => 'nullable|date|before_or_equal:today',
        'reference' => 'nullable|string|max:100',
        'notes' => 'nullable|string|max:500',
    ];
}
```

**Permission:** `Permissions::PAYMENT_CREATE`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $invoice = $this->resolveInvoice($params['invoice'], $company->id);

    // Validate invoice status
    if ($invoice->status === Invoice::STATUS_VOID) {
        throw new \Exception("Cannot record payment on voided invoice");
    }

    if ($invoice->status === Invoice::STATUS_DRAFT) {
        throw new \Exception("Cannot record payment on draft invoice. Send it first.");
    }

    if ($invoice->status === Invoice::STATUS_PAID) {
        throw new \Exception("Invoice is already fully paid");
    }

    $amount = (float) $params['amount'];

    // Warn if overpaying
    if ($amount > $invoice->balance_due) {
        throw new \Exception(
            "Payment amount ({$amount}) exceeds balance due " .
            "(" . PaletteFormatter::money($invoice->balance_due, $invoice->currency_code) . "). " .
            "Maximum payment: " . PaletteFormatter::money($invoice->balance_due, $invoice->currency_code)
        );
    }

    return DB::transaction(function () use ($params, $company, $invoice, $amount) {
        $paymentDate = !empty($params['date'])
            ? Carbon::parse($params['date'])
            : now();

        // Create payment record
        $payment = Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $amount,
            'currency_code' => $invoice->currency_code,
            'method' => $params['method'] ?? 'bank_transfer',
            'reference' => $params['reference'] ?? null,
            'payment_date' => $paymentDate,
            'notes' => $params['notes'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        // Update invoice
        $newAmountPaid = $invoice->amount_paid + $amount;
        $newBalanceDue = $invoice->total - $newAmountPaid;

        $newStatus = $newBalanceDue <= 0
            ? Invoice::STATUS_PAID
            : Invoice::STATUS_PARTIAL;

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'balance_due' => max(0, $newBalanceDue),
            'status' => $newStatus,
            'paid_at' => $newStatus === Invoice::STATUS_PAID ? now() : null,
        ]);

        $statusMsg = $newStatus === Invoice::STATUS_PAID
            ? '{success}Paid in full{/}'
            : PaletteFormatter::money($newBalanceDue, $invoice->currency_code) . ' remaining';

        return [
            'message' => "Payment recorded: " .
                PaletteFormatter::money($amount, $invoice->currency_code) .
                " on {$invoice->invoice_number} — {$statusMsg}",
            'data' => [
                'id' => $payment->id,
                'invoice' => $invoice->invoice_number,
                'amount' => PaletteFormatter::money($amount, $invoice->currency_code),
                'balance_due' => PaletteFormatter::money(max(0, $newBalanceDue), $invoice->currency_code),
                'status' => $newStatus,
            ],
        ];
    });
}
```

---

### 2. payment.list

**Purpose:** List payments with filtering.

**File:** `app/Actions/Payment/IndexAction.php`

**Command Syntax:**
```
payment list [--invoice=<number>] [--customer=<name>] [--method=<method>] [--from=<date>] [--to=<date>] [--limit=<n>]
```

**Examples:**
```
payment list
payment list --invoice=INV-00001
payment list --customer="Acme"
payment list --from=2024-01-01 --to=2024-01-31
pay ls --method=check
```

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();
    $limit = $params['limit'] ?? 50;

    $query = Payment::with(['invoice', 'customer'])
        ->where('company_id', $company->id)
        ->orderBy('payment_date', 'desc');

    // Invoice filter
    if (!empty($params['invoice'])) {
        $invoice = $this->resolveInvoice($params['invoice'], $company->id);
        $query->where('invoice_id', $invoice->id);
    }

    // Customer filter
    if (!empty($params['customer'])) {
        $query->whereHas('customer', function ($q) use ($params) {
            $q->where('name', 'ilike', "%{$params['customer']}%");
        });
    }

    // Method filter
    if (!empty($params['method'])) {
        $query->where('method', $params['method']);
    }

    // Date range
    if (!empty($params['from'])) {
        $query->where('payment_date', '>=', $params['from']);
    }
    if (!empty($params['to'])) {
        $query->where('payment_date', '<=', $params['to']);
    }

    $payments = $query->limit($limit)->get();
    $totalAmount = $payments->sum('amount');

    return [
        'data' => PaletteFormatter::table(
            headers: ['Date', 'Invoice', 'Customer', 'Method', 'Amount'],
            rows: $payments->map(fn($p) => [
                $p->payment_date->format('M j, Y'),
                $p->invoice->invoice_number,
                Str::limit($p->customer->name, 15),
                ucfirst(str_replace('_', ' ', $p->method)),
                PaletteFormatter::money($p->amount, $p->currency_code),
            ])->toArray(),
            footer: $payments->count() . ' payments · ' .
                    PaletteFormatter::money($totalAmount, $company->base_currency) . ' total'
        ),
    ];
}
```

---

### 3. payment.void

**Purpose:** Void a payment (reverse it).

**File:** `app/Actions/Payment/VoidAction.php`

**Command Syntax:**
```
payment void <id> [--reason=<text>]
```

**Examples:**
```
payment void 550e8400-e29b-41d4-a716-446655440000
pay void abc123 --reason="Bounced check"
```

**Permission:** `Permissions::PAYMENT_VOID`

**Business Logic:**
```php
public function handle(array $params): array
{
    $company = CompanyContext::requireCompany();

    $payment = Payment::where('company_id', $company->id)
        ->where('id', $params['id'])
        ->firstOrFail();

    if ($payment->is_voided) {
        throw new \Exception("Payment is already voided");
    }

    $invoice = $payment->invoice;

    return DB::transaction(function () use ($params, $payment, $invoice) {
        // Void the payment
        $payment->update([
            'is_voided' => true,
            'voided_at' => now(),
            'voided_reason' => $params['reason'] ?? null,
        ]);

        // Update invoice amounts
        $newAmountPaid = $invoice->amount_paid - $payment->amount;
        $newBalanceDue = $invoice->total - $newAmountPaid;

        // Determine new status
        $newStatus = Invoice::STATUS_PENDING;
        if ($newAmountPaid > 0) {
            $newStatus = Invoice::STATUS_PARTIAL;
        }
        if ($invoice->due_date->isPast()) {
            $newStatus = Invoice::STATUS_OVERDUE;
        }

        $invoice->update([
            'amount_paid' => max(0, $newAmountPaid),
            'balance_due' => $newBalanceDue,
            'status' => $newStatus,
            'paid_at' => null,
        ]);

        return [
            'message' => "Payment voided: " .
                PaletteFormatter::money($payment->amount, $payment->currency_code) .
                " on {$invoice->invoice_number}. New balance: " .
                PaletteFormatter::money($newBalanceDue, $invoice->currency_code),
            'data' => [
                'id' => $payment->id,
                'invoice' => $invoice->invoice_number,
                'amount_voided' => PaletteFormatter::money($payment->amount, $payment->currency_code),
                'new_balance' => PaletteFormatter::money($newBalanceDue, $invoice->currency_code),
            ],
        ];
    });
}
```

---

## Grammar Updates

Add to `resources/js/palette/grammar.ts`:

```typescript
export const ENTITY_ICONS: Record<string, string> = {
  company: '🏢',
  user: '👤',
  role: '🔑',
  customer: '👥',
  invoice: '📄',
  payment: '💰',
}

export const COMMAND_DESCRIPTIONS: Record<string, string> = {
  // ... existing ...

  // Customer
  'customer.create': 'Create a new customer',
  'customer.list': 'List all customers',
  'customer.view': 'View customer details and stats',
  'customer.update': 'Update customer information',
  'customer.delete': 'Deactivate a customer',
  'customer.restore': 'Restore a deactivated customer',

  // Invoice
  'invoice.create': 'Create a new invoice',
  'invoice.list': 'List invoices (filter by status, customer)',
  'invoice.view': 'View invoice details and payments',
  'invoice.send': 'Mark as sent / email to customer',
  'invoice.void': 'Void an invoice',
  'invoice.duplicate': 'Create a copy of an invoice',

  // Payment
  'payment.create': 'Record a payment on an invoice',
  'payment.list': 'List payment history',
  'payment.void': 'Void/reverse a payment',
}

export const GRAMMAR: Record<string, EntityDefinition> = {
  // ... existing company, user, role ...

  customer: {
    name: 'customer',
    shortcuts: ['cust', 'c'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        flags: [
          { name: 'name', type: 'string', required: true },
          { name: 'email', type: 'string', required: false },
          { name: 'phone', type: 'string', required: false },
          { name: 'currency', type: 'string', required: false },
          { name: 'payment_terms', shorthand: 't', type: 'number', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        flags: [
          { name: 'search', shorthand: 's', type: 'string', required: false },
          { name: 'inactive', type: 'boolean', required: false },
          { name: 'limit', type: 'number', required: false },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
      {
        name: 'update',
        aliases: ['edit', 'modify'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'name', type: 'string', required: false },
          { name: 'email', type: 'string', required: false },
          { name: 'phone', type: 'string', required: false },
          { name: 'currency', type: 'string', required: false },
          { name: 'payment_terms', shorthand: 't', type: 'number', required: false },
        ],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
      {
        name: 'restore',
        aliases: ['undelete', 'reactivate'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
    ],
  },

  invoice: {
    name: 'invoice',
    shortcuts: ['inv', 'i'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        flags: [
          { name: 'customer', shorthand: 'c', type: 'string', required: true },
          { name: 'amount', shorthand: 'a', type: 'number', required: true },
          { name: 'due', shorthand: 'd', type: 'string', required: false },
          { name: 'description', type: 'string', required: false },
          { name: 'reference', shorthand: 'r', type: 'string', required: false },
          { name: 'draft', type: 'boolean', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        flags: [
          { name: 'status', shorthand: 's', type: 'string', required: false },
          { name: 'customer', shorthand: 'c', type: 'string', required: false },
          { name: 'unpaid', type: 'boolean', required: false },
          { name: 'overdue', type: 'boolean', required: false },
          { name: 'from', type: 'string', required: false },
          { name: 'to', type: 'string', required: false },
          { name: 'limit', type: 'number', required: false },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
      {
        name: 'send',
        aliases: ['email', 'deliver'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'email', type: 'boolean', required: false },
          { name: 'to', type: 'string', required: false },
        ],
      },
      {
        name: 'void',
        aliases: ['cancel'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'reason', type: 'string', required: false },
        ],
      },
      {
        name: 'duplicate',
        aliases: ['dup', 'copy', 'clone'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'customer', shorthand: 'c', type: 'string', required: false },
          { name: 'draft', type: 'boolean', required: false },
        ],
      },
    ],
  },

  payment: {
    name: 'payment',
    shortcuts: ['pay', 'p'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add', 'record'],
        requiresSubject: true,
        flags: [
          { name: 'invoice', shorthand: 'i', type: 'string', required: true },
          { name: 'amount', shorthand: 'a', type: 'number', required: true },
          { name: 'method', shorthand: 'm', type: 'string', required: false },
          { name: 'date', shorthand: 'd', type: 'string', required: false },
          { name: 'reference', shorthand: 'r', type: 'string', required: false },
          { name: 'notes', type: 'string', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        flags: [
          { name: 'invoice', shorthand: 'i', type: 'string', required: false },
          { name: 'customer', shorthand: 'c', type: 'string', required: false },
          { name: 'method', shorthand: 'm', type: 'string', required: false },
          { name: 'from', type: 'string', required: false },
          { name: 'to', type: 'string', required: false },
          { name: 'limit', type: 'number', required: false },
        ],
      },
      {
        name: 'void',
        aliases: ['cancel', 'reverse'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'reason', type: 'string', required: false },
        ],
      },
    ],
  },
}
```

---

## Parser Inference Rules

Add to `resources/js/palette/parser.ts` in `inferFromSubject()`:

```typescript
function inferFromSubject(result: ParsedCommand): void {
  if (!result.subject) return

  const words = result.subject.split(/\s+/)

  // ... existing company/user inference ...

  // ═══════════════════════════════════════════════════════════════
  // CUSTOMER INFERENCE
  // ═══════════════════════════════════════════════════════════════

  // customer create: "customer create Acme Corporation"
  if (result.entity === 'customer' && result.verb === 'create') {
    if (!result.flags.name && words.length > 0) {
      // Check if last word looks like an email
      const lastWord = words[words.length - 1]
      if (lastWord.includes('@') && !result.flags.email) {
        result.flags.email = lastWord
        result.flags.name = words.slice(0, -1).join(' ')
      } else {
        result.flags.name = words.join(' ')
      }
    }
  }

  // customer view/update/delete: "customer view Acme" or "customer view acme@example.com"
  if (result.entity === 'customer' && ['view', 'update', 'delete', 'restore'].includes(result.verb)) {
    if (!result.flags.id && words.length > 0) {
      result.flags.id = words.join(' ')
    }
  }

  // ═══════════════════════════════════════════════════════════════
  // INVOICE INFERENCE
  // ═══════════════════════════════════════════════════════════════

  // invoice create: "invoice create Acme 1500" or "inv new acme 2500.50"
  if (result.entity === 'invoice' && result.verb === 'create') {
    // Find amount (last numeric value)
    for (let i = words.length - 1; i >= 0; i--) {
      const word = words[i]
      // Match: 1500, 2500.50, $1,500.00, etc.
      if (/^\$?[\d,]+\.?\d*$/.test(word) && !result.flags.amount) {
        result.flags.amount = parseFloat(word.replace(/[$,]/g, ''))
        words.splice(i, 1)
        break
      }
    }

    // Remaining words are customer name
    if (!result.flags.customer && words.length > 0) {
      result.flags.customer = words.join(' ')
    }
  }

  // invoice view/send/void/duplicate: "invoice view INV-00001" or "inv void 00001"
  if (result.entity === 'invoice' && ['view', 'send', 'void', 'duplicate'].includes(result.verb)) {
    if (!result.flags.id && words.length > 0) {
      result.flags.id = words[0]
    }
  }

  // ═══════════════════════════════════════════════════════════════
  // PAYMENT INFERENCE
  // ═══════════════════════════════════════════════════════════════

  // payment create: "payment create INV-00001 500" or "pay new 00001 250.50"
  if (result.entity === 'payment' && result.verb === 'create') {
    // First word is invoice
    if (words.length >= 1 && !result.flags.invoice) {
      result.flags.invoice = words[0]
    }

    // Second word is amount
    if (words.length >= 2 && !result.flags.amount) {
      const amountStr = words[1]
      if (/^\$?[\d,]+\.?\d*$/.test(amountStr)) {
        result.flags.amount = parseFloat(amountStr.replace(/[$,]/g, ''))
      }
    }
  }

  // payment void: "payment void <id>"
  if (result.entity === 'payment' && result.verb === 'void') {
    if (!result.flags.id && words.length > 0) {
      result.flags.id = words[0]
    }
  }
}
```

---

## Quick Actions

Add to `resources/js/palette/quick-actions.ts`:

```typescript
case 'customer.list':
  return [
    {
      key: '1',
      label: 'View customer',
      command: 'customer view {name}',
      needsRow: true,
    },
    {
      key: '2',
      label: 'Create invoice',
      command: 'invoice create {name}',
      needsRow: true,
      prompt: 'Enter amount',
    },
    {
      key: '3',
      label: 'View invoices',
      command: 'invoice list --customer={name}',
      needsRow: true,
    },
    {
      key: '4',
      label: 'Edit customer',
      command: 'customer update {name}',
      needsRow: true,
      prompt: 'Enter field and value (e.g., --email=new@example.com)',
    },
    {
      key: '9',
      label: 'Create customer',
      command: 'customer create',
      needsRow: false,
      prompt: 'Enter customer name',
    },
    {
      key: '0',
      label: 'Delete customer',
      command: 'customer delete {name}',
      needsRow: true,
    },
  ]

case 'invoice.list':
  return [
    {
      key: '1',
      label: 'View invoice',
      command: 'invoice view {number}',
      needsRow: true,
    },
    {
      key: '2',
      label: 'Record payment',
      command: 'payment create {number}',
      needsRow: true,
      prompt: 'Enter amount',
    },
    {
      key: '3',
      label: 'Send invoice',
      command: 'invoice send {number}',
      needsRow: true,
    },
    {
      key: '4',
      label: 'Duplicate invoice',
      command: 'invoice duplicate {number}',
      needsRow: true,
    },
    {
      key: '5',
      label: 'Show unpaid',
      command: 'invoice list --unpaid',
      needsRow: false,
    },
    {
      key: '6',
      label: 'Show overdue',
      command: 'invoice list --overdue',
      needsRow: false,
    },
    {
      key: '9',
      label: 'Create invoice',
      command: 'invoice create',
      needsRow: false,
      prompt: 'Enter customer and amount (e.g., "Acme 1500")',
    },
    {
      key: '0',
      label: 'Void invoice',
      command: 'invoice void {number}',
      needsRow: true,
    },
  ]

case 'payment.list':
  return [
    {
      key: '1',
      label: 'View invoice',
      command: 'invoice view {invoice}',
      needsRow: true,
    },
    {
      key: '9',
      label: 'Record payment',
      command: 'payment create',
      needsRow: false,
      prompt: 'Enter invoice and amount (e.g., "INV-00001 500")',
    },
    {
      key: '0',
      label: 'Void payment',
      command: 'payment void {id}',
      needsRow: true,
    },
  ]
```

---

## Permissions Matrix

Add to `app/Constants/Permissions.php`:

```php
<?php

namespace App\Constants;

class Permissions
{
    // ═══════════════════════════════════════════════════════════════
    // COMPANY (existing)
    // ═══════════════════════════════════════════════════════════════
    public const COMPANY_CREATE = 'company:create';
    public const COMPANY_DELETE = 'company:delete';
    public const COMPANY_INVITE_USER = 'company:invite-user';
    public const COMPANY_MANAGE_USERS = 'company:manage-users';
    public const COMPANY_MANAGE_ROLES = 'company:manage-roles';
    public const COMPANY_DELETE_USER = 'company:delete-user';

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMER
    // ═══════════════════════════════════════════════════════════════
    public const CUSTOMER_CREATE = 'customer:create';
    public const CUSTOMER_VIEW = 'customer:view';
    public const CUSTOMER_UPDATE = 'customer:update';
    public const CUSTOMER_DELETE = 'customer:delete';

    // ═══════════════════════════════════════════════════════════════
    // INVOICE
    // ═══════════════════════════════════════════════════════════════
    public const INVOICE_CREATE = 'invoice:create';
    public const INVOICE_VIEW = 'invoice:view';
    public const INVOICE_UPDATE = 'invoice:update';
    public const INVOICE_SEND = 'invoice:send';
    public const INVOICE_VOID = 'invoice:void';

    // ═══════════════════════════════════════════════════════════════
    // PAYMENT
    // ═══════════════════════════════════════════════════════════════
    public const PAYMENT_CREATE = 'payment:create';
    public const PAYMENT_VIEW = 'payment:view';
    public const PAYMENT_VOID = 'payment:void';
}
```

### Default Role Permissions

Update `config/role-permissions.php`:

```php
<?php

return [
    'owner' => [
        // All permissions
        '*',
    ],

    'admin' => [
        // Company
        'company:invite-user',
        'company:manage-users',
        'company:manage-roles',

        // Customer
        'customer:create',
        'customer:view',
        'customer:update',
        'customer:delete',

        // Invoice
        'invoice:create',
        'invoice:view',
        'invoice:update',
        'invoice:send',
        'invoice:void',

        // Payment
        'payment:create',
        'payment:view',
        'payment:void',
    ],

    'accountant' => [
        // Customer
        'customer:create',
        'customer:view',
        'customer:update',

        // Invoice
        'invoice:create',
        'invoice:view',
        'invoice:update',
        'invoice:send',
        'invoice:void',

        // Payment
        'payment:create',
        'payment:view',
        'payment:void',
    ],

    'member' => [
        // Customer (read only)
        'customer:view',

        // Invoice (read only)
        'invoice:view',

        // Payment (read only)
        'payment:view',
    ],
];
```

---

## Command Bus Config

Update `config/command-bus.php`:

```php
<?php

return [
    // Company
    'company.create' => \App\Actions\Company\CreateAction::class,
    'company.list' => \App\Actions\Company\IndexAction::class,
    'company.view' => \App\Actions\Company\ViewAction::class,
    'company.switch' => \App\Actions\Company\SwitchAction::class,
    'company.delete' => \App\Actions\Company\DeleteAction::class,

    // User
    'user.invite' => \App\Actions\User\InviteAction::class,
    'user.list' => \App\Actions\User\IndexAction::class,
    'user.assign-role' => \App\Actions\User\AssignRoleAction::class,
    'user.remove-role' => \App\Actions\User\RemoveRoleAction::class,
    'user.deactivate' => \App\Actions\User\DeactivateAction::class,
    'user.delete' => \App\Actions\User\DeleteAction::class,

    // Role
    'role.list' => \App\Actions\Role\IndexAction::class,
    'role.assign' => \App\Actions\Role\AssignPermissionAction::class,
    'role.revoke' => \App\Actions\Role\RevokePermissionAction::class,

    // Customer
    'customer.create' => \App\Actions\Customer\CreateAction::class,
    'customer.list' => \App\Actions\Customer\IndexAction::class,
    'customer.view' => \App\Actions\Customer\ViewAction::class,
    'customer.update' => \App\Actions\Customer\UpdateAction::class,
    'customer.delete' => \App\Actions\Customer\DeleteAction::class,
    'customer.restore' => \App\Actions\Customer\RestoreAction::class,

    // Invoice
    'invoice.create' => \App\Actions\Invoice\CreateAction::class,
    'invoice.list' => \App\Actions\Invoice\IndexAction::class,
    'invoice.view' => \App\Actions\Invoice\ViewAction::class,
    'invoice.send' => \App\Actions\Invoice\SendAction::class,
    'invoice.void' => \App\Actions\Invoice\VoidAction::class,
    'invoice.duplicate' => \App\Actions\Invoice\DuplicateAction::class,

    // Payment
    'payment.create' => \App\Actions\Payment\CreateAction::class,
    'payment.list' => \App\Actions\Payment\IndexAction::class,
    'payment.void' => \App\Actions\Payment\VoidAction::class,
];
```

---

## Command Examples Summary

### Customer Commands
```bash
# Create
customer create "Acme Corporation"
customer create "John Doe" --email=john@example.com --phone="555-0100"
cust new "Big Client" --currency=EUR --payment_terms=45

# List
customer list
customer list --search=acme
cust ls --inactive

# View
customer view "Acme Corporation"
customer view acme@example.com
c info acme

# Update
customer update "Acme" --email=new@acme.com
cust edit "Big Client" --payment_terms=60

# Delete / Restore
customer delete "Old Customer"
customer restore "Old Customer"
```

### Invoice Commands
```bash
# Create
invoice create "Acme Corp" 1500
invoice create acme 2500.50 --due=2024-02-15
inv new "Big Client" 10000 --draft
i add acme 500 --reference="PO-12345"

# List
invoice list
invoice list --unpaid
invoice list --overdue
invoice list --customer="Acme"
inv ls --status=draft

# View
invoice view INV-00001
inv get 00001

# Send
invoice send INV-00001
invoice send INV-00001 --email
inv send 00001 --to=billing@customer.com

# Void
invoice void INV-00001
invoice void INV-00001 --reason="Duplicate"

# Duplicate
invoice duplicate INV-00001
invoice duplicate INV-00001 --customer="Other Client"
```

### Payment Commands
```bash
# Create
payment create INV-00001 500
payment create INV-00001 1500 --method=card
pay new 00001 250.50 --date=2024-01-15

# List
payment list
payment list --invoice=INV-00001
payment list --customer="Acme"
pay ls --method=check

# Void
payment void <payment-id>
payment void <id> --reason="Bounced check"
```

---

## File Structure

```
app/
├── Actions/
│   ├── Customer/
│   │   ├── CreateAction.php
│   │   ├── IndexAction.php
│   │   ├── ViewAction.php
│   │   ├── UpdateAction.php
│   │   ├── DeleteAction.php
│   │   └── RestoreAction.php
│   ├── Invoice/
│   │   ├── CreateAction.php
│   │   ├── IndexAction.php
│   │   ├── ViewAction.php
│   │   ├── SendAction.php
│   │   ├── VoidAction.php
│   │   └── DuplicateAction.php
│   └── Payment/
│       ├── CreateAction.php
│       ├── IndexAction.php
│       └── VoidAction.php
├── Constants/
│   └── Permissions.php
├── Models/
│   └── Accounting/
│       ├── Customer.php
│       ├── Invoice.php
│       ├── InvoiceLine.php
│       └── Payment.php
├── Services/
│   └── InvoiceNumberGenerator.php
└── Support/
    └── PaletteFormatter.php

config/
├── command-bus.php
└── role-permissions.php

resources/js/palette/
├── grammar.ts
├── parser.ts
├── quick-actions.ts
└── help.ts
```

---

## Implementation Checklist

### Backend
- [ ] Customer CreateAction
- [ ] Customer IndexAction
- [ ] Customer ViewAction
- [ ] Customer UpdateAction
- [ ] Customer DeleteAction
- [ ] Customer RestoreAction
- [ ] Invoice CreateAction
- [ ] Invoice IndexAction
- [ ] Invoice ViewAction
- [ ] Invoice SendAction
- [ ] Invoice VoidAction
- [ ] Invoice DuplicateAction
- [ ] Payment CreateAction
- [ ] Payment IndexAction
- [ ] Payment VoidAction
- [ ] InvoiceNumberGenerator service
- [ ] Update PaletteFormatter (money, relativeDate)
- [ ] Update Permissions constants
- [ ] Update command-bus.php config
- [ ] Update role-permissions.php config

### Frontend
- [ ] Update grammar.ts (customer, invoice, payment entities)
- [ ] Update parser.ts (inference rules)
- [ ] Update quick-actions.ts (new entity actions)
- [ ] Update help.ts (new command documentation)

### Testing
- [ ] Customer CRUD via palette
- [ ] Invoice CRUD via palette
- [ ] Payment recording via palette
- [ ] Invoice status transitions
- [ ] Permission checks
- [ ] Fuzzy customer matching
- [ ] Invoice number generation
