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
- Transport service is the vehicle source of truth. Do not maintain a separate vehicle type setup screen.
- Drivers are reusable transport staff records. A transport service can have a default driver, and group creation can override the driver for that trip.
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
  - `user_id` uuid nullable FK -> `auth.users.id`. Optional login user for agent self-service.
  - `agent_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `phone` varchar(50) nullable.
  - `email` varchar(255) nullable.
  - `city` varchar(100) nullable.
  - `country` varchar(100) nullable. Used as the default passenger nationality for this agent's new groups.
  - `notes` text nullable.
  - `total_receivable` numeric(15,2) default 0.
  - `total_paid` numeric(15,2) default 0.
  - `balance` numeric(15,2) default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Indexes/constraints:
  - Unique (`company_id`, `agent_number`).
  - Unique (`company_id`, `user_id`) where `user_id` is not null.
  - Index (`company_id`, `name`), (`company_id`, `is_active`), (`company_id`, `user_id`).
- Model fillable:
  - `company_id`, `user_id`, `agent_number`, `name`, `phone`, `email`, `city`, `country`, `notes`, `total_receivable`, `total_paid`, `balance`, `is_active`.

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
- Purpose: Reusable transport options including vehicle, passenger capacity, and driver details.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `driver_id` uuid nullable FK -> `umrah.drivers.id`.
  - `name` varchar(150).
  - `vehicle_type` varchar(100) nullable. Free-form type such as car, 7-seater, coaster, bus.
  - `pax_capacity` integer nullable. Passenger capacity for one vehicle/service.
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
  - `company_id`, `driver_id`, `name`, `vehicle_type`, `pax_capacity`, `make`, `model`, `color`, `number_plate`, `driver_name`, `driver_contact`, `default_sale_amount`, `default_cost_amount`, `notes`, `is_active`.

### umrah.drivers
- Purpose: Reusable drivers assignable to transport services and visa groups.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `name` varchar(150).
  - `phone` varchar(50) nullable.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Indexes:
  - Index (`company_id`, `name`), (`company_id`, `phone`), (`company_id`, `is_active`).
- Model fillable:
  - `company_id`, `name`, `phone`, `notes`, `is_active`.

### umrah.visa_groups
- Purpose: Main operational record for a group sent by an agent.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `agent_id` uuid FK -> `umrah.agents.id`.
  - `vendor_id` uuid nullable FK -> `umrah.visa_vendors.id`.
  - `visa_service_id` uuid nullable FK -> `umrah.visa_services.id`.
  - `transport_service_id` uuid nullable FK -> `umrah.transport_services.id`.
  - `driver_id` uuid nullable FK -> `umrah.drivers.id`.
  - `group_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `status` varchar(30) default `draft`.
  - `travel_date` date nullable.
  - `flight_info` jsonb nullable.
  - `hotel_info` jsonb nullable.
  - `transport_required` boolean default false.
  - `transport_quantity` integer default 0.
  - `transport_pax_capacity` integer nullable. Copied from selected transport service and overrideable on the group.
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
  - `visa_status` in `pending`, `received`, `submitted`, `embassy`, `approved`, `rejected`, `delivered`.
- Model fillable:
  - `company_id`, `visa_group_id`, `full_name`, `passport_number`, `nationality`, `date_of_birth`, `visa_status`, `notes`, `sort_order`.

### umrah.vouchers
- Purpose: Travel voucher / journey schedule for all or selected passengers in a visa group.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `visa_group_id` uuid FK -> `umrah.visa_groups.id`.
  - `agent_id` uuid FK -> `umrah.agents.id`.
  - `voucher_number` varchar(50), unique per company.
  - `title` varchar(255).
  - `status` varchar(30) default `draft`.
  - `onward_airline` varchar(150). Stores the airline IATA code from the module airline catalogue.
  - `onward_flight_number` varchar(80) nullable.
  - `onward_departure_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `onward_arrival_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `onward_departure_at` timestamp.
  - `onward_arrival_at` timestamp.
  - `return_airline` varchar(150). Stores the airline IATA code from the module airline catalogue.
  - `return_flight_number` varchar(80) nullable.
  - `return_departure_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `return_arrival_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `return_departure_at` timestamp.
  - `return_arrival_at` timestamp.
  - `hotel_stays` jsonb default `[]`. Each stay has hotel name, city, check-in date, checkout date, and notes.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK -> `auth.users.id`.
  - timestamps, soft deletes.
- Check:
  - `status` in `draft`, `issued`, `cancelled`.
- Model fillable:
  - `company_id`, `visa_group_id`, `agent_id`, `voucher_number`, `title`, `status`, `onward_airline`, `onward_flight_number`, `onward_departure_city`, `onward_arrival_city`, `onward_departure_at`, `onward_arrival_at`, `return_airline`, `return_flight_number`, `return_departure_city`, `return_arrival_city`, `return_departure_at`, `return_arrival_at`, `hotel_stays`, `notes`, `created_by_user_id`.

### umrah.voucher_passengers
- Purpose: Passengers included in a voucher.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `voucher_id` uuid FK -> `umrah.vouchers.id`.
  - `visa_group_id` uuid FK -> `umrah.visa_groups.id`.
  - `passenger_id` uuid FK -> `umrah.passengers.id`.
  - timestamps, soft deletes.
- Constraints:
  - Unique (`company_id`, `visa_group_id`, `passenger_id`) for active voucher assignment in phase 1.
- Model fillable:
  - `company_id`, `voucher_id`, `visa_group_id`, `passenger_id`.

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
- Selecting a transport service copies its default sale/cost and passenger capacity into group fields; users may override before saving.
- Selecting a transport service copies its default driver into the group if a driver is assigned to that service; users may override before saving.
- Selecting an agent defaults new passenger nationality to the agent country. Allowed nationality options in phase 1 are Pakistan, Bangladesh, India, Turkiye, United Kingdom, and United States.
- Agent totals are recalculated from active non-cancelled groups.
- Passenger count is recalculated from passengers unless explicitly entered on group creation.
- Payments cannot exceed group balance in phase 1.
- Payment records update group and agent totals inside one database transaction.
- Vouchers may include all, one, or some passengers from one group.
- A passenger can be included in one active voucher per group in phase 1. Later voucher creation shows only remaining unassigned passengers.
- Admin/accountant users can create vouchers for any agent group. Member users can create vouchers only for the agent record linked to their login user through `umrah.agents.user_id`.
- Voucher hotel stay dates must be within the onward departure and return arrival window.
- Hotel checkout must be on or after check-in.

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
