# Schema Contract — Visitor Management System (vms)

Single source of truth for travel agency visitor management: groups, visitors, services, bookings, vouchers, and itineraries. Vertical-specific module for travel/tourism businesses.

## Guardrails
- Schema: `vms` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on major entities.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Money precision: `numeric(15,2)` for amounts.
- Integrates with CRM (customers) and AR (invoices) when available.
- Passport/travel document handling requires data protection consideration.

## Tables

### vms.groups
- Purpose: travel group master (tour groups, corporate delegations).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `group_number` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `customer_id` uuid nullable FK → `acct.customers.id` (SET NULL/CASCADE).
  - `vendor_id` uuid nullable FK → `acct.vendors.id` (SET NULL/CASCADE) (tour operator).
  - `group_type` varchar(30) not null default 'tour'. Enum: tour, business, family, individual, pilgrimage, educational.
  - `departure_date` date nullable.
  - `return_date` date nullable.
  - `destination_country_code` char(2) nullable FK → `public.countries.code`.
  - `origin_country_code` char(2) nullable FK → `public.countries.code`.
  - `status` varchar(30) not null default 'draft'. Enum: draft, confirmed, in_progress, completed, cancelled.
  - `total_members` integer not null default 0; check >= 0.
  - `total_cost` numeric(15,2) not null default 0.
  - `paid_amount` numeric(15,2) not null default 0.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `leader_name` varchar(255) nullable.
  - `leader_phone` varchar(50) nullable.
  - `notes` text nullable.
  - `custom_fields` jsonb not null default '{}'.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `group_number`) where deleted_at is null.
  - Index: `company_id`; `customer_id`; (`company_id`, `status`); (`company_id`, `departure_date`).
  - Check: return_date IS NULL OR return_date >= departure_date.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.groups'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','group_number','name','description','customer_id','vendor_id','group_type','departure_date','return_date','destination_country_code','origin_country_code','status','total_members','total_cost','paid_amount','currency','leader_name','leader_phone','notes','custom_fields','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','vendor_id'=>'string','departure_date'=>'date','return_date'=>'date','total_members'=>'integer','total_cost'=>'decimal:2','paid_amount'=>'decimal:2','custom_fields'=>'array','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo Vendor; hasMany Visitor; hasMany Booking.
- Validation:
  - `group_number`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `group_type`: required|in:tour,business,family,individual,pilgrimage,educational.
  - `status`: in:draft,confirmed,in_progress,completed,cancelled.
  - `departure_date`: nullable|date.
  - `return_date`: nullable|date|after_or_equal:departure_date.
- Business rules:
  - total_members updated by trigger on visitors.
  - Cannot delete group with bookings; cancel instead.

### vms.visitors
- Purpose: individual travelers/visitors.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `group_id` uuid nullable FK → `vms.groups.id` (SET NULL/CASCADE).
  - `customer_id` uuid nullable FK → `acct.customers.id` (SET NULL/CASCADE).
  - `visitor_number` varchar(50) not null.
  - `title` varchar(10) nullable. Enum: Mr, Mrs, Ms, Miss, Dr, Prof.
  - `first_name` varchar(255) not null.
  - `last_name` varchar(255) not null.
  - `date_of_birth` date nullable.
  - `gender` varchar(20) nullable. Enum: male, female, other.
  - `nationality_code` char(2) nullable FK → `public.countries.code`.
  - `passport_number` varchar(50) nullable.
  - `passport_issue_date` date nullable.
  - `passport_expiry_date` date nullable.
  - `passport_issue_country_code` char(2) nullable FK → `public.countries.code`.
  - `national_id` varchar(50) nullable.
  - `phone` varchar(50) nullable.
  - `email` varchar(255) nullable.
  - `address` text nullable.
  - `emergency_contact_name` varchar(255) nullable.
  - `emergency_contact_phone` varchar(50) nullable.
  - `emergency_contact_relation` varchar(100) nullable.
  - `special_requirements` text nullable (dietary, medical, mobility).
  - `notes` text nullable.
  - `documents` jsonb not null default '[]'. Array of {type, name, url, expiry_date}.
  - `custom_fields` jsonb not null default '{}'.
  - `is_active` boolean not null default true.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `visitor_number`) where deleted_at is null.
  - Index: `company_id`; `group_id`; (`company_id`, `passport_number`); (`company_id`, `email`).
  - Check: passport_expiry_date IS NULL OR passport_issue_date IS NULL OR passport_expiry_date > passport_issue_date.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.visitors'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','group_id','customer_id','visitor_number','title','first_name','last_name','date_of_birth','gender','nationality_code','passport_number','passport_issue_date','passport_expiry_date','passport_issue_country_code','national_id','phone','email','address','emergency_contact_name','emergency_contact_phone','emergency_contact_relation','special_requirements','notes','documents','custom_fields','is_active','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','group_id'=>'string','customer_id'=>'string','date_of_birth'=>'date','passport_issue_date'=>'date','passport_expiry_date'=>'date','documents'=>'array','custom_fields'=>'array','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Group; belongsTo Customer; hasMany Service; hasMany BookingItem (through Service).
- Validation:
  - `visitor_number`: required|string|max:50; unique per company (soft-delete aware).
  - `first_name`: required|string|max:255.
  - `last_name`: required|string|max:255.
  - `passport_number`: nullable|string|max:50.
  - `passport_expiry_date`: nullable|date|after:passport_issue_date.
- Business rules:
  - Passport expiry warning if < 6 months from travel date.
  - Visitor can be reused across multiple groups.
  - Sensitive data (passport) requires access control.

### vms.services
- Purpose: services provided to visitors (visa, hotel, flight, etc.).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `visitor_id` uuid not null FK → `vms.visitors.id` (CASCADE/CASCADE).
  - `vendor_id` uuid nullable FK → `acct.vendors.id` (SET NULL/CASCADE).
  - `service_type` varchar(30) not null. Enum: visa, hotel, flight, transport, tour, insurance, other.
  - `service_name` varchar(255) not null.
  - `description` text nullable.
  - `start_date` date nullable.
  - `end_date` date nullable.
  - `quantity` integer not null default 1; check > 0.
  - `unit_price` numeric(15,2) not null default 0; check >= 0.
  - `total_price` numeric(15,2) not null default 0; check >= 0.
  - `cost_price` numeric(15,2) nullable (supplier cost).
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `status` varchar(30) not null default 'pending'. Enum: pending, confirmed, completed, cancelled.
  - `reference_number` varchar(100) nullable (booking ref).
  - `confirmation_number` varchar(100) nullable (supplier confirmation).
  - `details` jsonb not null default '{}'. Type-specific details (flight: airline, hotel: room_type, etc.).
  - `attachments` jsonb not null default '[]'. Array of {name, url, type}.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `visitor_id`; `vendor_id`; (`company_id`, `service_type`); (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.services'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','visitor_id','vendor_id','service_type','service_name','description','start_date','end_date','quantity','unit_price','total_price','cost_price','currency','status','reference_number','confirmation_number','details','attachments','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','visitor_id'=>'string','vendor_id'=>'string','start_date'=>'date','end_date'=>'date','quantity'=>'integer','unit_price'=>'decimal:2','total_price'=>'decimal:2','cost_price'=>'decimal:2','details'=>'array','attachments'=>'array','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Visitor; belongsTo Vendor.
- Business rules:
  - total_price = quantity * unit_price.
  - Margin = total_price - cost_price.
  - Services can be bundled into bookings.

### vms.bookings
- Purpose: order header for billable services.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `booking_number` varchar(50) not null.
  - `customer_id` uuid nullable FK → `acct.customers.id` (SET NULL/CASCADE).
  - `group_id` uuid nullable FK → `vms.groups.id` (SET NULL/CASCADE).
  - `booking_date` date not null default current_date.
  - `status` varchar(30) not null default 'draft'. Enum: draft, confirmed, invoiced, cancelled.
  - `subtotal` numeric(15,2) not null default 0.
  - `tax_amount` numeric(15,2) not null default 0.
  - `discount_amount` numeric(15,2) not null default 0.
  - `total_amount` numeric(15,2) not null default 0.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `invoice_id` uuid nullable FK → `acct.invoices.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `terms` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `booking_number`) where deleted_at is null.
  - Index: `company_id`; `customer_id`; `group_id`; (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.bookings'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','booking_number','customer_id','group_id','booking_date','status','subtotal','tax_amount','discount_amount','total_amount','currency','invoice_id','notes','terms','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','group_id'=>'string','booking_date'=>'date','subtotal'=>'decimal:2','tax_amount'=>'decimal:2','discount_amount'=>'decimal:2','total_amount'=>'decimal:2','invoice_id'=>'string','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo Group; belongsTo Invoice; hasMany BookingItem.
- Business rules:
  - Converting to invoice creates AR invoice with line items.
  - total_amount = subtotal + tax_amount - discount_amount.

### vms.booking_items
- Purpose: line items for bookings.
- Columns:
  - `id` uuid PK.
  - `booking_id` uuid not null FK → `vms.bookings.id` (CASCADE/CASCADE).
  - `service_id` uuid nullable FK → `vms.services.id` (SET NULL/CASCADE).
  - `visitor_id` uuid nullable FK → `vms.visitors.id` (SET NULL/CASCADE).
  - `description` varchar(255) not null.
  - `quantity` integer not null default 1; check > 0.
  - `unit_price` numeric(15,2) not null default 0; check >= 0.
  - `tax_rate` numeric(5,2) not null default 0; check >= 0 and <= 100.
  - `tax_amount` numeric(15,2) not null default 0.
  - `discount_amount` numeric(15,2) not null default 0.
  - `line_total` numeric(15,2) not null default 0.
  - `sort_order` integer not null default 0.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `booking_id`; `service_id`; `visitor_id`.
- RLS: inherited from parent (bookings).
- Model:
  - `$connection = 'pgsql'; $table = 'vms.booking_items'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['booking_id','service_id','visitor_id','description','quantity','unit_price','tax_rate','tax_amount','discount_amount','line_total','sort_order'];`
  - `$casts = ['booking_id'=>'string','service_id'=>'string','visitor_id'=>'string','quantity'=>'integer','unit_price'=>'decimal:2','tax_rate'=>'decimal:2','tax_amount'=>'decimal:2','discount_amount'=>'decimal:2','line_total'=>'decimal:2','sort_order'=>'integer','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - line_total = (quantity * unit_price) + tax_amount - discount_amount.
  - Trigger updates booking totals.

### vms.vouchers
- Purpose: travel vouchers (hotel, flight, tour confirmations).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `voucher_number` varchar(50) not null.
  - `booking_id` uuid nullable FK → `vms.bookings.id` (SET NULL/CASCADE).
  - `visitor_id` uuid nullable FK → `vms.visitors.id` (SET NULL/CASCADE).
  - `voucher_type` varchar(30) not null. Enum: hotel, flight, transport, tour, general.
  - `title` varchar(255) not null.
  - `issue_date` date not null default current_date.
  - `valid_from` date nullable.
  - `valid_until` date nullable.
  - `details` jsonb not null default '{}'. Type-specific (hotel: check_in, check_out, room_type; flight: airline, flight_no).
  - `terms_conditions` text nullable.
  - `status` varchar(20) not null default 'active'. Enum: active, used, expired, cancelled.
  - `used_at` timestamp nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `voucher_number`).
  - Index: `company_id`; `booking_id`; `visitor_id`; (`company_id`, `status`).
  - Check: valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.vouchers'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','voucher_number','booking_id','visitor_id','voucher_type','title','issue_date','valid_from','valid_until','details','terms_conditions','status','used_at','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','booking_id'=>'string','visitor_id'=>'string','issue_date'=>'date','valid_from'=>'date','valid_until'=>'date','details'=>'array','used_at'=>'datetime','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Generate printable voucher PDF.
  - Auto-expire past valid_until date.

### vms.itineraries
- Purpose: trip itinerary/schedule.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `booking_id` uuid not null FK → `vms.bookings.id` (CASCADE/CASCADE).
  - `title` varchar(255) not null.
  - `start_date` date nullable.
  - `end_date` date nullable.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `booking_id`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.itineraries'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','booking_id','title','start_date','end_date','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','booking_id'=>'string','start_date'=>'date','end_date'=>'date','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`

### vms.itinerary_items
- Purpose: daily activities within itinerary.
- Columns:
  - `id` uuid PK.
  - `itinerary_id` uuid not null FK → `vms.itineraries.id` (CASCADE/CASCADE).
  - `day_number` integer not null; check > 0.
  - `activity_date` date nullable.
  - `start_time` time nullable.
  - `end_time` time nullable.
  - `activity_type` varchar(30) not null. Enum: flight, hotel, tour, transport, meal, free_time, other.
  - `title` varchar(255) not null.
  - `location` varchar(255) nullable.
  - `description` text nullable.
  - `details` jsonb not null default '{}'.
  - `sort_order` integer not null default 0.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `itinerary_id`; (`itinerary_id`, `day_number`).
- RLS: inherited from parent.
- Model:
  - `$connection = 'pgsql'; $table = 'vms.itinerary_items'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['itinerary_id','day_number','activity_date','start_time','end_time','activity_type','title','location','description','details','sort_order'];`
  - `$casts = ['itinerary_id'=>'string','day_number'=>'integer','activity_date'=>'date','details'=>'array','sort_order'=>'integer','created_at'=>'datetime','updated_at'=>'datetime'];`

## Enums Reference

### Group Type
| Type | Description |
|------|-------------|
| tour | Package tour |
| business | Corporate/business travel |
| family | Family vacation |
| individual | Single traveler |
| pilgrimage | Religious pilgrimage |
| educational | School/university trip |

### Service Type
| Type | Description |
|------|-------------|
| visa | Visa processing |
| hotel | Hotel accommodation |
| flight | Air travel |
| transport | Ground transport |
| tour | Sightseeing/activities |
| insurance | Travel insurance |
| other | Other services |

## Form Behaviors

### Group Form
- Fields: group_number, name, group_type, customer_id, departure_date, return_date, destination, leader info
- Add visitors to group
- View total cost and payments

### Visitor Form
- Fields: personal info, passport details, emergency contact, special requirements
- Document upload (passport copy, visa)
- Passport expiry validation

### Service Form
- Fields: service_type, visitor_id, vendor_id, dates, pricing
- Type-specific detail fields (flight: airline, times; hotel: room type, meals)
- Attach confirmations

### Booking Form
- Select group or individual customer
- Add services as line items
- Apply discounts
- Convert to invoice action

## Out of Scope (v1)
- Visa application tracking workflow.
- Flight/hotel API integration.
- Travel document OCR.
- Customer portal for travelers.
- Multi-currency pricing.
- Commission tracking for agents.

## Extending
- Add new service_type values here first.
- Visa workflow would add `vms.visa_applications` table.
- Supplier integration would add API credential storage.
