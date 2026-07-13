# Schema Contract - Umrah Visa Operations (umrah)

Single source of truth for Umrah visa groups, agents, passports, visa vendors, transport requirements, payments, and earnings. Read this before touching Umrah migrations, models, services, or controllers.

**Module Location:** `modules/Umrah/`
**Namespace:** `App\Modules\Umrah`

## Direction

- Umrah is a separate module from Fuel Station.
- Companies with industry `umrah` or `travel` should see Umrah-specific features, not petrol pump workflows.
- `Visa Group` is the operational single source of truth.
- Flight and hotel are informational in phase 1.
- Transport is mandatory for visa groups. Standard bus transport is included in the visa vendor cost; specialized transport can replace it.
- Transport service is the vehicle source of truth. Do not maintain a separate vehicle type setup screen.
- Transport sectors and journey packages are configurable. Fares belong to a transport service and either one sector or one journey package.
- Group transport items are immutable pricing snapshots. Later fare changes must not alter historical group totals.
- Drivers are reusable transport staff records. A transport service can have a default driver, and group creation can override the driver for that trip.
- Visa and transport services provide default retail/cost amounts. Visa vendor adult and child amounts are used for group pricing from passenger DOB. Users may override copied transport prices/costs per group.

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
  - `logo_url` varchar(500) nullable.
  - `notes` text nullable.
  - `can_create_voucher` boolean default true.
  - `can_approve_voucher` boolean default false.
  - `can_edit_voucher` boolean default false.
  - `voucher_cutoff_hours` integer default 6. Allowed values: 2, 6, 12, 18, 24, 48.
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
  - `company_id`, `user_id`, `agent_number`, `name`, `phone`, `email`, `city`, `country`, `logo_url`, `notes`, `can_create_voucher`, `can_approve_voucher`, `can_edit_voucher`, `voucher_cutoff_hours`, `total_receivable`, `total_paid`, `balance`, `is_active`.

### umrah.visa_vendors
- Purpose: Visa suppliers, usually government or service providers.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `vendor_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `vendor_type` varchar(30) default `government`.
  - `phone`, `email`, `city` nullable.
  - `logo_url` varchar(500) nullable.
  - `notes` text nullable.
  - `adult_retail_amount` numeric(15,2) default 0.
  - `adult_cost_amount` numeric(15,2) default 0.
  - `child_retail_amount` numeric(15,2) default 0.
  - `child_cost_amount` numeric(15,2) default 0.
  - `included_bus_cost_amount` numeric(15,2) default 50. Per visa passenger cost already included in adult/child vendor cost for mandatory all-sector bus transport.
  - `total_cost` numeric(15,2) default 0.
  - `total_paid` numeric(15,2) default 0.
  - `balance` numeric(15,2) default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Check:
  - `vendor_type` in `government`, `visa_provider`, `transport_provider`, `hotel`, `other`.
- Model fillable:
  - `company_id`, `vendor_number`, `name`, `vendor_type`, `phone`, `email`, `city`, `notes`, `adult_retail_amount`, `adult_cost_amount`, `child_retail_amount`, `child_cost_amount`, `included_bus_cost_amount`, `total_cost`, `total_paid`, `balance`, `is_active`.

### umrah.visa_services (legacy)
- Purpose: Reusable visa service templates with default adult and child retail/cost amounts.
- Status: Legacy/dormant. New group creation prices visas directly from the selected visa vendor.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `vendor_id` uuid nullable FK -> `umrah.visa_vendors.id`.
  - `name` varchar(150).
  - `retail_amount` numeric(15,2) default 0. Adult retail amount.
  - `cost_amount` numeric(15,2) default 0. Adult cost amount.
  - `child_retail_amount` numeric(15,2) default 0.
  - `child_cost_amount` numeric(15,2) default 0.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Unique (`company_id`, `name`).
- Model fillable:
  - `company_id`, `vendor_id`, `name`, `retail_amount`, `cost_amount`, `child_retail_amount`, `child_cost_amount`, `notes`, `is_active`.

### umrah.hotel_vendors
- Purpose: Hotel suppliers kept separate from visa and transport vendors.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `vendor_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `phone`, `email`, `city` nullable.
  - `notes` text nullable.
  - `total_cost`, `total_paid`, `balance` numeric(15,2) default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Model fillable:
  - `company_id`, `vendor_number`, `name`, `phone`, `email`, `city`, `logo_url`, `notes`, `total_cost`, `total_paid`, `balance`, `is_active`.

### umrah.hotels
- Purpose: Hotels available for voucher stays.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `hotel_vendor_id` uuid FK -> `umrah.hotel_vendors.id`.
  - `name` varchar(255).
  - `city` varchar(100). Allowed values: `Makkah`, `Madinah`.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Unique (`company_id`, `name`, `city`).
- Check: `city` in `Makkah`, `Madinah`.
- Model fillable:
  - `company_id`, `hotel_vendor_id`, `name`, `city`, `notes`, `is_active`.

### umrah.hotel_room_rates
- Purpose: Per-bed-per-night retail and cost rates offered by a hotel.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `hotel_id` uuid FK -> `umrah.hotels.id`.
  - `room_type` varchar(30). Values: `sharing` (priced per allocated bed), `double` (2 beds), `triple` (3 beds), `quad` (4 beds), `quint` (5 beds). Hotels do not offer a single-bed room type.
  - `retail_amount`, `cost_amount` numeric(15,2) default 0. Amount per bed per night; a room stay total is rate x beds per room x room count x nights. For `sharing`, the voucher quantity is the allocated bed count and the total is rate x allocated beds x nights.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Unique (`company_id`, `hotel_id`, `room_type`).
- Model fillable:
  - `company_id`, `hotel_id`, `room_type`, `retail_amount`, `cost_amount`, `is_active`.

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

### umrah.transport_sectors
- Purpose: Configurable directional transport routes.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `code` varchar(50), unique per company.
  - `name` varchar(150).
  - `origin` varchar(150).
  - `destination` varchar(150).
  - `sort_order` integer default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Model fillable:
  - `company_id`, `code`, `name`, `origin`, `destination`, `sort_order`, `is_active`.

### umrah.transport_packages
- Purpose: Named complete-journey bundles made from one or more sectors.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `name` varchar(150), unique per company.
  - `notes` text nullable.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Model fillable:
  - `company_id`, `name`, `notes`, `is_active`.

### umrah.transport_package_sectors
- Purpose: Ordered sectors included in a transport package.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `transport_package_id` uuid FK -> `umrah.transport_packages.id`.
  - `transport_sector_id` uuid FK -> `umrah.transport_sectors.id`.
  - `sort_order` integer default 0.
  - timestamps, soft deletes.
- Constraints:
  - Unique (`company_id`, `transport_package_id`, `transport_sector_id`).
- Model fillable:
  - `company_id`, `transport_package_id`, `transport_sector_id`, `sort_order`.

### umrah.transport_fares
- Purpose: Retail and cost rates for a vehicle/service covering one sector or one journey package.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `transport_service_id` uuid FK -> `umrah.transport_services.id`.
  - `transport_sector_id` uuid nullable FK -> `umrah.transport_sectors.id`.
  - `transport_package_id` uuid nullable FK -> `umrah.transport_packages.id`.
  - `name` varchar(150).
  - `charging_basis` varchar(30) default `per_vehicle`.
  - `sale_amount` numeric(15,2) default 0.
  - `cost_amount` numeric(15,2) default 0.
  - `hajj_terminal_sale_amount` numeric(15,2) default 90.
  - `hajj_terminal_cost_amount` numeric(15,2) default 0.
  - `is_active` boolean default true.
  - timestamps, soft deletes.
- Checks:
  - Exactly one of `transport_sector_id` and `transport_package_id` is set.
  - `charging_basis` in `per_vehicle`, `per_passenger`, `flat_group`.
- Model fillable:
  - `company_id`, `transport_service_id`, `transport_sector_id`, `transport_package_id`, `name`, `charging_basis`, `sale_amount`, `cost_amount`, `hajj_terminal_sale_amount`, `hajj_terminal_cost_amount`, `is_active`.

### umrah.group_transport_items
- Purpose: Historical transport selection and fare snapshot for a visa group. One item represents either a sector or a complete journey package.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `visa_group_id` uuid FK -> `umrah.visa_groups.id`.
  - `transport_fare_id` uuid nullable FK -> `umrah.transport_fares.id`.
  - `transport_service_id` uuid nullable FK -> `umrah.transport_services.id`.
  - `transport_sector_id` uuid nullable FK -> `umrah.transport_sectors.id`.
  - `transport_package_id` uuid nullable FK -> `umrah.transport_packages.id`.
  - `driver_id` uuid nullable FK -> `umrah.drivers.id`.
  - `description` varchar(255).
  - `scheduled_at` timestamp nullable.
  - `terminal` varchar(30) default `standard`.
  - `charging_basis` varchar(30) default `per_vehicle`.
  - `quantity` integer default 1.
  - `passenger_count` integer default 0.
  - `unit_sale_amount` numeric(15,2) default 0.
  - `unit_cost_amount` numeric(15,2) default 0.
  - `surcharge_sale_amount` numeric(15,2) default 0.
  - `surcharge_cost_amount` numeric(15,2) default 0.
  - `total_sale_amount` numeric(15,2) default 0.
  - `total_cost_amount` numeric(15,2) default 0.
  - `notes` text nullable.
  - timestamps, soft deletes.
- Checks:
  - `terminal` in `standard`, `hajj`.
  - `charging_basis` in `per_vehicle`, `per_passenger`, `flat_group`.
- Model fillable:
  - all business columns above.

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
  - `vendor_id` uuid FK -> `umrah.visa_vendors.id`. Source of visa retail/cost defaults.
  - `visa_service_id` uuid nullable FK -> `umrah.visa_services.id`. Legacy only.
  - `transport_service_id` uuid nullable FK -> `umrah.transport_services.id`.
  - `driver_id` uuid nullable FK -> `umrah.drivers.id`.
  - `group_number` varchar(50), unique per company.
  - `name` varchar(255).
  - `status` varchar(30) default `draft`. Values: `draft`, `approved`.
  - `travel_date` date nullable.
  - `flight_info` jsonb nullable.
  - `hotel_info` jsonb nullable.
  - `transport_required` boolean default false.
  - `transport_mode` varchar(30) default `standard_bus`. Values: `standard_bus`, `specialized`.
  - `included_bus_cost_per_passenger` numeric(15,2) default 50. Snapshot from visa vendor.
  - `included_bus_cost_deduction` numeric(15,2) default 0. Deducted only when specialized transport replaces included bus transport.
  - `transport_quantity` integer default 0.
  - `transport_pax_capacity` integer nullable. Copied from selected transport service and overrideable on the group.
  - `passenger_count` integer default 0.
  - `visa_sale_amount` numeric(15,2) default 0.
  - `transport_amount` numeric(15,2) default 0.
  - `discount_amount` numeric(15,2) default 0.
  - `visa_cost_amount` numeric(15,2) default 0.
  - `transport_cost_amount` numeric(15,2) default 0.
  - `hotel_amount` numeric(15,2) default 0.
  - `hotel_cost_amount` numeric(15,2) default 0.
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
  - `imported_age` integer nullable. Age from Go VT mutamer exports or manual age entry when DOB is unavailable.
  - `service_type` varchar(30) default `visa_transport`. Values: `visa_transport`, `transport_only`.
  - `transport_charge_amount` numeric(15,2) default 0. Passenger-specific sale for a traveller whose visa came from another provider.
  - `visa_status` varchar(30) default `pending`.
  - `notes` text nullable.
  - `sort_order` integer default 0.
  - timestamps, soft deletes.
- Check:
  - `visa_status` in `pending`, `received`, `submitted`, `embassy`, `approved`, `rejected`, `delivered`.
- Model fillable:
  - `company_id`, `visa_group_id`, `full_name`, `passport_number`, `nationality`, `date_of_birth`, `imported_age`, `service_type`, `transport_charge_amount`, `visa_status`, `notes`, `sort_order`.

### umrah.vouchers
- Purpose: Travel voucher / journey schedule for all or selected passengers in a visa group.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK.
  - `visa_group_id` uuid FK -> `umrah.visa_groups.id`.
  - `agent_id` uuid FK -> `umrah.agents.id`.
  - `voucher_number` varchar(50), unique per company.
  - `title` varchar(255).
  - `service_bundle` varchar(40) default `visa_transport_hotel`. Records the voucher-level itinerary mode. The voucher form exposes only a Hotel-only toggle; otherwise passenger `service_type` determines visa plus mandatory transport or transport only. Hotel inclusion is inferred from Company-supplied stays.
  - `status` varchar(30) default `draft`.
  - `onward_airline` varchar(150) nullable. Required unless `service_bundle = hotel`. Stores the airline IATA code from the module airline catalogue.
  - `onward_flight_number` varchar(80) nullable. UI and validation limit to 5 characters.
  - `onward_departure_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `onward_arrival_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `onward_departure_at` timestamp nullable. Required unless `service_bundle = hotel`.
  - `onward_arrival_at` timestamp nullable. Required unless `service_bundle = hotel`.
  - `return_airline` varchar(150) nullable. Required unless `service_bundle = hotel`. Stores the airline IATA code from the module airline catalogue.
  - `return_flight_number` varchar(80) nullable. UI and validation limit to 5 characters.
  - `return_departure_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `return_arrival_city` varchar(150) nullable for existing records, required when creating a voucher. Stores an airport IATA code from the module airport-city catalogue.
  - `return_departure_at` timestamp nullable. Required unless `service_bundle = hotel`.
  - `return_arrival_at` timestamp nullable. Required unless `service_bundle = hotel`.
  - `hotel_stays` jsonb default `[]`. Required for every voucher because the voucher must show the passenger's complete journey and stays, even when hotel service was bought elsewhere. Each stay has hotel name, city, check-in date, checkout date, and notes. Hotel stays do not record check-in or checkout times. New vouchers start with three editable stays: Makkah, Madinah, Makkah.
    - Selecting a stay checkout date sets the next stay check-in to the same date by default. Same-day hotel transfers are valid; a later stay cannot begin before the previous checkout date.
    - Company stay snapshot also stores `hotel_id`, `hotel_vendor_id`, `room_type`, `room_count`, `beds_per_room`, `night_count`, per-bed unit retail/cost and total retail/cost.
    - Self-arranged stay stores `source = self` and zero retail/cost while preserving itinerary information.
    - A Company stay uses the configured company hotel and is charged; a Self stay is itinerary-only and has zero retail/cost.
  - `hotel_sale_amount`, `hotel_cost_amount` numeric(15,2) default 0.
  - `hotel_sale_transaction_id`, `hotel_cost_transaction_id` uuid nullable FK -> `acct.transactions.id`.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK -> `auth.users.id`.
  - timestamps, soft deletes.
- Check:
  - `status` in `draft`, `approved`.
  - `service_bundle` in `visa_transport`, `visa_transport_hotel`, `transport`, `transport_hotel`, `hotel`.
- Business rules:
  - Hotel Only vouchers do not require or display flight or transport information. Their scheduling deadline is measured from the first hotel check-in.
  - For non-hotel-only vouchers, each selected passenger defaults to `visa_transport`; selecting Transport only updates the passenger service and group financials.
- Model fillable:
  - `company_id`, `visa_group_id`, `agent_id`, `voucher_number`, `title`, `service_bundle`, `status`, `onward_airline`, `onward_flight_number`, `onward_departure_city`, `onward_arrival_city`, `onward_departure_at`, `onward_arrival_at`, `return_airline`, `return_flight_number`, `return_departure_city`, `return_arrival_city`, `return_departure_at`, `return_arrival_at`, `hotel_stays`, `hotel_sale_amount`, `hotel_cost_amount`, `notes`, `created_by_user_id`, `hotel_sale_transaction_id`, `hotel_cost_transaction_id`.

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
  - `total_receivable = visa_sale_amount + transport_amount + hotel_amount - discount_amount`.
  - `balance = total_receivable - total_paid`.
  - `profit = visa_sale_amount + transport_amount + hotel_amount - discount_amount - visa_cost_amount - transport_cost_amount - hotel_cost_amount`.
- Selecting a visa vendor calculates visa retail/cost only for `visa_transport` passengers from DOB when available, otherwise `imported_age`, using vendor adult/child rates. Child = under 12 years, adult = 12+ years. DOB age is calculated against travel date when available, otherwise today's date.
- Standard bus transport is mandatory and included in the visa vendor cost. The vendor's `included_bus_cost_amount` defaults to SAR 50 per visa passenger and covers the complete standard journey.
- When `transport_mode = standard_bus`, `included_bus_cost_deduction = 0` and no separate bus transport cost or sale is added.
- When `transport_mode = specialized`, `included_bus_cost_deduction = min(base visa cost, included bus cost per passenger x visa passenger count)`. Adjusted visa cost equals base visa cost minus this deduction. Selected specialized fare costs are then recorded in `transport_cost_amount`.
- `transport_amount` equals group transport item retail totals plus passenger-specific `transport_charge_amount` values for `transport_only` passengers.
- Group transport fare snapshots calculate by charging basis: per vehicle uses quantity, per passenger uses passenger count, and flat group uses one unit. Hajj Terminal surcharge uses the same basis and is added when terminal is `hajj`.
- All passengers count toward transport capacity. Only `visa_transport` passengers count toward visa vendor retail and cost.
- Selecting an agent defaults new passenger nationality to the agent country. Allowed nationality options in phase 1 are Pakistan, Bangladesh, India, Turkiye, United Kingdom, and United States.
- Agent totals are recalculated from active non-cancelled groups.
- Passenger count is recalculated from passengers unless explicitly entered on group creation.
- Go VT mutamer workbook import reads only mutamer name, mutamer age, passport number, and nationality. Imported rows remain editable before saving the visa group.
- If group code is blank, the next sequential group number is used. If group name is blank, the service generates `{agent name} - {pax} pax - {YYYYMMDD HHMMSS}`.
- Payments cannot exceed group balance in phase 1.
- Payment records update group and agent totals inside one database transaction.
- Vouchers may include all, one, or some passengers from one group.
- A passenger can be included in one active voucher per group in phase 1. Later voucher creation shows only remaining unassigned passengers.
- Every flight arrival must be strictly after its departure, and return departure must be strictly after onward arrival.
- Each stay checkout must be strictly after check-in. Every later stay must start strictly after the previous stay ends, and all stays must remain between onward arrival and return departure.
- Return airline defaults to the onward airline. Return origin/destination default to the inverse of the onward destination/origin, but all defaults remain editable.
- Approved vouchers add company-supplied hotel sale/cost snapshots to the linked group and agent balance and create separate hotel accounting journals. Draft vouchers do not affect accounting.
- Externally purchased hotel stays remain printable itinerary entries with zero hotel sale and cost.
- Agent logins use a unique username and hashed password in `auth.users`; plaintext credentials are never stored in Umrah tables. Existing users may continue signing in with email.
- Agent voucher capabilities are managed on the agent page. Voucher creation defaults on; approval and draft editing default off.
- Agent voucher creation and draft edits are rejected when onward departure is less than the configured cutoff of 2, 6, 12, 18, 24, or 48 hours away. Company staff are not subject to the agent cutoff.
- Approved vouchers are immutable. Changes require a future amendment/reversal workflow so posted hotel accounting is never silently changed.
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
