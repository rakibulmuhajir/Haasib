# Schema Contract — Fuel Station Operations (fuel)

Single source of truth for fuel station specific operations: pumps, rate changes, tank/pump readings, investors, amanat deposits, and attendant handovers. Read this before touching migrations, models, or services.

**Module Location:** `modules/FuelStation/`
**Namespace:** `App\Modules\FuelStation`

### Daily close bank withdrawals

`bank_withdrawals` is an optional list of `{bank_account_id, amount, reference?, purpose?}` in the daily-close form and saved `metadata.form_input`. Each row transfers cash from an active, company-owned bank account in the company base currency into the configured station cash drawer. It increases Money In and expected drawer cash; it does not create revenue. Posting credits the selected bank and includes the matching debit in the close's cash entry. Metadata stores `bank_withdrawals` (total) and `bank_withdrawals_by_account` (amounts by account). Park/resume preserves the rows without posting them.

---

## Architectural Decisions (REJECTED → ACCEPTED)

### ❌ REJECTED: Extending Core Accounting Tables
**Initial approach:** Add fuel-specific columns directly to `acct.customers` and `acct.invoices`.

**Why rejected:**
- Pollutes core accounting module with industry-specific fields
- Makes accounting module non-generic (other industries would add their own columns)
- Tight coupling between fuel and accounting modules
- Fields like `pump_id`, `attendant_transit`, `cnic`, `amanat_balance` are meaningless for non-fuel companies

### ✅ ACCEPTED: Separate 1:1 Linked Tables
**Final approach:** Create `fuel.customer_profiles` and `fuel.sale_metadata` tables that link 1:1 to core tables.

**Benefits:**
- Core accounting module stays industry-agnostic
- Other industries can follow same pattern (`hospitality.guest_profiles`, `retail.sale_metadata`)
- Clear module boundaries
- Fuel module can be removed without touching accounting
- Easier to reason about what's "fuel stuff" vs "accounting stuff"

### ❌ REJECTED: Storing Current Rates on Items
**Initial approach:** Add `purchase_rate` and `sale_rate` columns to `inv.items`.

**Why rejected:** Creates "historical lies" — rates change every 2 weeks by government mandate. Looking at an old invoice, you'd see current rate, not rate at time of sale.

### ✅ ACCEPTED: Rate Changes Table as Source of Truth
`fuel.rate_changes` stores all rate changes with effective dates. Current rate = most recent effective rate. Historical rates preserved.

### ❌ REJECTED: Single `customer_type` Enum
**Initial approach:** `customer_type ENUM('regular', 'credit', 'amanat', 'investor')`.

**Why rejected:** Real customers overlap. Owner can be credit customer + investor. Single enum forces false choice.

### ✅ ACCEPTED: Non-Exclusive Boolean Flags
`is_credit_customer`, `is_amanat_holder`, `is_investor` — all can be true simultaneously.

### ❌ REJECTED: Simple Investor Entitlement Formula
**Initial approach:** `units_entitled = investment / current_purchase_rate` (recalculated).

**Why rejected:** Government changes rates every 2 weeks. Recalculating creates disputes: "I invested 100k when petrol was 250/L, that's 400L. Now rate is 260/L and you say 384L?"

### ✅ ACCEPTED: Lot Model with Locked Rates
`fuel.investor_lots` locks `entitlement_rate` at deposit time. Units never change regardless of rate changes.

### ❌ REJECTED: Auto-Posting Variance from Tank Readings
**Initial approach:** Automatically create JE when variance detected.

**Why rejected:** Staff would repeatedly "fix numbers" by entering adjusted readings. No audit trail, no approval.

### ✅ ACCEPTED: Posting Workflow (draft → confirmed → posted)
Draft readings can be edited. Confirmation requires manager. JE only created on posting. Prevents gaming.

---

## Guardrails
- Schema: `fuel` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes where applicable (pumps, investors).
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Quantity precision: `numeric(12,2)` for liters.
- Rate precision: `numeric(10,2)` for per-liter rates.
- Amount precision: `numeric(15,2)` for monetary values.
- **Variance JE only from tank_readings posting workflow** — never auto-post.
- **Investor entitlements locked at deposit time** — lot model prevents disputes.

## Dependencies on Existing Tables

### Extensions to `inv.items`
Add columns:
- `fuel_category` varchar(20) nullable — 'petrol', 'diesel', 'high_octane', 'lubricant', null for non-fuel.
- `avg_cost` numeric(10,4) nullable — Weighted Average Cost for COGS calculation.

### Extensions to `inv.warehouses`
Add columns:
- `warehouse_type` varchar(20) not null default 'standard' — 'standard', 'tank'.
- `capacity` numeric(12,2) nullable — tank capacity in liters.
- `low_level_alert` numeric(12,2) nullable — alert threshold in liters.
- `linked_item_id` uuid nullable FK → `inv.items.id` — fuel type this tank holds.

Add constraint:
```sql
ALTER TABLE inv.warehouses ADD CONSTRAINT tank_requires_item_and_capacity
  CHECK (warehouse_type != 'tank' OR (linked_item_id IS NOT NULL AND capacity IS NOT NULL));
```

### NO Changes to Core Accounting Tables
The fuel module does NOT extend `acct.customers` or `acct.invoices` directly.
Instead, fuel-specific data lives in separate 1:1 linked tables:
- `fuel.customer_profiles` — fuel-specific customer data
- `fuel.sale_metadata` — fuel-specific invoice/sale data

This keeps the core accounting module industry-agnostic.

## Tables

### fuel.customer_profiles
- Purpose: Fuel-specific customer data. Links 1:1 to `acct.customers`.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null unique FK → `acct.customers.id` (CASCADE/CASCADE).
  - `is_credit_customer` boolean not null default false.
  - `is_amanat_holder` boolean not null default false.
  - `is_investor` boolean not null default false.
  - `relationship` varchar(50) nullable — 'owner', 'employee', 'external'.
  - `cnic` varchar(20) nullable — Pakistani national ID.
  - `amanat_balance` numeric(15,2) not null default 0.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique `customer_id` (1:1 relationship).
  - Index: `company_id`; (`company_id`, `is_credit_customer`); (`company_id`, `is_amanat_holder`); (`company_id`, `is_investor`).
  - CHECK: relationship IN ('owner', 'employee', 'external') OR NULL.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.customer_profiles'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','is_credit_customer','is_amanat_holder','is_investor','relationship','cnic','amanat_balance'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','is_credit_customer'=>'boolean','is_amanat_holder'=>'boolean','is_investor'=>'boolean','amanat_balance'=>'decimal:2','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer.
- Validation:
  - `customer_id`: required|uuid|exists:acct.customers,id|unique.
  - `relationship`: nullable|in:owner,employee,external.
  - `cnic`: nullable|string|max:20.
- Business rules:
  - Created on-demand when customer needs fuel-specific tracking.
  - `amanat_balance` updated via AmanatTransaction records.
  - Flags are non-exclusive (customer can be credit + investor + amanat holder).

### fuel.sale_metadata
- Purpose: Fuel-specific sale/invoice data. Links 1:1 to `acct.invoices`.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `invoice_id` uuid not null unique FK → `acct.invoices.id` (CASCADE/CASCADE).
  - `sale_type` varchar(20) not null — 'retail', 'bulk', 'credit', 'amanat', 'investor', 'parco_card'.
  - `pump_id` uuid nullable FK → `fuel.pumps.id` (SET NULL/CASCADE).
  - `attendant_transit` boolean not null default false — cash not yet handed over.
  - `discount_reason` varchar(50) nullable — 'bulk_discount', 'investor_commission'.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique `invoice_id` (1:1 relationship).
  - Index: `company_id`; (`company_id`, `sale_type`); (`company_id`, `attendant_transit`).
  - CHECK: sale_type IN ('retail', 'bulk', 'credit', 'amanat', 'investor', 'parco_card').
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.sale_metadata'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','invoice_id','sale_type','pump_id','attendant_transit','discount_reason'];`
  - `$casts = ['company_id'=>'string','invoice_id'=>'string','pump_id'=>'string','attendant_transit'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Invoice; belongsTo Pump.
- Validation:
  - `invoice_id`: required|uuid|exists:acct.invoices,id|unique.
  - `sale_type`: required|in:retail,bulk,credit,amanat,investor,parco_card.
  - `pump_id`: nullable|uuid|exists:fuel.pumps,id.
- Business rules:
  - Created alongside invoice for fuel sales.
  - `attendant_transit = true` until AttendantHandover recorded.
  - `parco_card` sales go to Parco Clearing account, not bank.

### fuel.customer_fuel_discounts
- Purpose: A customer's negotiated discount, per fuel item — either a percent of the sale
  or a flat amount per litre (e.g. a transporter gets Rs 3/L on diesel, nothing on petrol).
  Set on the Fuel Credit Customer page; read by `CustomerFuelDiscountService`, the only
  place this discount is looked up or priced — the standalone Fuel → Sales credit sale and
  a manual Daily Close credit row both go through it.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null FK → `acct.customers.id` (CASCADE/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (CASCADE/CASCADE).
  - `discount_type` varchar(20) not null — 'percent', 'per_litre'.
  - `value` numeric(12,4) not null — the percent (≤ 100) or the Rs/L amount; always > 0.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `customer_id`, `item_id`) — one discount per customer per fuel item.
  - Index: `company_id`.
  - CHECK: discount_type IN ('percent', 'per_litre').
  - CHECK: value > 0.
  - CHECK: discount_type <> 'percent' OR value <= 100.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.customer_fuel_discounts'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','item_id','discount_type','value'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','item_id'=>'string','value'=>'decimal:4','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo Item.
- Validation (`UpdateCustomerFuelDiscountsRequest`):
  - `discounts.*.item_id`: required|uuid|exists:inv.items,id.
  - `discounts.*.discount_type`: nullable|in:percent,per_litre — omitted/null clears the row's discount.
  - `discounts.*.value`: nullable|numeric|min:0.0001|required_with:discount_type; ≤ 100 when percent.
- Business rules:
  - `CustomerFuelDiscountService::for($companyId, $customerId, $itemId)` returns the active
    discount or null; `::amount($discount, $litres, $grossAmount)` prices it (`per_litre` →
    `round($litres * $value, 2)`, `percent` → `round($grossAmount * $value / 100, 2)`), never
    more than the gross.
  - `FuelSaleService::createSale` applies the stored discount automatically when the request
    supplies neither `discount_per_liter` nor `discount_percent`; an explicit value on the
    request overrides it. Exactly one of the two is ever honoured.
  - A manual Daily Close credit row with `item_id` (+ `litres` for a per-litre discount) is
    repriced server-side by `DailyCloseCreditSaleService::prepare` — a client-sent discount
    figure is never trusted. A per-litre discount without `litres` is refused
    (`ValidationException`). The invoice is created for the net amount; the close's journal
    debits the same Sales Discounts account `FuelSaleService::postDiscount` uses (resolved
    once via `StationAccountMapper::resolveMappedAccountId`) for the discount, so
    `Dr AR (net) + Dr Sales Discounts (discount) = gross`, and expected drawer cash is
    reduced by the gross exactly once regardless of the discount.

### fuel.pumps
- Purpose: Dispensing machines (fuel pumps) with meter tracking.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `name` varchar(100) not null — 'Pump 1', 'Pump 2'.
  - `tank_id` uuid not null FK → `inv.warehouses.id` (RESTRICT/CASCADE).
  - `current_meter_reading` numeric(12,2) not null default 0.
  - `is_active` boolean not null default true.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `name`) where deleted_at is null.
  - Index: `company_id`; `tank_id`; (`company_id`, `is_active`).
  - CHECK: tank must have warehouse_type='tank' (enforce in app layer).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.pumps'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','name','tank_id','current_meter_reading','is_active','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','tank_id'=>'string','current_meter_reading'=>'decimal:2','is_active'=>'boolean','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Tank (Warehouse); hasMany PumpReading.
- Validation:
  - `name`: required|string|max:100; unique per company (soft-delete aware).
  - `tank_id`: required|uuid|exists:inv.warehouses,id (must be tank type).
  - `current_meter_reading`: numeric|min:0.
- Business rules:
  - Pump links to tank; tank links to fuel item (derivation chain).
  - current_meter_reading updated after each pump_reading.
  - Cannot delete pump with readings; deactivate instead.

### fuel.rate_changes
- Purpose: Government-mandated price changes. **SOURCE OF TRUTH for current rates.**
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (RESTRICT/CASCADE).
  - `effective_date` date not null.
  - `purchase_rate` numeric(10,2) not null — new purchase rate per liter.
  - `sale_rate` numeric(10,2) not null — new sale rate per liter.
  - `stock_quantity_at_change` numeric(12,2) nullable — snapshot for margin impact.
  - `margin_impact` numeric(12,2) nullable — (new_margin - old_margin) * stock.
  - `revaluation_amount` numeric(15,2) nullable — inventory revaluation amount posted for the rate change.
  - `previous_avg_cost` numeric(10,4) nullable — item average cost before revaluation.
  - `journal_entry_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `snapshot_tank_id` uuid nullable FK → `inv.warehouses.id` (SET NULL/CASCADE) — tank used for optional midnight/rate-change dip.
  - `snapshot_stick_reading` numeric(12,2) nullable — physical stick reading at rate change.
  - `snapshot_dip_liters` numeric(12,2) nullable — physical tank quantity at rate change.
  - `snapshot_nozzle_readings` jsonb nullable — optional per-nozzle meter snapshot at rate change; rows contain `nozzle_id`, `electronic_reading`, `manual_reading`.
  - `notes` text nullable.
  - `created_by_user_id` uuid not null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `item_id`, `effective_date`).
  - Index: `company_id`; `item_id`; (`company_id`, `effective_date` DESC).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.rate_changes'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','item_id','effective_date','purchase_rate','sale_rate','stock_quantity_at_change','margin_impact','revaluation_amount','previous_avg_cost','journal_entry_id','snapshot_tank_id','snapshot_stick_reading','snapshot_dip_liters','snapshot_nozzle_readings','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','item_id'=>'string','effective_date'=>'date','purchase_rate'=>'decimal:2','sale_rate'=>'decimal:2','stock_quantity_at_change'=>'decimal:2','margin_impact'=>'decimal:2','revaluation_amount'=>'decimal:2','previous_avg_cost'=>'decimal:4','journal_entry_id'=>'string','snapshot_tank_id'=>'string','snapshot_stick_reading'=>'decimal:2','snapshot_dip_liters'=>'decimal:2','snapshot_nozzle_readings'=>'array','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Item.
- Validation:
  - `item_id`: required|uuid|exists:inv.items,id (must have fuel_category).
  - `effective_date`: required|date.
  - `purchase_rate`: required|numeric|min:0.
  - `sale_rate`: required|numeric|min:0.
  - `snapshot_tank_id`: nullable|uuid|exists:inv.warehouses,id.
  - `snapshot_stick_reading`: nullable|numeric|min:0.
  - `snapshot_dip_liters`: nullable|numeric|min:0.
  - `snapshot_nozzle_readings`: nullable|array.
- Business rules:
  - First row created during onboarding with initial rates.
  - To get current rate: `WHERE effective_date <= NOW() ORDER BY effective_date DESC LIMIT 1`.
  - Do NOT store rates on inv.items — rates change over time.
  - margin_impact calculated: (new_sale_rate - new_purchase_rate - old_margin) * stock.
  - Optional rate-change snapshots are used by Daily Close to split nozzle sales before/after a midnight rate change.
  - If `snapshot_dip_liters` is entered, it is used as the stock quantity for rate-change revaluation.

### fuel.tank_readings
- Purpose: Manual dip measurements for variance calculation. **Variance JE only created from here.**
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `tank_id` uuid not null FK → `inv.warehouses.id` (RESTRICT/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (RESTRICT/CASCADE) — derived from tank→linked_item_id.
  - `reading_date` timestamp not null.
  - `reading_type` varchar(20) not null — 'opening', 'closing', 'spot_check'.
  - `dip_measurement_liters` numeric(12,2) not null — actual dip reading.
  - `system_calculated_liters` numeric(12,2) not null — expected from purchases/sales.
  - `variance_liters` numeric(10,2) not null — dip - system.
  - `variance_type` varchar(10) not null — 'loss', 'gain', 'none'.
  - `variance_reason` varchar(30) nullable — enum for audit.
  - `status` varchar(20) not null default 'draft' — 'draft', 'confirmed', 'posted'.
  - `journal_entry_id` uuid nullable FK → `acct.journal_entries.id` (SET NULL/CASCADE).
  - `recorded_by_user_id` uuid not null FK → `auth.users.id` (SET NULL/CASCADE).
  - `confirmed_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `confirmed_at` timestamp nullable.
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `tank_id`; `item_id`; (`company_id`, `reading_date` DESC); (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.tank_readings'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','tank_id','item_id','reading_date','reading_type','dip_measurement_liters','system_calculated_liters','variance_liters','variance_type','variance_reason','status','journal_entry_id','recorded_by_user_id','confirmed_by_user_id','confirmed_at','notes'];`
  - `$casts = ['company_id'=>'string','tank_id'=>'string','item_id'=>'string','reading_date'=>'datetime','dip_measurement_liters'=>'decimal:2','system_calculated_liters'=>'decimal:2','variance_liters'=>'decimal:2','journal_entry_id'=>'string','recorded_by_user_id'=>'string','confirmed_by_user_id'=>'string','confirmed_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Tank (Warehouse); belongsTo Item; belongsTo JournalEntry.
- Validation:
  - `tank_id`: required|uuid|exists:inv.warehouses,id (must be tank type).
  - `reading_type`: required|in:opening,closing,spot_check.
  - `dip_measurement_liters`: required|numeric|min:0.
  - `variance_reason`: nullable|in:evaporation,leak_suspected,meter_fault,dip_error,temperature,theft_suspected,unknown.
  - `status`: required|in:draft,confirmed,posted.
- Business rules:
  - Timing: the station dips each tank once every morning. The dip taken on the morning after day D is stored on day D's daily close with `reading_date = D`, `reading_type = 'closing'`. It is both the closing stock of D and the opening baseline of D+1 (`DailyCloseService::openingBaselineForTank` picks the latest posted reading dated before the close). Expected stock for D = baseline + stock movements dated within (baseline, D] excluding `fuel.daily_close` adjustments − sales on D. A close is refused for a tank with neither a previous dip nor any stock movement (`has_baseline = false`).
  - item_id derived from tank→linked_item_id at creation.
  - variance_liters = dip_measurement_liters - system_calculated_liters.
  - variance_type = 'loss' if negative, 'gain' if positive, 'none' if zero.
  - Posting workflow: draft → confirmed → posted.
  - Draft readings can be edited; confirmed/posted cannot.
  - JE created only when status changes to 'posted'.
  - **Never auto-post** — prevents staff from repeatedly "fixing numbers".

### Variance Reason Codes
```php
enum VarianceReason: string {
    case EVAPORATION = 'evaporation';
    case LEAK_SUSPECTED = 'leak_suspected';
    case METER_FAULT = 'meter_fault';
    case DIP_ERROR = 'dip_error';
    case TEMPERATURE = 'temperature';      // diesel gain from cold
    case THEFT_SUSPECTED = 'theft_suspected';
    case UNKNOWN = 'unknown';
}
```

### fuel.pump_readings
- Purpose: Meter counter readings per pump per shift.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `pump_id` uuid not null FK → `fuel.pumps.id` (RESTRICT/CASCADE).
  - `item_id` uuid not null FK → `inv.items.id` (RESTRICT/CASCADE) — derived from pump→tank→linked_item_id.
  - `reading_date` date not null.
  - `shift` varchar(20) not null — 'day', 'night'.
  - `opening_meter` numeric(12,2) not null.
  - `closing_meter` numeric(12,2) not null.
  - `liters_dispensed` numeric(12,2) not null — closing - opening.
  - `recorded_by_user_id` uuid not null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `pump_id`, `reading_date`, `shift`).
  - Index: `company_id`; `pump_id`; `item_id`; (`company_id`, `reading_date` DESC).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.pump_readings'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','pump_id','item_id','reading_date','shift','opening_meter','closing_meter','liters_dispensed','recorded_by_user_id'];`
  - `$casts = ['company_id'=>'string','pump_id'=>'string','item_id'=>'string','reading_date'=>'date','opening_meter'=>'decimal:2','closing_meter'=>'decimal:2','liters_dispensed'=>'decimal:2','recorded_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Pump; belongsTo Item.
- Validation:
  - `pump_id`: required|uuid|exists:fuel.pumps,id.
  - `reading_date`: required|date.
  - `shift`: required|in:day,night.
  - `opening_meter`: required|numeric|min:0.
  - `closing_meter`: required|numeric|gte:opening_meter.
- Business rules:
  - item_id derived from pump→tank→linked_item_id at creation.
  - liters_dispensed = closing_meter - opening_meter (calculated).
  - opening_meter should match previous shift's closing_meter.
  - Store item_id redundantly for reconciliation queries and audit trail.
  - Used in reconciliation health: meters vs invoices.

### fuel.investors
- Purpose: Investor master record.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `name` varchar(255) not null.
  - `phone` varchar(20) nullable.
  - `cnic` varchar(20) nullable — Pakistani ID.
  - `total_invested` numeric(15,2) not null default 0 — sum of all lots.
  - `total_commission_earned` numeric(15,2) not null default 0.
  - `total_commission_paid` numeric(15,2) not null default 0.
  - `is_active` boolean not null default true.
  - `investor_account_id` uuid nullable FK → `acct.accounts.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; (`company_id`, `is_active`); `cnic`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.investors'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','name','phone','cnic','total_invested','total_commission_earned','total_commission_paid','is_active','investor_account_id','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','total_invested'=>'decimal:2','total_commission_earned'=>'decimal:2','total_commission_paid'=>'decimal:2','is_active'=>'boolean','investor_account_id'=>'string','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo InvestorAccount (Account); hasMany InvestorLot.
- Validation:
  - `name`: required|string|max:255.
  - `phone`: nullable|string|max:20.
  - `cnic`: nullable|string|max:20.
- Business rules:
  - Aggregate fields updated when lots change.
  - investor_account_id links to sub-ledger account for detailed tracking.

### fuel.investor_lots
- Purpose: Lot model for investment tracking. **Prevents rate-change disputes.**
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `investor_id` uuid not null FK → `fuel.investors.id` (CASCADE/CASCADE).
  - `deposit_date` date not null.
  - `investment_amount` numeric(15,2) not null.
  - `entitlement_rate` numeric(10,2) not null — **LOCKED at deposit time (purchase rate)**.
  - `commission_rate` numeric(5,2) not null default 2.00 — **LOCKED at deposit (PKR/liter)**.
  - `units_entitled` numeric(12,2) not null — investment / entitlement_rate (fixed).
  - `units_remaining` numeric(12,2) not null — decrements as sales occur.
  - `commission_earned` numeric(15,2) not null default 0.
  - `status` varchar(20) not null default 'active' — 'active', 'depleted', 'withdrawn'.
  - `journal_entry_id` uuid nullable FK → `acct.journal_entries.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `investor_id`; (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.investor_lots'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','investor_id','deposit_date','investment_amount','entitlement_rate','commission_rate','units_entitled','units_remaining','commission_earned','status','journal_entry_id','notes'];`
  - `$casts = ['company_id'=>'string','investor_id'=>'string','deposit_date'=>'date','investment_amount'=>'decimal:2','entitlement_rate'=>'decimal:2','commission_rate'=>'decimal:2','units_entitled'=>'decimal:2','units_remaining'=>'decimal:2','commission_earned'=>'decimal:2','journal_entry_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Investor; belongsTo JournalEntry.
- Validation:
  - `investor_id`: required|uuid|exists:fuel.investors,id.
  - `deposit_date`: required|date.
  - `investment_amount`: required|numeric|gt:0.
  - `entitlement_rate`: required|numeric|gt:0.
  - `commission_rate`: required|numeric|min:0.
  - `status`: required|in:active,depleted,withdrawn.
- Business rules:
  - entitlement_rate = current purchase_rate at time of deposit.
  - units_entitled = investment_amount / entitlement_rate (LOCKED).
  - units_remaining decremented as sales occur against this lot.
  - commission_earned = commission_rate * (units_entitled - units_remaining).
  - Status changes to 'depleted' when units_remaining = 0.
  - **Rates do not change** — prevents disputes when government changes prices.

### fuel.amanat_transactions
- Purpose: Trust deposit movements (deposit/withdraw/fuel_purchase).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null FK → `acct.customers.id` (RESTRICT/CASCADE).
  - `transaction_type` varchar(20) not null — 'deposit', 'withdrawal', 'fuel_purchase'.
    - `amount` numeric(15,2) not null.
    - `payment_account_id` uuid nullable FK → `acct.accounts.id` (cash or bank account used for a deposit/withdrawal; null on legacy rows and fuel purchases).
  - `fuel_item_id` uuid nullable FK → `inv.items.id` (SET NULL/CASCADE) — if fuel_purchase.
  - `fuel_quantity` numeric(10,2) nullable — liters if fuel_purchase.
  - `reference` varchar(100) nullable.
  - `journal_entry_id` uuid nullable FK → `acct.journal_entries.id` (SET NULL/CASCADE) (added by migration 2026_09_15_000001).
  - `recorded_by_user_id` uuid not null FK → `auth.users.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `customer_id`; (`company_id`, `transaction_type`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.amanat_transactions'; $keyType = 'string'; public $incrementing = false;`
    - `$fillable = ['company_id','customer_id','transaction_type','amount','payment_account_id','fuel_item_id','fuel_quantity','reference','journal_entry_id','recorded_by_user_id','notes'];`
    - `$casts = ['company_id'=>'string','customer_id'=>'string','amount'=>'decimal:2','payment_account_id'=>'string','fuel_item_id'=>'string','fuel_quantity'=>'decimal:2','journal_entry_id'=>'string','recorded_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo FuelItem (Item); belongsTo JournalEntry.
- Validation:
  - `customer_id`: required|uuid|exists:acct.customers,id (must have is_amanat_holder=true).
  - `transaction_type`: required|in:deposit,withdrawal,fuel_purchase.
  - `amount`: required|numeric|gt:0.
  - `fuel_item_id`: required_if:transaction_type,fuel_purchase.
  - `fuel_quantity`: required_if:transaction_type,fuel_purchase|numeric|gt:0.
- Business rules:
    - Deposit: adds to customer.amanat_balance and debits the selected cash/bank account (cash is the legacy default).
    - Withdrawal: subtracts from customer.amanat_balance and credits the selected cash/bank account (cash is the legacy default).
  - Fuel purchase: subtracts from balance, creates sale.
  - Balance cannot go negative.
  - **Tech debt:** May be refactored to generic `acct.deposits` later.

### fuel.attendant_handovers
- Purpose: Cash transit from attendants to company. **Control surface for fraud/mistakes.**
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `attendant_id` uuid not null FK → `auth.users.id` (RESTRICT/CASCADE) — WHO collected.
  - `handover_date` timestamp not null.
  - `pump_id` uuid not null FK → `fuel.pumps.id` (RESTRICT/CASCADE).
  - `shift` varchar(20) not null — 'day', 'night'.
  - `cash_amount` numeric(15,2) not null default 0.
  - `easypaisa_amount` numeric(15,2) not null default 0.
  - `jazzcash_amount` numeric(15,2) not null default 0.
  - `bank_transfer_amount` numeric(15,2) not null default 0.
  - `card_swipe_amount` numeric(15,2) not null default 0.
  - `parco_card_amount` numeric(15,2) not null default 0 — goes to clearing, not bank.
  - `total_amount` numeric(15,2) not null — computed sum.
  - `destination_bank_id` uuid not null FK → `acct.accounts.id` (RESTRICT/CASCADE).
  - `status` varchar(20) not null default 'pending' — 'pending', 'received', 'reconciled'.
  - `received_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `received_at` timestamp nullable.
  - `journal_entry_id` uuid nullable FK → `acct.journal_entries.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `attendant_id`; `pump_id`; (`company_id`, `handover_date` DESC); (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'fuel.attendant_handovers'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','attendant_id','handover_date','pump_id','shift','cash_amount','easypaisa_amount','jazzcash_amount','bank_transfer_amount','card_swipe_amount','parco_card_amount','total_amount','destination_bank_id','status','received_by_user_id','received_at','journal_entry_id','notes'];`
  - `$casts = ['company_id'=>'string','attendant_id'=>'string','handover_date'=>'datetime','pump_id'=>'string','cash_amount'=>'decimal:2','easypaisa_amount'=>'decimal:2','jazzcash_amount'=>'decimal:2','bank_transfer_amount'=>'decimal:2','card_swipe_amount'=>'decimal:2','parco_card_amount'=>'decimal:2','total_amount'=>'decimal:2','destination_bank_id'=>'string','received_by_user_id'=>'string','received_at'=>'datetime','journal_entry_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Attendant (User); belongsTo Pump; belongsTo DestinationBank (Account); belongsTo JournalEntry.
- Validation:
  - `attendant_id`: required|uuid|exists:auth.users,id.
  - `handover_date`: required|date.
  - `pump_id`: required|uuid|exists:fuel.pumps,id.
  - `shift`: required|in:day,night.
  - `cash_amount`: numeric|min:0.
  - `easypaisa_amount`: numeric|min:0.
  - `jazzcash_amount`: numeric|min:0.
  - `bank_transfer_amount`: numeric|min:0.
  - `card_swipe_amount`: numeric|min:0.
  - `parco_card_amount`: numeric|min:0.
  - `destination_bank_id`: required|uuid|exists:acct.accounts,id.
  - `status`: required|in:pending,received,reconciled.
- Business rules:
  - total_amount = sum of all channel amounts.
  - parco_card_amount goes to Parco Card Clearing (1030), not bank.
  - Status workflow: pending → received → reconciled.
  - JE created when received: Dr Bank / Cr Attendant Cash in Transit.
  - Channel breakdown enables fraud detection and verification.

## Inventory Costing: Weighted Average Cost (WAC)

**Fuel accounting without a costing rule is astrology.**

### On Purchase
```php
// After receiving purchase of X liters at Y cost/liter
$item = Item::find($itemId);
$oldQty = $item->current_stock;
$oldAvgCost = $item->avg_cost ?? 0;
$newQty = $purchaseQty;
$newCost = $purchaseCostPerLiter;

$item->avg_cost = (($oldQty * $oldAvgCost) + ($newQty * $newCost)) / ($oldQty + $newQty);
$item->current_stock += $newQty;
$item->save();
```

### On Sale
```php
// COGS = liters × avg_cost at time of sale
$cogs = $saleQty * $item->avg_cost;
```

## Key Accounting Entries

### Parco Card Sale (TWO events)
**Event 1: Sale time (customer uses Parco card)**
```
Dr. Parco Card Clearing (1030)    XXX
    Cr. Fuel Sales (4100)                   XXX
Dr. COGS - Fuel (5100)            XXX
    Cr. Fuel Inventory (1200)               XXX
```

**Event 2: Parco settlement (statement-driven)**
```
Dr. Accounts Payable - Parco (2100)   XXX
    Cr. Parco Card Clearing (1030)          XXX
```

### Bulk Sale (2 PKR discount)
```
Dr. Cash/Bank                     (net amount received)
Dr. Sales Discounts (4210)        (2 * liters) ← contra-revenue
    Cr. Fuel Sales (4100)                   (gross)
Dr. COGS - Fuel (5100)            XXX
    Cr. Fuel Inventory (1200)               XXX
```

### Investor Deposit
```
Dr. Cash/Bank                     XXX
    Cr. Investor Deposits (2210)            XXX
```

### Investor Fulfillment + Commission
```
Dr. Investor Deposits (2210)      (sale amount)
    Cr. Fuel Sales (4100)                   XXX
Dr. Investor Commission (6200)    (commission_rate * liters)
    Cr. Commission Payable (2220)           XXX
+ COGS entry
```

### Amanat Deposit
```
Dr. Cash/Bank                     XXX
    Cr. Amanat Deposits (2200)              XXX
```

### Amanat Fuel Purchase
```
Dr. Amanat Deposits (2200)        XXX
    Cr. Fuel Sales (4100)                   XXX
+ COGS entry
```

### Fuel Shrinkage (from tank_reading posting)
```
Dr. Fuel Shrinkage Loss (6300)    XXX
    Cr. Fuel Inventory (1200)               XXX
```

### Fuel Gain (from tank_reading posting, diesel)
```
Dr. Fuel Inventory (1200)         XXX
    Cr. Fuel Variance Gain (4900)           XXX
```

### Attendant Cash → Bank
```
Dr. Operating Bank (1000)         XXX
    Cr. Attendant Cash in Transit (1060)    XXX
```

## API Contracts (for Frontend Development)

Frontend devs can build pages using these contracts while backend implements controllers/services.

### Pumps CRUD
```
GET    /{company}/fuel/pumps              → { pumps: Pump[], tanks: Tank[] }
POST   /{company}/fuel/pumps              → { pump: Pump }
GET    /{company}/fuel/pumps/{id}         → { pump: Pump, tank: Tank, readings: PumpReading[] }
PUT    /{company}/fuel/pumps/{id}         → { pump: Pump }
DELETE /{company}/fuel/pumps/{id}         → { success: boolean }

Pump = { id, name, tank_id, current_meter_reading, is_active }
Tank = { id, name, code, capacity, low_level_alert, linked_item: Item }
```

### Rate Changes
```
GET    /{company}/fuel/rates              → { rates: RateChange[], items: FuelItem[] }
POST   /{company}/fuel/rates              → { rate: RateChange }
GET    /{company}/fuel/rates/current      → { rates: { [item_id]: { purchase_rate, sale_rate, margin } } }

RateChange = { id, item_id, effective_date, purchase_rate, sale_rate, margin_impact }
FuelItem = { id, name, fuel_category, avg_cost }
```

### Tank Readings (with workflow)
```
GET    /{company}/fuel/tank-readings                → { readings: TankReading[], tanks: Tank[] }
POST   /{company}/fuel/tank-readings                → { reading: TankReading }
GET    /{company}/fuel/tank-readings/{id}           → { reading: TankReading }
PUT    /{company}/fuel/tank-readings/{id}           → { reading: TankReading }  // only if draft
POST   /{company}/fuel/tank-readings/{id}/confirm   → { reading: TankReading }  // draft → confirmed
POST   /{company}/fuel/tank-readings/{id}/post      → { reading: TankReading, journal_entry: JE }  // confirmed → posted

TankReading = {
  id, tank_id, item_id, reading_date, reading_type,
  dip_measurement_liters, system_calculated_liters,
  variance_liters, variance_type, variance_reason,
  status, // 'draft' | 'confirmed' | 'posted'
  recorded_by, confirmed_by, confirmed_at
}
```

### Pump Readings
```
GET    /{company}/fuel/pump-readings      → { readings: PumpReading[], pumps: Pump[] }
POST   /{company}/fuel/pump-readings      → { reading: PumpReading }

PumpReading = { id, pump_id, item_id, reading_date, shift, opening_meter, closing_meter, liters_dispensed }
```

### Investors
```
GET    /{company}/fuel/investors                    → { investors: Investor[] }
POST   /{company}/fuel/investors                    → { investor: Investor }
GET    /{company}/fuel/investors/{id}               → { investor: Investor, lots: InvestorLot[] }
PUT    /{company}/fuel/investors/{id}               → { investor: Investor }
POST   /{company}/fuel/investors/{id}/lots          → { lot: InvestorLot }  // new investment
POST   /{company}/fuel/investors/{id}/pay-commission → { payment: CommissionPayment }

Investor = { id, name, phone, cnic, total_invested, total_commission_earned, total_commission_paid, outstanding_commission }
InvestorLot = { id, deposit_date, investment_amount, entitlement_rate, commission_rate, units_entitled, units_remaining, commission_earned, status }
```

### Amanat (Trust Deposits)
```
GET    /{company}/fuel/amanat                       → { customers: CustomerWithAmanat[] }
GET    /{company}/fuel/amanat/{customer_id}         → { customer: Customer, profile: CustomerProfile, transactions: AmanatTransaction[] }
POST   /{company}/fuel/amanat/{customer_id}/deposit → { transaction: AmanatTransaction, new_balance: number }
POST   /{company}/fuel/amanat/{customer_id}/withdraw → { transaction: AmanatTransaction, new_balance: number }

CustomerProfile = { id, customer_id, is_credit_customer, is_amanat_holder, is_investor, relationship, cnic, amanat_balance }
AmanatTransaction = { id, transaction_type, amount, fuel_item_id, fuel_quantity, reference, created_at }
```

### Attendant Handovers
```
GET    /{company}/fuel/handovers                    → { handovers: Handover[], attendants: User[], pumps: Pump[] }
POST   /{company}/fuel/handovers                    → { handover: Handover }
GET    /{company}/fuel/handovers/{id}               → { handover: Handover }
POST   /{company}/fuel/handovers/{id}/receive       → { handover: Handover }  // pending → received

Handover = {
  id, attendant_id, handover_date, pump_id, shift,
  cash_amount, easypaisa_amount, jazzcash_amount,
  bank_transfer_amount, card_swipe_amount, parco_card_amount,
  total_amount, destination_bank_id, status,
  received_by, received_at
}
```

### Fuel Sales (creates invoice + sale_metadata)
```
POST   /{company}/fuel/sales                        → { invoice: Invoice, metadata: SaleMetadata }

Request body:
{
  sale_type: 'retail' | 'bulk' | 'credit' | 'amanat' | 'investor' | 'parco_card',
  pump_id: uuid,
  customer_id?: uuid,  // required for credit/amanat/investor
  investor_id?: uuid,  // required for investor sales
  items: [{ item_id, quantity, unit_price, discount_amount }],
  payment_method?: 'cash' | 'card' | 'parco_card' | 'easypaisa' | 'jazzcash' | 'bank_transfer',
  attendant_id?: uuid
}

SaleMetadata = { id, invoice_id, sale_type, pump_id, attendant_transit, discount_reason }
```

### Dashboard Data
```
GET    /{company}/fuel/dashboard          → {
  today_sales: { total, by_fuel_type: {...}, by_sale_type: {...} },
  tank_levels: [{ tank_id, item_name, current_liters, capacity, percentage, low_alert }],
  pending_handovers: number,
  unreconciled_parco: number,
  recent_variance: [{ date, tank, variance_liters, variance_type, status }],
  reconciliation_health: {
    meters_vs_invoices: { match: boolean, discrepancy_liters: number },
    cash_vs_handovers: { match: boolean, discrepancy_amount: number }
  }
}
```

### Parco Settlement (statement-driven)
```
GET    /{company}/fuel/parco/pending      → { pending_amount: number, sales: ParcoSale[] }
POST   /{company}/fuel/parco/settle       → { settlement: ParcoSettlement }

Request body:
{
  settlement_amount: number,  // from Parco statement
  settlement_date: date,
  reference: string  // Parco statement reference
}
// System matches against clearing account, posts JE
```

---

## Vue Pages Structure

```
modules/FuelStation/Resources/js/pages/
├── Dashboard.vue           # Fuel station overview
├── Pumps/
│   ├── Index.vue          # List pumps
│   └── Form.vue           # Create/edit pump
├── Rates/
│   ├── Index.vue          # Rate history
│   └── Form.vue           # Record new rate change
├── TankReadings/
│   ├── Index.vue          # List with status filters
│   └── Form.vue           # Record reading + workflow buttons
├── PumpReadings/
│   ├── Index.vue          # List by date/shift
│   └── Form.vue           # Record shift readings
├── Investors/
│   ├── Index.vue          # List investors
│   ├── Show.vue           # Investor detail + lots
│   └── Form.vue           # Create/edit investor
├── Amanat/
│   ├── Index.vue          # Amanat holders list
│   └── Show.vue           # Customer transactions + deposit/withdraw
├── Handovers/
│   ├── Index.vue          # List with status
│   └── Form.vue           # Record handover
├── Sales/
│   └── Form.vue           # Quick sale entry (POS-style)
└── Parco/
    └── Settlement.vue     # Pending + settle form
```

---

## Out of Scope (v1)
- Multi-supplier support (Parco only for now).
- Credit card settlement reconciliation (beyond simple card_swipe tracking).
- Automated bank statement import.
- Mobile app for attendants.
- Fuel delivery tracking.
- Loyalty programs.

## Extending
- Add new variance_reason values here first.
- Multi-supplier would add `fuel.suppliers` and FK on purchases.
- Loyalty would add `fuel.loyalty_programs` and `fuel.loyalty_transactions`.


## Daily Close reconciliation (2026-09-15)

- `fuel.daily_close_drafts`: UUID id; company_id UUID FK auth.companies;
  business_date date; payload jsonb; created_by_user_id and updated_by_user_id UUID
  FKs auth.users; timestamps. Unique (company_id, business_date), company RLS.
  Drafts have no journals, stock movements or finalized physical readings.
- Posting continues to use acct.transactions, transaction_type fuel_daily_close.
  transaction_date is the business date. created_at and posted_at are audit times.
- metadata.posting_snapshot stores version, business_date, posted_at, posted_by,
  totals and canonical journal/stock source vectors. It is immutable after posting.
  Existing metadata remains the original declared physical cash, dip and meter data.
- Reconciled values are read-time differences against source vectors. They never
  update a posted close or carry revised physical cash into another posted close.
- No new ledger or duplicated business-date column is added to Accounting.


### fuel.daily_close_activity
- Append-only audit evidence, not a ledger or a source for accounting totals.
- id UUID PK; company_id UUID FK auth.companies; close_transaction_id UUID FK
  acct.transactions; source_table varchar(100); source_id UUID; operation varchar(10);
  occurred_at timestamptz; actor_id nullable UUID; before_data/after_data nullable jsonb.
- Company RLS and index (company_id, close_transaction_id, occurred_at, id).
- Database source triggers retain insert/update/delete facts after a close posts,
  including edits subsequently reverted and late records subsequently deleted.
- Snapshot-era close journal lines cannot be edited or deleted. Canonical adjustments
  remain separate transactions with their own business dates and audit events.

Posted snapshot physical tank/nozzle observations and close-owned stock movements are immutable at database level. Corrections use separately dated canonical adjustments. Salary advances created by Daily Close reference its journal entry. Reconciliation applies stock movement changes to both the old and new tank/item when an adjustment is reassigned.

Post-close audit also covers invoice/bill lines, customer/supplier payments, Amanat, partner movements and salary advances. Effective dates come from document dates or linked journals, never entry timestamps. These source-document edits are audit evidence; reconciliation amounts remain derived from canonical posted journals and stock movements.

### fuel.daily_close_unlocks (2026-09-19)
- Append-only record of every reopening of a locked Daily Close: who, when, why,
  and the lock that was replaced. Not a ledger and not a source for any total.
- id UUID PK; company_id UUID FK auth.companies; close_transaction_id UUID FK
  acct.transactions; unlocked_at timestamp; unlocked_by_user_id nullable UUID FK
  auth.users; reason text (required, min 10 chars at the request layer);
  previously_locked_at timestamp nullable; previously_locked_by_user_id nullable
  UUID FK auth.users; previous_lock_reason varchar(50) nullable.
- Company RLS, index (company_id, close_transaction_id, unlocked_at), and an
  append-only trigger (fuel.prevent_unlock_trail_mutation) refusing UPDATE/DELETE.
- Why a table and not columns on acct.transactions: Transaction::unlock() clears
  locked_at, locked_by_user_id and lock_reason, so a reopened day was previously
  indistinguishable from a day never locked. fuel.capture_post_close_activity
  returns early for fuel_daily_close rows by design (the close row churns during
  posting), so the post-close audit does not cover it either. A day may also be
  locked and reopened repeatedly; the audit question is the sequence, not the
  most recent event.
- Columns are plain timestamp, not timestamptz: the application runs UTC while the
  Postgres session is Asia/Karachi, and copying acct.transactions.locked_at
  (timestamp) into a timestamptz column skews the stored instant by five hours.

### fuel.station_settings.sales_discount_account_id (2026-09-19)
- Nullable UUID FK acct.accounts. Where a discount given on a fuel sale is posted.
- Resolved and created on demand by StationAccountMapper (code 4210
  "Sales Discounts", type revenue, normal_balance debit, is_contra true).
- The Daily Close posts revenue from the meters at the posted pump rate, so a sale
  below that rate must debit contra revenue and credit the drawer. Without it the
  discount surfaced as an unexplained cash shortage on the close, which is the
  signal a manager uses to detect theft. FuelSaleService posts it as its own dated
  transaction (transaction_type fuel_sale_discount, reference_type acct.invoices),
  so DailyCloseReconciliationService::sources() picks it up whether or not the
  close for that date has already posted.

### fuel.daily_close_reading_corrections (2026-09-16)
- Controlled correction of a physical tank or nozzle reading belonging to an
  already-posted (posting_snapshot) Daily Close. The original
  fuel.tank_readings / fuel.nozzle_readings row is never touched -- physical
  observations on a posted close remain immutable at database level (see
  fuel.capture_post_close_activity above). A correction only adjusts the
  CURRENT/RECONCILED figures DailyCloseReconciliationService::view() returns;
  the POSTED SNAPSHOT section is byte-identical before and after.
- id UUID PK; company_id UUID FK auth.companies; close_transaction_id UUID FK
  acct.transactions; reading_type varchar(10) CHECK IN ('tank','nozzle');
  reading_id UUID (the original tank_readings/nozzle_readings row, not FK-enforced
  across schemas); original_value/corrected_value numeric(18,4); reason text NOT
  NULL; created_by_user_id UUID FK auth.users; created_at timestamptz.
- Company RLS (ENABLE + FORCE), index (company_id, close_transaction_id).
- Append-only: reuses fuel.prevent_audit_mutation() to reject UPDATE/DELETE --
  a correction row is itself audit evidence of the correction, never edited.
- CorrectCloseReadingAction (fuel.daily_close.correct_reading) validates: the
  close belongs to the current company and has a posting_snapshot (unposted or
  legacy closes are rejected); the reading belongs to that company and business
  date (nozzle readings are additionally matched by daily_close_transaction_id);
  corrected_value >= 0; reason required. Permission
  Permissions::DAILY_CLOSE_CORRECT ('daily_close.correct_reading').
- Corrections are serialized per company/business date, with a monotonically increasing
  revision per reading. original_value is the previous effective value, not always the
  posting-time value. Unique(company_id, close_transaction_id, reading_id, revision).
- Additional columns: revision integer; effects jsonb (frozen tank/item, quantity,
  revenue, cost, and expected-stock deltas); transaction_id nullable UUID FK acct.transactions.
- New snapshots store nozzle tank assignments, per-nozzle prices/rate boundaries,
  unit costs, accounting accounts, and per-tank valuation basis. Missing historical
  basis is never reconstructed from current settings: controlled correction rejects it.
- Corrections post only deltas through canonical GL and stock movements atomically.
  Meter revenue changes offset cash over/short, preserving counted cash. Meter COGS
  changes also adjust declared tank variance; unchanged physical dip means no extra
  stock quantity movement. Dip corrections create canonical stock adjustment deltas,
  excluded from expected receipts because they correct the physical declaration itself.
- Original readings, posted snapshots and downstream posted closes never change.
- Operational rule: all fuel sales flow through nozzles and are included in point
  register totals, copied to the office register and Haasib on the following day.
  Credit allocations entered below are already included in those meter sales.

### Daily-close credit allocations and register totals (2026-09-17)
- `credit_sales[]` input: `customer_id` (active tenant customer, distinct), `amount`
  (positive base-currency amount, at most two decimals), optional `reference` (100 characters).
  These are unpaid portions of the entered nozzle sales, not additional sales.
  Credit allocations cannot exceed nozzle revenue; cards plus credit cannot exceed total sales.
- Parking saves these inputs only. Posting atomically creates one native `acct.invoices`
  header/line per customer, links `transaction_id` to the close journal, and debits
  that customer's configured AR account (or company/default AR). The close credits
  full sales revenue and posts COGS/stock once; cash is reduced by card and credit totals.
  No separate invoice journal or additional inventory movement is created.
- Invoice creation is authorized as part of `DAILY_CLOSE_CREATE`. These invoices appear
  on normal Invoice/Customer pages and are settled through normal Payments. Their
  original principal, customer, date and lines are immutable once the close is frozen;
  settlement updates remain permitted. Do not enter a second invoice for the same meter sale.
- Close metadata: `credit_sales_total`, `credit_sale_details[]` with `customer_id`,
  `customer_name`, `amount`, `reference`, `ar_account_id`, `invoice_id`, `invoice_number`.
  `form_input.credit_sales` preserves input; `posting_snapshot.credit_sales` freezes details.
- Snapshot version 2 reports current-day gross Money In and Money Out, with opening
  cash separate. Card/bank receipts and credit allocations are included in Money Out
  rather than netted from sales in Money In. Expected cash = opening + in - out.
  Version 1 is normalized for display using its frozen channel amounts without rewriting
  stored snapshots; both posted and current views use the same normalized convention.

### Standalone cash-sale customer (2026-09-19)
- Retail and bulk cash sales may omit `customer_id`. The service creates or reuses
  the current company's active `acct.customers` row with reserved customer number
  `CASH-FUEL`, name `Walk-in fuel customer`, type `walk_in`, company base currency,
  and zero-day payment terms. First-use creation is serialized within the sale
  transaction. No global customer is shared across tenants, and invoice customer
  IDs remain mandatory. Explicit customer selections are preserved and must be
  active members of the current company's customer master.
- Other sale types require an explicit customer; missing customers produce a
  business error, not a database constraint failure. Cash invoices retain their
  existing paid status and zero balance; this does not introduce an extra payment
  or revenue journal alongside the daily close.

### Standalone fuel-sale invoices as a close channel (2026-09-17)
- Business rule: every litre sold goes through a nozzle the close reads. A standalone
  credit fuel-sale invoice (`FuelSaleService::createSale`, `sale_type=credit`) is never
  additional revenue — it is a split of the meter-derived sales into a receivable, same
  as a credit allocation typed directly into the close.
- Before a close for its date posts: `DailyCloseCreditSaleService::pendingFuelInvoiceDetails()`
  finds unlinked (`transaction_id IS NULL`) credit fuel-sale invoices for the company+date
  and folds them into `credit_sale_details`/`posting_snapshot.credit_sales` alongside
  manually-typed rows, each detail carrying `source` = `fuel_sale_invoice` (manual rows
  carry `source` = `manual`). They reduce expected cash exactly like a manual credit row
  and are linked to the close's transaction on post via the same `attach()` used for
  manual rows. The `DailyCloseController` Create page pre-loads them as `pendingFuelInvoices`,
  pre-checked into the credit-sales section; a manual row whose `reference` matches a
  pending invoice's number is either the page's own echo (same amount — dropped) or a
  genuine duplicate attempt (different amount — rejected).
- After a close for its date has already posted (has `metadata.posting_snapshot`):
  `FuelSaleService::createSale` immediately posts a `fuel_sale_reclass` journal
  (Dr buyer's AR / Cr the close's frozen `cash_account_id`, `reference_type=acct.invoices`,
  `reference_id=<invoice id>`, dated the sale date) and links the invoice's `transaction_id`
  to it. `DailyCloseReconciliationService::sources()`/`view()` pick this transaction up like
  any other canonical late activity: cash effect = -amount, sales effect = 0 (no revenue
  account is touched).
- No new columns were added: this reuses `acct.invoices.transaction_id` (null = pending,
  set = attached to a close or reclassified) plus the existing `posting_snapshot` metadata.

### Inline supplier bills / fuel purchases in the Daily Close (2026-09-17)
- `purchases[]` input (park: stored as-is in the draft; post: each row must be complete):
  `supplier_id`, `item_id`, `quantity`, `unit_cost`, `tank_id` (required when the item has
  a `fuel_category`), `supplier_invoice_number`, `notes`, `paid_now`. Entering a purchase
  requires `bill.create` (`RequiresBillCreatePermission`); the Create page hides the
  section and the FormRequest rejects any row without it.
- At post, before `DailyCloseReconciliationService::sources()` reads the date (inside the
  same advisory-locked transaction), `DailyCloseEntryService::purchase()` dispatches the
  same `bill.create` (status `received`, so `Bill\CreateAction`'s own immediate-delivery
  receipt posts the stock movement into the chosen tank) and, when `paid_now` is set,
  `bill_payment.create` commands the Bills module itself uses — no bill/payment logic is
  duplicated. The resulting bill/payment transactions are then picked up automatically by
  `sources()` like any other canonical activity for the date; `DailyCloseService` tags the
  matching `posting_snapshot.sources['journal:<id>']` entries with `source` = `close_purchase`
  for display. A bill paid inside the same close already carries a `transaction_id`, so it
  is excluded from `pendingBillPayments` by the same `whereNull('transaction_id')` filter
  that list already uses.

### Legacy Daily Close amendment removed (2026-09-17)
- Owner decision: nothing was ever in production with a close lacking a `posting_snapshot`,
  and a posted Daily Close is never reversed and re-posted. Every close is a snapshot close.
- Removed entirely: `DailyCloseAmendmentService` (the `amendDailyClose()`/reversal+correction
  path and `getAmendmentChain()`), the `/{transaction}/amend` and `/{transaction}/amendment-chain`
  routes and controller methods, `StoreDailyCloseAmendmentRequest`, the `AmendmentChain.vue`
  component, and the "Amend"/"Amendment History" UI in Show.vue and Index.vue.
  `Transaction::isAmendable()` now always returns `false`; `PostingService::reverseTransaction`
  refuses `fuel_daily_close` unconditionally (previously only when a `posting_snapshot` was
  present). Lock/unlock/lock-month is a separate, unrelated feature and was kept, now in
  `DailyCloseLockService` (renamed from `DailyCloseAmendmentService`, which no longer amends
  anything).
- `fuel.capture_post_close_activity()`'s whitelist for `fuel_daily_close_reversal` transactions
  (nothing creates that type anymore) was dropped via a new migration
  (`2026_09_17_230000_drop_legacy_close_reversal_support`) that re-creates the function with
  `CREATE OR REPLACE`, rather than editing the original `2026_09_15_220000_audit_post_close_activity`
  migration in place (that one has already run in production).
- Post-close corrections remain exactly as documented above (declared expenses, reading
  corrections, late canonical activity captured in `fuel.daily_close_activity`) — nothing
  about that mechanism changed. What was removed is the alternate, unused path of reversing
  the whole close and re-posting a correction transaction linked via `corrects_transaction_id`.
