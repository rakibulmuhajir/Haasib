# Umrah Module Delivery Todo

## Core Direction

- [ ] Create a separate `Umrah` module. Do not reuse petrol pump screens, stock deliveries, tank dips, fuel rates, or daily close flows.
- [ ] When a company is created with industry `umrah` or `travel`, show Umrah-specific navigation and setup only.
- [ ] Keep the first delivery focused on visa operations for Umrah groups.
- [ ] Make `Visa Group` the single source of truth for operational tracking, payments, balances, and earnings.

## First Delivery Scope

- [ ] Agents
  - [ ] Add and manage agents who send passports.
  - [ ] Track agent phone, company/name, city, opening balance if needed, total receivable, paid, and outstanding balance.

- [ ] Vendors
  - [ ] Add visa vendors, usually government or visa service providers.
  - [ ] Track vendor name, contact info, and payable balance if costs are unpaid.

- [ ] Visa Groups
  - [ ] Create group under an agent.
  - [ ] Track group code/name, travel date, status, passenger count, visa sale amount, visa cost, paid amount, balance, and profit.
  - [ ] Store flight and hotel details as simple information fields only.
  - [ ] Add optional transport requirement using client-managed vehicle types.

- [ ] Passengers / Passports
  - [ ] Add passengers inside a group.
  - [ ] Track name, passport number, nationality, status, and notes.
  - [ ] Keep passenger entry lightweight for fast group creation.

- [ ] Transport Settings
  - [ ] Let client create vehicle types such as car, 7 seater, 9 seater, coaster, bus, or custom.
  - [ ] Attach transport requirement to a visa group.

- [ ] Payments
  - [ ] Record payments received from agents against a visa group.
  - [ ] Support partial payments.
  - [ ] Show remaining group balance clearly.
  - [ ] Show agent balance across all groups.

- [ ] Visa Cost
  - [ ] Record visa cost per passenger or group total.
  - [ ] Link cost to vendor when applicable.
  - [ ] Use cost to calculate group profit.

## Accounting Rules

- [ ] Group receivable / sale:
  - Dr Agent Receivable
  - Cr Visa Revenue

- [ ] Agent payment:
  - Dr Cash / Bank
  - Cr Agent Receivable

- [ ] Visa cost:
  - Dr Visa Cost
  - Cr Vendor Payable or Cash / Bank

- [ ] Vendor payment if cost was unpaid:
  - Dr Vendor Payable
  - Cr Cash / Bank

- [ ] Transport income if charged:
  - Dr Agent Receivable
  - Cr Transport Revenue

## Reports

- [ ] Agent Balance Report
  - Agent, total groups, total receivable, paid, outstanding.

- [ ] Group Profit Report
  - Group sale, visa cost, transport income, profit, paid, balance.

- [ ] Monthly Earnings Report
  - Visa revenue, transport revenue, visa cost, gross profit.

- [ ] Upcoming Travel Report
  - Groups by travel date, agent, passengers, flight/hotel info.

- [ ] Payment Collection Report
  - Payments by date, agent, group, account, method.

## Navigation

- [ ] Umrah Dashboard
- [*] Agents
- [ ] Visa Groups
- [ ] Vendors
- [ ] Payments
- [ ] Reports
- [ ] Settings

## Done Criteria

- [ ] A travel/umrah company sees only Umrah-relevant screens.
- [ ] Petrol pump features are hidden from travel/umrah companies.
- [ ] User can create agent, vendor, group, passengers, cost, and payment.
- [ ] Group balance and profit are visible without accounting knowledge.
- [ ] Accounting entries are automatic and balanced.
- [ ] Reports answer: who owes money, which groups are profitable, and monthly earnings.
