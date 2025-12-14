# Schema Contract — CRM (crm)

Single source of truth for contacts and interactions. Extends customer/vendor data with relationship management capabilities.

## Guardrails
- Schema: `crm` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at`.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Customers and vendors live in `acct` schema; this module adds contacts and activity tracking.
- Contacts are linked to either customer OR vendor, not both.

## Tables

### crm.contacts
- Purpose: individual people at customers/vendors.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid nullable FK → `acct.customers.id` (CASCADE/CASCADE).
  - `vendor_id` uuid nullable FK → `acct.vendors.id` (CASCADE/CASCADE).
  - `first_name` varchar(100) not null.
  - `last_name` varchar(100) not null.
  - `email` varchar(255) nullable.
  - `phone` varchar(50) nullable.
  - `mobile` varchar(50) nullable.
  - `position` varchar(100) nullable (job title).
  - `department` varchar(100) nullable.
  - `is_primary` boolean not null default false.
  - `is_billing_contact` boolean not null default false.
  - `is_shipping_contact` boolean not null default false.
  - `notes` text nullable.
  - `tags` jsonb not null default '[]'.
  - `custom_fields` jsonb not null default '{}'.
  - `is_active` boolean not null default true.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `customer_id`; `vendor_id`; (`company_id`, `email`); (`company_id`, `is_active`).
  - Check: (customer_id IS NOT NULL AND vendor_id IS NULL) OR (vendor_id IS NOT NULL AND customer_id IS NULL) OR (customer_id IS NULL AND vendor_id IS NULL).
- RLS:
  ```sql
  alter table crm.contacts enable row level security;
  create policy contacts_policy on crm.contacts
    for all using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model:
  - `$connection = 'pgsql'; $table = 'crm.contacts'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','vendor_id','first_name','last_name','email','phone','mobile','position','department','is_primary','is_billing_contact','is_shipping_contact','notes','tags','custom_fields','is_active','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','vendor_id'=>'string','is_primary'=>'boolean','is_billing_contact'=>'boolean','is_shipping_contact'=>'boolean','tags'=>'array','custom_fields'=>'array','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo Vendor; hasMany Interaction.
- Validation:
  - `first_name`: required|string|max:100.
  - `last_name`: required|string|max:100.
  - `email`: nullable|email|max:255.
  - `phone`: nullable|string|max:50.
  - `customer_id`: nullable|uuid|exists:acct.customers,id.
  - `vendor_id`: nullable|uuid|exists:acct.vendors,id.
  - XOR: only one of customer_id or vendor_id can be set.
- Business rules:
  - Only one is_primary = true per customer/vendor.
  - Contact can exist without customer/vendor (standalone contact).
  - Deleting customer/vendor cascades to contacts.

### crm.interactions
- Purpose: activity log for customer/vendor relationships.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid nullable FK → `acct.customers.id` (CASCADE/CASCADE).
  - `vendor_id` uuid nullable FK → `acct.vendors.id` (CASCADE/CASCADE).
  - `contact_id` uuid nullable FK → `crm.contacts.id` (SET NULL/CASCADE).
  - `interaction_type` varchar(30) not null. Enum: call, email, meeting, note, task, follow_up.
  - `direction` varchar(10) nullable. Enum: inbound, outbound (for calls/emails).
  - `subject` varchar(255) nullable.
  - `description` text nullable.
  - `interaction_date` timestamp not null default now().
  - `duration_minutes` integer nullable.
  - `outcome` varchar(50) nullable. Enum: completed, no_answer, voicemail, scheduled, cancelled.
  - `follow_up_date` date nullable.
  - `follow_up_notes` text nullable.
  - `is_completed` boolean not null default true.
  - `related_type` varchar(100) nullable (e.g., 'acct.invoices', 'acct.bills').
  - `related_id` uuid nullable.
  - `attachments` jsonb not null default '[]'. Array of {name, url, size, mime_type}.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `assigned_to_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `customer_id`; `vendor_id`; `contact_id`; (`company_id`, `interaction_date`); (`company_id`, `interaction_type`); (`assigned_to_user_id`, `is_completed`).
  - Check: customer_id IS NOT NULL OR vendor_id IS NOT NULL OR contact_id IS NOT NULL.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'crm.interactions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','vendor_id','contact_id','interaction_type','direction','subject','description','interaction_date','duration_minutes','outcome','follow_up_date','follow_up_notes','is_completed','related_type','related_id','attachments','created_by_user_id','assigned_to_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','vendor_id'=>'string','contact_id'=>'string','interaction_date'=>'datetime','duration_minutes'=>'integer','follow_up_date'=>'date','is_completed'=>'boolean','related_id'=>'string','attachments'=>'array','created_by_user_id'=>'string','assigned_to_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo Vendor; belongsTo Contact; belongsTo CreatedBy (User); belongsTo AssignedTo (User).
- Validation:
  - `interaction_type`: required|in:call,email,meeting,note,task,follow_up.
  - `direction`: nullable|in:inbound,outbound.
  - `interaction_date`: required|date.
  - `outcome`: nullable|in:completed,no_answer,voicemail,scheduled,cancelled.
  - At least one of customer_id, vendor_id, or contact_id required.
- Business rules:
  - Interactions provide audit trail of all communications.
  - Tasks with is_completed = false show in to-do lists.
  - Follow-up dates trigger reminders.
  - Can link to documents (invoices, bills) via related_type/related_id.

### crm.tags
- Purpose: categorization tags for customers/vendors/contacts.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `name` varchar(100) not null.
  - `color` varchar(7) nullable (hex color, e.g., '#FF5733').
  - `description` text nullable.
  - `entity_type` varchar(30) not null. Enum: customer, vendor, contact, all.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `name`, `entity_type`).
  - Index: `company_id`; (`company_id`, `entity_type`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'crm.tags'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','name','color','description','entity_type','is_active'];`
  - `$casts = ['company_id'=>'string','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Tags with entity_type = 'all' apply to any entity.
  - Stored in entity's `tags` JSONB field as array of tag names or IDs.

### crm.custom_field_definitions
- Purpose: define custom fields for customers/vendors/contacts.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `entity_type` varchar(30) not null. Enum: customer, vendor, contact.
  - `field_name` varchar(100) not null.
  - `field_label` varchar(255) not null.
  - `field_type` varchar(30) not null. Enum: text, number, date, boolean, select, multiselect, url, email.
  - `options` jsonb nullable (for select/multiselect types).
  - `is_required` boolean not null default false.
  - `default_value` text nullable.
  - `sort_order` integer not null default 0.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `entity_type`, `field_name`).
  - Index: `company_id`; (`company_id`, `entity_type`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'crm.custom_field_definitions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','entity_type','field_name','field_label','field_type','options','is_required','default_value','sort_order','is_active'];`
  - `$casts = ['company_id'=>'string','options'=>'array','is_required'=>'boolean','sort_order'=>'integer','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Values stored in entity's `custom_fields` JSONB.
  - field_name used as key in custom_fields object.
  - Validation rules derived from field_type and is_required.

## Integration with Accounting

### Customers (acct.customers)
Add these columns if not present:
- `tags` jsonb default '[]'
- `custom_fields` jsonb default '{}'
- `primary_contact_id` uuid nullable FK → crm.contacts.id

### Vendors (acct.vendors)
Add these columns if not present:
- `tags` jsonb default '[]'
- `custom_fields` jsonb default '{}'
- `primary_contact_id` uuid nullable FK → crm.contacts.id

## Enums Reference

### Interaction Type
| Type | Description | Has Direction |
|------|-------------|---------------|
| call | Phone call | Yes |
| email | Email communication | Yes |
| meeting | In-person or virtual meeting | No |
| note | Internal note | No |
| task | To-do item | No |
| follow_up | Scheduled follow-up | No |

### Outcome
| Outcome | Description |
|---------|-------------|
| completed | Successfully completed |
| no_answer | No response |
| voicemail | Left voicemail |
| scheduled | Meeting scheduled |
| cancelled | Cancelled |

## Form Behaviors

### Contact Form
- Fields: first_name, last_name, email, phone, mobile, position, department, customer_id OR vendor_id, is_primary, is_billing_contact, is_shipping_contact, tags, custom_fields
- Customer/vendor selector (mutually exclusive)
- Custom fields rendered dynamically from definitions
- Tags multi-select from available tags

### Interaction Form
- Fields: interaction_type, direction, subject, description, interaction_date, duration_minutes, outcome, follow_up_date, contact_id, related document
- Contact dropdown filtered by selected customer/vendor
- Duration shown for calls
- Related document picker (recent invoices, bills)
- Follow-up creates reminder

### Activity Timeline
- Shows all interactions for customer/vendor/contact
- Chronological order (newest first)
- Filter by type, date range, user
- Quick-add buttons for each type

### Task List
- Incomplete interactions where is_completed = false
- Filter by assigned user
- Sort by follow_up_date
- Mark complete action

## Out of Scope (v1)
- Lead management / sales pipeline.
- Email integration (send/receive from app).
- Calendar integration.
- Document management.
- Mass email campaigns.
- Customer portal.
- Opportunity/deal tracking.

## Extending
- Add new interaction_type values here first.
- Sales pipeline would add `crm.opportunities` and `crm.pipeline_stages`.
- Email integration would add `crm.emails` with tracking.
- Document management would add `crm.documents` table.
