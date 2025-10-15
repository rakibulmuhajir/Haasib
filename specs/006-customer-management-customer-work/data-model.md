# Data Model — Customer Management Lifecycle

## Entity Overview

### Customer (`invoicing.customers`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK, generated via `uuid_generate_v4()` | Multi-tenant identifier (exposed as `customer_id`) |
| `company_id` | UUID | FK → `auth.companies.id`, required, RLS enforced | Partitioning key for tenancy |
| `customer_number` | string(30) | Unique per table, required, slug/sequence (e.g., `CUST-0001`) | Used in UI/CLI lookup |
| `name` | string(255) | Required, trimmed | Display name |
| `legal_name` | string(255) | Optional | Supports legal vs trading names |
| `status` | enum(`active`,`inactive`,`blocked`) | Default `active`, validated by state machine | Drives credit enforcement |
| `email` | string(255) | Nullable, must be email | Primary billing contact |
| `phone` | string(50) | Nullable, E.164 formatting on save | |
| `default_currency` | char(3) | Required, ISO 4217 | Aligns with invoice currency |
| `payment_terms` | string(100) | Nullable, enumerated (e.g., `net_30`) | UI friendly labels |
| `credit_limit` | decimal(15,2) | Nullable, non-negative | Mirrors latest entry in credit history |
| `credit_limit_effective_at` | timestamp | Nullable | Cached effective date |
| `tax_id` | string(50) | Nullable | Supports multiple regions |
| `website` | string(255) | Nullable, URL validation | |
| `notes` | text | Nullable | Internal notes, audit logged |
| `created_by_user_id` | UUID | FK → `auth.users.id`, nullable | For audit trail |
| `created_at`/`updated_at` | timestamps | Required | |
| `deleted_at` | timestamp | Soft delete | Honours RLS policies |

Indexes: `(company_id, customer_number)` unique; `(company_id, status)`, `(company_id, email)`, and `GIN` trigram index on `name` (optional) for search.

### CustomerContact (`invoicing.customer_contacts`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `customer_id` | UUID | FK → `invoicing.customers.id`, required | |
| `company_id` | UUID | FK → `auth.companies.id`, required | Redundant for RLS |
| `first_name` / `last_name` | string(100) | Required | |
| `email` | string(255) | Required, email | Unique per customer |
| `phone` | string(50) | Nullable, E.164 | |
| `role` | string(100) | Required | e.g., `billing`, `technical`, `collections` |
| `is_primary` | boolean | Default false | At most one `true` per role per customer |
| `preferred_channel` | enum(`email`,`phone`,`sms`,`portal`) | Default `email` | |
| `created_by_user_id` | UUID | FK → `auth.users.id`, nullable | |
| Timestamps + `deleted_at` | | | |

Unique index: `(customer_id, lower(email))`; partial unique ensuring single primary per role.

### CustomerAddress (`invoicing.customer_addresses`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `customer_id` | UUID | FK → `invoicing.customers.id`, required | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `label` | string(100) | Required | Human-readable alias |
| `type` | enum(`billing`,`shipping`,`statement`,`other`) | Required | |
| `line1` | string(255) | Required | |
| `line2` | string(255) | Nullable | |
| `city` | string(100) | Nullable | |
| `state` | string(100) | Nullable | |
| `postal_code` | string(30) | Nullable | Format validated per country |
| `country` | char(2) | Required, ISO 3166-1 alpha-2 | |
| `is_default` | boolean | Default false | Enforce one default per type |
| `notes` | text | Nullable | Delivery instructions |
| Timestamps + `deleted_at` | | | |

Unique constraint: `(customer_id, type, is_default=true)` via partial index.

### CustomerCreditLimit (`invoicing.customer_credit_limits`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `customer_id` | UUID | FK → `invoicing.customers.id`, required | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `limit_amount` | decimal(15,2) | Required, >=0 | Stored in customer currency |
| `effective_at` | timestamp | Required | Defaults to `now()` |
| `expires_at` | timestamp | Nullable | Optional future limit |
| `status` | enum(`pending`,`approved`,`revoked`) | Default `approved` | Workflow support |
| `reason` | text | Nullable | |
| `changed_by_user_id` | UUID | FK → `auth.users.id`, required | |
| `approval_reference` | string(100) | Nullable | Maps to ticket or document |
| `created_at` | timestamp | | |

Composite index `(customer_id, effective_at desc)`.

### CustomerStatement (`invoicing.customer_statements`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `customer_id` | UUID | FK → `invoicing.customers.id`, required | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `period_start` | date | Required | Inclusive |
| `period_end` | date | Required | Inclusive |
| `generated_at` | timestamp | Required | |
| `generated_by_user_id` | UUID | FK → `auth.users.id`, nullable | |
| `opening_balance` | decimal(15,2) | Required | Snapshot from prior period |
| `total_invoiced` | decimal(15,2) | Required | |
| `total_paid` | decimal(15,2) | Required | |
| `total_credit_notes` | decimal(15,2) | Required | |
| `closing_balance` | decimal(15,2) | Required | Derived |
| `aging_bucket_summary` | jsonb | Required | Stores bucket totals |
| `document_path` | string(255) | Nullable | Link to generated PDF/CSV |
| `checksum` | string(64) | Nullable | Detect tampering |

Unique index `(customer_id, period_start, period_end)`.

### CustomerAgingSnapshot (`invoicing.customer_aging_snapshots`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `customer_id` | UUID | FK → `invoicing.customers.id`, required | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `snapshot_date` | date | Required, default `current_date` | |
| `bucket_current` | decimal(15,2) | Required | |
| `bucket_1_30` | decimal(15,2) | Required | |
| `bucket_31_60` | decimal(15,2) | Required | |
| `bucket_61_90` | decimal(15,2) | Required | |
| `bucket_90_plus` | decimal(15,2) | Required | |
| `total_invoices` | integer | Required | |
| `generated_via` | enum(`scheduled`,`on_demand`) | Required | |
| `generated_by_user_id` | UUID | FK → `auth.users.id`, nullable | For on-demand |
| `created_at` | timestamp | | |

Index `(customer_id, snapshot_date desc)`.

### CustomerGroup (`invoicing.customer_groups`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `name` | string(100) | Required, unique per company | |
| `description` | text | Nullable | |
| `is_default` | boolean | Default false | |
| timestamps | | | |

Join table `invoicing.customer_group_members (customer_id, group_id)` ensures membership with unique constraint `(customer_id, group_id)`.

### CustomerCommunication (`invoicing.customer_communications`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `customer_id` | UUID | FK → `invoicing.customers.id`, required | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `contact_id` | UUID | FK → `invoicing.customer_contacts.id`, nullable | |
| `channel` | enum(`email`,`phone`,`meeting`,`note`) | Required | |
| `direction` | enum(`inbound`,`outbound`,`internal`) | Required | |
| `subject` | string(255) | Nullable | |
| `body` | text | Required for internal notes | |
| `logged_by_user_id` | UUID | FK → `auth.users.id`, required | |
| `occurred_at` | timestamp | Required | |
| `attachments` | jsonb | Nullable | metadata for files |
| timestamps | | | |

## Relationships
- `Customer` 1—* `CustomerContact`, `CustomerAddress`, `CustomerCreditLimit`, `CustomerStatement`, `CustomerAgingSnapshot`, `CustomerCommunication`.
- `Customer` *—* `CustomerGroup` via `customer_group_members`.
- `Customer` 1—* `Invoice` and `Payment` (existing tables) with FK `customer_id`.
- `CustomerStatement` references aggregated invoices/payments via derived data; no direct FK but stores metadata.
- `CustomerCreditLimit` latest approved row synchronizes to `customers.credit_limit`.

## State Machines
- **Customer Status**: `active` → (`inactive` | `blocked`), `inactive` → `active`, `blocked` → (`active` after approval). Blocking requires capturing reason; transitions logged in audit.
- **Credit Limit Status**: `pending` → `approved` (with approval reference) or `revoked`; `approved` → `revoked` or superseded by new record; `revoked` → `pending` for re-approval.
- **Statement Lifecycle**: `generated` (default) → `archived` (logical flag) when superseded; archived statements remain immutable (no updates).

## Validation & Business Rules
- Customer numbers must be unique per company; sequence generator increments per tenant.
- Only one primary contact per role per customer; UI enforces designation changes atomically.
- Default address constraint ensures at most one default per type; updates flip previous default off within transaction.
- Credit limit enforcement: invoices may only be created if `balance_due + new_invoice_total ≤ credit_limit` unless override permission is granted; overrides require reason captured in audit entries.
- Aging snapshots must align with statement periods; an on-demand statement triggers immediate snapshot creation stored in `customer_aging_snapshots`.
- Communications cannot be deleted once sent to external contacts; soft-delete allowed only for internal notes (requires `accounting.customers.manage_contacts` permission).
