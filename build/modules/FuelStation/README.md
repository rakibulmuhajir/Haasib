# Fuel Station Module

**Module Type:** Industry Vertical
**Target Market:** Pakistani Fuel Stations (Petrol Pumps)
**Status:** MVP Development

---

## Module Docs

- `build/modules/FuelStation/permissions.md` - Module permissions and default role access.
- `build/modules/FuelStation/coa.md` - Default chart of accounts and posting templates.

---

## Business Context

### The Problem

Pakistani fuel station owners face unique operational challenges that generic accounting software doesn't address:

1. **Government Price Controls** - Fuel prices are set by OGRA (Oil and Gas Regulatory Authority) and change frequently. Stations must track purchase rates vs sale rates, and rate changes affect existing inventory valuation.

2. **Multiple Sales Channels** - Cash, credit accounts, mobile wallets (Easypaisa/JazzCash), card swipes, vendor fleet cards - each with different settlement timelines and fees.

3. **Investor-Funded Operations** - Many stations operate on investor deposits where investors provide capital and earn commission per liter sold. Rate changes create disputes if not tracked properly.

4. **Amanat (Trust Deposits)** - Customers pre-pay for fuel, creating a liability until they consume it. Common for fleet operators.

5. **Attendant Accountability** - Multiple pump attendants handle cash across shifts. Tracking who collected what, and when it was handed over, is critical for fraud prevention.

6. **Vendor Card Settlement** - Fleet cards are processed through a clearing system with delayed settlement and fees. Receivables must be tracked separately.

7. **Tank/Pump Variance** - Physical dip measurements vs system calculations reveal losses (evaporation, theft, meter drift) or gains (temperature expansion).

### The Solution

A specialized module that extends Haasib's core accounting with fuel station-specific:
- **Data models** (tanks, pumps, rates, readings, investors, handovers)
- **Workflows** (variance posting, commission calculation, settlement)
- **Dashboards** (tank levels, pending handovers, outstanding receivables)

---

## Business Requirements

### BR-1: Rate Management
- Track purchase and sale rates per fuel type (Petrol, Hi-Octane, Diesel)
- Record effective date for rate changes
- Calculate margin impact when rates change with existing stock
- Historical rate lookup for any date

### BR-2: Tank Monitoring
- Physical dip measurements compared to system-calculated levels
- Variance detection with categorization (evaporation, theft, calibration, temperature)
- Posting workflow: Draft → Confirmed → Posted (prevents gaming)
- Journal entries for significant variances

### BR-3: Pump Meter Readings
- Opening/closing meter per pump per shift
- Auto-calculate liters dispensed
- Link to tank for fuel type identification
- Support for day/night shifts

### BR-4: Investor Management
- **Lot Model**: Each investment creates a "lot" with locked entitlement rate
- `units_entitled = investment_amount / purchase_rate_at_time`
- Rate changes don't affect existing lots (prevents disputes)
- Commission = units consumed × (sale_rate - purchase_rate) at lot creation time
- FIFO consumption across lots
- Commission payment tracking with journal entries

### BR-5: Amanat (Trust Deposits)
- Customer deposits money, receives fuel later
- Balance tracking per customer
- Deposit/withdraw with journal entries
- Apply balance to fuel purchases
- Non-exclusive: same customer can be credit customer AND amanat holder

### BR-6: Attendant Handovers
- Track cash collection by attendant, pump, shift
- Breakdown by payment channel (cash, easypaisa, jazzcash, bank transfer, card, vendor card)
- Status workflow: Pending → Received → Reconciled
- Vendor card amounts tracked separately (goes to clearing, not cash)

### BR-7: Fuel Sales
Six sale types with different accounting treatment:
| Type | Payment | Receivable | Commission |
|------|---------|------------|------------|
| Retail | Immediate cash | None | None |
| Bulk | Cash with discount | None | None |
| Credit | Deferred | Customer AR | None |
| Amanat | From deposit | None | None |
| Investor | From investor | None | Yes |
| Vendor Card | Via clearing | Vendor AR | None |

### BR-8: Vendor Card Settlement
- List pending vendor card receivables
- Record settlement with fee deduction
- Mark invoices as paid
- Journal entry: Dr Bank, Dr Fees, Cr Vendor Card Receivable

### BR-9: Dashboard
Real-time visibility into:
- Tank levels with fill percentage
- Current rates and margins
- Today's sales by type
- Month-to-date totals
- Pending handovers awaiting receipt
- Outstanding investor commissions
- Total amanat liability
- Vendor card receivable balance

### BR-10: Daily Close
- Capture a full day register (sales, pump readings, tank readings, money in/out)
- Reconcile expected cash vs closing cash with variance posting
- One posted close per day with amendment/lock workflow

---

## Architectural Decisions

### Rejected → Accepted

| Decision | Rejected Approach | Accepted Approach | Rationale |
|----------|-------------------|-------------------|-----------|
| Core table extension | Add `is_credit_customer`, `amanat_balance` to `acct.customers` | Separate `fuel.customer_profiles` 1:1 linked | Keep core accounting module clean and reusable |
| Invoice metadata | Add `sale_type`, `pump_id` to `acct.invoices` | Separate `fuel.sale_metadata` 1:1 linked | Same - don't pollute core with industry-specific fields |
| Rate storage | Store rates on `inv.items` | Separate `fuel.rate_changes` with history | Need rate history, margin calculation, effective dates |
| Customer types | Single `customer_type` enum | Non-exclusive boolean flags | Customer can be credit AND amanat holder AND investor |
| Investor formula | `units = investment / current_rate` (recalculated) | Lot model with locked `entitlement_rate` | Prevents rate-change disputes |
| Variance posting | Auto-post on reading creation | Draft → Confirmed → Posted workflow | Prevents gaming, requires manager approval |

### Module Isolation

The FuelStation module:
- Lives in `modules/FuelStation/`
- Has its own service provider loading migrations and routes
- Creates tables in `fuel.*` schema
- Links to core `acct.*` and `inv.*` tables via foreign keys
- Never modifies core table structures

---

## Onboarding Flow

When a company enables the Fuel Station module, the following setup is required:

### Step 1: Verify Prerequisites
- Company must have base currency set (typically PKR)
- Chart of accounts must exist with:
  - Cash/Bank accounts (assets)
  - Inventory accounts (assets)
  - Fuel inventory asset account
  - Revenue accounts

### Step 2: Create Fuel Items
For each fuel type sold:
- Create inventory item with `fuel_category` set (petrol, diesel, high_octane)
- Link to fuel inventory account

### Step 3: Create Tanks (Warehouses)
For each physical tank:
- Create warehouse with `warehouse_type = 'tank'`
- Set `capacity` (liters)
- Link to fuel item via `linked_item_id`

### Step 4: Create Pumps
For each fuel dispenser:
- Create pump linked to tank
- Set initial meter reading
- Mark as active

### Step 5: Set Initial Rates
For each fuel item:
- Create rate change record with current purchase and sale rates
- Set effective date

### Step 6: Create Required Accounts (Auto)
The module auto-creates these if missing:
- `Investor Deposits` (liability) - for investor capital
- `Customer Amanat Deposits` (liability) - for trust deposits
- `Investor Commission Expense` (expense) - for commission payments
- `Vendor Card Receivable` (asset) - for pending settlements
- `Vendor Card Fees` (expense) - for settlement fees
- `Fuel Variance` (expense) - for tank losses

### Step 7: Initial Tank Reading
For each tank:
- Perform physical dip measurement
- Record as opening reading
- This establishes the baseline for variance tracking

---

## Data Model

### New Tables (fuel.* schema)

```
fuel.rate_changes        - Price history per fuel item
fuel.pumps               - Fuel dispensers linked to tanks
fuel.pump_readings       - Meter readings per shift
fuel.tank_readings       - Dip measurements with variance
fuel.investors           - People who fund operations
fuel.investor_lots       - Individual investments with locked rates
fuel.customer_profiles   - Fuel-specific customer data (1:1 with acct.customers)
fuel.sale_metadata       - Fuel-specific invoice data (1:1 with acct.invoices)
fuel.amanat_transactions - Trust deposit movements
fuel.attendant_handovers - Cash collection records
```

### Extended Tables

```
inv.warehouses           - Add warehouse_type='tank', capacity, linked_item_id
inv.items                - Add fuel_category (petrol, diesel, high_octane)
```

---

## API Endpoints

All routes prefixed with `/{company}/fuel/`

### Dashboard
- `GET /dashboard` - Main dashboard with all metrics

### Pumps
- `GET /pumps` - List pumps with tanks
- `POST /pumps` - Create pump
- `GET /pumps/{pump}` - Pump details with recent readings
- `PUT /pumps/{pump}` - Update pump
- `DELETE /pumps/{pump}` - Delete (fails if has readings)

### Rates
- `GET /rates` - Rate change history
- `POST /rates` - Record new rate
- `GET /rates/current` - Current rates per fuel item (JSON)

### Tank Readings
- `GET /tank-readings` - List with filters
- `POST /tank-readings` - Create draft reading
- `GET /tank-readings/{reading}` - Reading details
- `PUT /tank-readings/{reading}` - Update draft
- `POST /tank-readings/{reading}/confirm` - Manager confirmation
- `POST /tank-readings/{reading}/post` - Post variance to GL

### Pump Readings
- `GET /pump-readings` - List readings
- `POST /pump-readings` - Record meter reading

### Daily Close
- `GET /daily-close` - Create daily close
- `POST /daily-close` - Store daily close
- `GET /daily-close/history` - History index
- `GET /daily-close/{transaction}` - Read-only view
- `GET /daily-close/{transaction}/amend` - Amendment form
- `POST /daily-close/{transaction}/amend` - Store amendment
- `POST /daily-close/{transaction}/lock` - Lock
- `POST /daily-close/{transaction}/unlock` - Unlock
- `POST /daily-close/lock-month` - Lock month

### Investors
- `GET /investors` - List with summary stats
- `POST /investors` - Create investor
- `GET /investors/{investor}` - Details with lots
- `PUT /investors/{investor}` - Update
- `POST /investors/{investor}/lots` - Add investment lot
- `POST /investors/{investor}/pay-commission` - Pay outstanding

### Amanat
- `GET /amanat` - List amanat holders
- `GET /amanat/{customer}` - Transaction history
- `POST /amanat/{customer}/deposit` - Record deposit
- `POST /amanat/{customer}/withdraw` - Process withdrawal

### Handovers
- `GET /handovers` - List with pending count
- `POST /handovers` - Record handover
- `GET /handovers/{handover}` - Details
- `POST /handovers/{handover}/receive` - Mark received

### Sales
- `POST /sales` - Record fuel sale (handles all 6 types)

### Vendor Card Settlement
- `GET /vendor-cards/pending` - Pending vendor card receivables
- `POST /vendor-cards/settle` - Process vendor card settlement

---

## Vue Pages

```
pages/FuelStation/
├── Dashboard/
│   └── Index.vue          - Main dashboard
├── Pumps/
│   ├── Index.vue          - Pump list with create modal
│   └── Show.vue           - Pump details
├── Rates/
│   └── Index.vue          - Rate history with add form
├── TankReadings/
│   ├── Index.vue          - Readings list with create
│   └── Show.vue           - Reading detail with workflow actions
├── PumpReadings/
│   └── Index.vue          - Readings list with create
├── Investors/
│   ├── Index.vue          - Investor list
│   └── Show.vue           - Investor detail with lots
├── Amanat/
│   ├── Index.vue          - Amanat holders list
│   └── Show.vue           - Customer amanat history
├── DailyClose/
│   ├── Create.vue         - Daily close register
│   ├── Index.vue          - Daily close history
│   └── Show.vue           - Read-only daily close view
├── Handovers/
│   ├── Index.vue          - Handover list
│   └── Show.vue           - Handover detail
└── VendorCards/
    └── Settlement.vue     - Vendor card settlement interface
```

---

## Testing Scenarios

### Happy Path
1. Owner sets up tanks, pumps, initial rates
2. Attendant records pump readings each shift
3. Manager records tank dip, confirms, posts variance
4. Retail sales throughout the day
5. Daily close posted with cash reconciliation
6. Attendant hands over cash at shift end
7. Owner marks handover received

### Investor Flow
1. Create investor, add lot (locks rate)
2. Investor customer purchases fuel
3. System consumes from oldest lot (FIFO)
4. Commission accumulates
5. Owner pays commission periodically

### Amanat Flow
1. Customer deposits PKR 10,000
2. Customer purchases fuel over time
3. Balance decreases with each purchase
4. Customer can withdraw remaining balance

### Vendor Card Settlement Flow
1. Vendor card sales accumulate
2. Weekly, vendor sends settlement minus fees
3. Owner records settlement, marks invoices paid

---

## Module Development
- Follow `docs/modules.md` before adding new features.
- Keep all module logic inside this module (migrations, models, controllers, services, routes, views, sidebar).
- Create `permissions.md` and `coa.md` in this module root before implementation.

## Module Type
- Standalone business module (vertical).
- Provides navigation via `Resources/js/nav.ts` and may override the host sidebar.

## Module Navigation
- Register entries in `Resources/js/nav.ts`.
- Example:
```ts
import type { ModuleNavConfig } from '@/navigation/types'

export const fuelStationNav: ModuleNavConfig = {
  id: 'fuel_station',
  label: 'Fuel Station',
  mode: 'extend',
  isEnabled: (context) => Boolean(context.slug && context.isFuelStationCompany),
  getNavGroups: (context) => {
    const { slug } = context
    return [
      {
        label: 'Daily Operations',
        items: [
          { title: 'Daily Close', href: `/${slug}/fuel/daily-close` },
        ],
      },
    ]
  },
}
```
