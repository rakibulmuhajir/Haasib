# Schema Contract - Umrah Visa Operations (umrah)

Single source of truth for Umrah visa groups, agents, passports, visa vendors, transport requirements, payments, and earnings. Read this before touching Umrah migrations, models, services, or controllers.

**Module Location:** `modules/Umrah/`
**Namespace:** `App\Modules\Umrah`

## Direction

- Umrah is a separate module from Fuel Station.
- Companies with industry `umrah` or `travel` should see Umrah-specific features, not petrol pump workflows.
- `Visa Group` is the operational single source of truth.
- Flight and hotel are informational in phase 1.
- Transport is an optional group requirement using client-managed transport services.
- Visa and transport services provide default retail/cost amounts. Group creation copies those values, and users may override copied prices/costs per group.

## Guardrails

- Schema: `umrah` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Every tenant table has `company_id`.
- RLS required with company isolation and super-admin override.
- Models use `$connection = 'pgsql'`, schema-qualified `$table`, `$keyType = 'string'`, `$incrementing = false`.
- Money precision: `numeric(15,2)`.
- Counts: integer.
- Keep operations lightweight; avoid generic travel complexity until ticketing/hotel/transport are separately required.

## Tables

### umrah.agents
- Purpose: Agents who send passports/groups.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK -> `auth.companies.id`.
  - `agent_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `phone` varchar(50) nullable.
  - `email` varchar(255) nullable.
  - `city` varchar(100) nullable.
  - `notes` text nullable.
  - `total_receivable` numeric(15,2) default 0.
  - `total_paid` numeric(15,2) default 0.
  - `balance` numeric(15,2) default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Indexes/constraints:
  - Unique (`company_id`, `agent_number`).
  - Index (`company_id`, `name`), (`company_id`, `is_active`).
- Model fillable:
  - `company_id`, `agent_number`, `name`, `phone`, `email`, `city`, `notes`, `total_receivable`, `total_paid`, `balance`, `is_active`.

### umrah.visa_vendors
- Purpose: Visa suppliers, usually government or service providers.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `vendor_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `vendor_type` varchar(30) default `government`.
  - `phone`, `email`, `city` nullable.
  - `notes` text nullable.
  - `total_cost` numeric(15,2) default 0.
  - `total_paid` numeric(15,2) default 0.
  - `balance` numeric(15,2) default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Check:
  - `vendor_type` in `government`, `visa_provider`, `transport_provider`, `hotel`, `other`.
- Model fillable:
  - `company_id`, `vendor_number`, `name`, `vendor_type`, `phone`, `email`, `city`, `notes`, `total_cost`, `total_paid`, `balance`, `is_active`.

### umrah.vehicle_types
- Purpose: Transport vehicle names controlled by the client.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `name` varchar(100).
  - `seats` integer nullable.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Unique (`company_id`, `name`).
- Model fillable:
  - `company_id`, `name`, `seats`, `notes`, `is_active`.

### umrah.visa_services
- Purpose: Reusable visa service templates with default retail and cost.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `vendor_id` uuid nullable FK -> `umrah.visa_vendors.id`.
  - `name` varchar(150).
  - `retail_amount` numeric(15,2) default 0.
  - `cost_amount` numeric(15,2) default 0.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Unique (`company_id`, `name`).
- Model fillable:
  - `company_id`, `vendor_id`, `name`, `retail_amount`, `cost_amount`, `notes`, `is_active`.

### umrah.transport_services
- Purpose: Reusable transport options including vehicle and driver details.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `vehicle_type_id` uuid nullable FK -> `umrah.vehicle_types.id`.
  - `name` varchar(150).
  - `make` varchar(100) nullable.
  - `model` varchar(100) nullable.
  - `color` varchar(50) nullable.
  - `number_plate` varchar(50) nullable.
  - `driver_name` varchar(150) nullable.
  - `driver_contact` varchar(50) nullable.
  - `default_sale_amount` numeric(15,2) default 0.
  - `default_cost_amount` numeric(15,2) default 0.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Unique (`company_id`, `name`).
- Model fillable:
  - `company_id`, `vehicle_type_id`, `name`, `make`, `model`, `color`, `number_plate`, `driver_name`, `driver_contact`, `default_sale_amount`, `default_cost_amount`, `notes`, `is_active`.

### umrah.visa_groups
- Purpose: Main operational record for a group sent by an agent.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `agent_id` uuid FK -> `umrah.agents.id`.
  - `vendor_id` uuid nullable FK -> `umrah.visa_vendors.id`.
  - `vehicle_type_id` uuid nullable FK -> `umrah.vehicle_types.id`.
  - `visa_service_id` uuid nullable FK -> `umrah.visa_services.id`.
  - `transport_service_id` uuid nullable FK -> `umrah.transport_services.id`.
  - `group_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `status` varchar(30) default `draft`.
  - `travel_date` date nullable.
  - `flight_info` jsonb nullable.
  - `hotel_info` jsonb nullable.
  - `transport_required` boolean default false.
  - `transport_quantity` integer default 0.
  - `passenger_count` integer default 0.
  - `visa_sale_amount` numeric(15,2) default 0.
  - `transport_amount` numeric(15,2) default 0.
  - `discount_amount` numeric(15,2) default 0.
  - `visa_cost_amount` numeric(15,2) default 0.
  - `transport_cost_amount` numeric(15,2) default 0.
  - `total_receivable` numeric(15,2) default 0.
  - `total_paid` numeric(15,2) default 0.
  - `balance` numeric(15,2) default 0.
  - `profit` numeric(15,2) default 0.
  - `notes` text nullable.
  - `sale_transaction_id` uuid nullable FK -> `acct.transactions.id`.
  - `cost_transaction_id` uuid nullable FK -> `acct.transactions.id`.
  - timestamps, soft deletes.
- Check:
  - `status` in `draft`, `passports_received`, `submitted`, `visa_approved`, `delivered`, `closed`, `cancelled`.
- Model fillable:
  - all business columns above.

### umrah.passengers
- Purpose: Passports/passengers inside a visa group.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `visa_group_id` uuid FK -> `umrah.visa_groups.id`.
  - `full_name` varchar(255).
  - `passport_number` varchar(100) nullable.
  - `nationality` varchar(100) nullable.
  - `date_of_birth` date nullable.
  - `visa_status` varchar(30) default `pending`.
  - `notes` text nullable.
  - `sort_order` integer default 0.
  - timestamps, soft deletes.
- Check:
  - `visa_status` in `pending`, `received`, `submitted`, `approved`, `rejected`, `delivered`.
- Model fillable:
  - `company_id`, `visa_group_id`, `full_name`, `passport_number`, `nationality`, `date_of_birth`, `visa_status`, `notes`, `sort_order`.

### umrah.group_payments
- Purpose: Payments received from agents against visa groups.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `visa_group_id` uuid FK.
  - `agent_id` uuid FK.
  - `account_id` uuid nullable FK -> `acct.accounts.id`.
  - `payment_number` varchar(50), unique per company.
  - `payment_date` date.
  - `amount` numeric(15,2).
  - `method` varchar(30) default `cash`.
  - `reference` varchar(255) nullable.
  - `notes` text nullable.
  - `transaction_id` uuid nullable FK -> `acct.transactions.id`.
  - timestamps, soft deletes.
- Check:
  - `method` in `cash`, `bank_transfer`, `card`, `wallet`, `other`.
- Model fillable:
  - `company_id`, `visa_group_id`, `agent_id`, `account_id`, `payment_number`, `payment_date`, `amount`, `method`, `reference`, `notes`.

## Business Rules

- `Visa Group` totals:
  - `total_receivable = visa_sale_amount + transport_amount - discount_amount`.
  - `balance = total_receivable - total_paid`.
  - `profit = visa_sale_amount + transport_amount - discount_amount - visa_cost_amount - transport_cost_amount`.
- Selecting a visa service copies its default retail/cost into group amounts; users may override before saving.
- Selecting a transport service copies its default sale/cost and vehicle type into group fields; users may override before saving.
- Agent totals are recalculated from active non-cancelled groups.
- Passenger count is recalculated from passengers unless explicitly entered on group creation.
- Payments cannot exceed group balance in phase 1.
- Payment records update group and agent totals inside one database transaction.

## Phase 1 Accounting Target

- Group sale posts once per group when receivable is created:
  - Dr Agent Receivable
  - Cr Visa Revenue
  - Cr Transport Revenue if transport is charged.
- Visa cost posts once per group when cost is entered:
  - Dr Visa Cost
  - Cr Vendor Payable.
- Transport cost posts with group cost when entered:
  - Dr Transport Cost
  - Cr Vendor Payable.
- Agent payment posts once per payment:
  - Dr Cash / Bank
  - Cr Agent Receivable
- `sale_transaction_id`, `cost_transaction_id`, and `group_payments.transaction_id` are the idempotency links to GL.
