# Travel Module Audit and Phase 1 Plan

**Audit date:** 2026-07-16  
**Module:** `build/modules/Umrah`  
**Status:** Phase 1A-1E implemented; final validation in progress  
**Primary contract:** `docs/contracts/umrah-schema.md`

## Purpose

This document records the current Travel module capabilities, identifies incomplete features and reporting gaps, and defines a production-focused Phase 1 implementation plan.

The product goal is to keep daily work simple for agents and non-accounting staff while preserving controlled accounting, audit, and reporting behavior for company owners and accountants.

## Current Baseline

The module currently supports:

- Agents with optional login accounts and voucher capabilities.
- Visa vendors with adult and child retail/cost pricing.
- Go VT `.xlsx` mutamer import for name, passport number, age, and nationality.
- Visa groups with passenger pricing, standard bus inclusion, and specialized transport replacement.
- Configurable transport vehicles, drivers, sectors, packages, fares, and Hajj Terminal surcharges.
- Hotels, hotel vendors, per-bed room rates, sharing rates, and Company/Self-arranged stays.
- Draft and approved vouchers with flights, stays, transport itinerary, passengers, company branding, and PDF export.
- Independent agent receipts and vendor payments in multiple currencies.
- Group allocations and unallocated agent/vendor advances.
- Accounting journal posting for group sales, costs, payments, allocations, and approved hotel charges.
- Search on groups and vouchers, including passenger name and passport number.
- Read-only Travel links to Accounting expenses and Payroll salaries.

## Audit Findings

### 1. Earnings Report Does Not Reconcile

The existing Earnings report is not suitable for management or accounting decisions:

- Groups are selected by `created_at`, not travel date or accounting posting date.
- Displayed cost excludes hotel cost while displayed profit includes it.
- Collected payments use payment date and are not restricted to the displayed groups.
- Balance is the all-time balance of all agents rather than the selected report period.
- The report has no agent, vendor, service, status, or date-basis filters.
- It has no pagination or PDF output.

### 2. Agent Read Access Is Too Broad

Historically, agent logins used the generic company `member` role and could navigate to Agents, Visa Vendors, Reports, Transport Services, and Drivers. The safety phase introduces a dedicated `agent` role so linked agent logins receive only Travel self-service permissions, with runtime ownership scoping on records.

An agent should be limited to:

- Its own groups and passengers.
- Its own vouchers and voucher PDFs.
- Its own received payments, allocations, advances, and statement.
- Creating groups and vouchers according to its assigned capabilities.

An agent should not see:

- Other agents or their balances.
- Company-wide revenue or profit.
- Vendor cost rates or vendor balances.
- Transport costs, drivers, or company settings.

### 3. Posted Records Have No Correction Workflow

The module lacks controlled correction actions for:

- Group details, status transitions, cancellation, and closure.
- Passenger correction or removal after group creation.
- Payment and allocation reversal.
- Accidental draft voucher deletion.
- Approved voucher amendment or reversal.

Financial records must not be physically edited or deleted after posting. Corrections must create linked reversing and replacement journal entries.

### 4. Specialized Transport Has No Separate Supplier

Transport costs are recorded, but the group does not identify a separate transport supplier. Sent payment allocation supports the group visa vendor or an approved hotel vendor only.

This prevents correct payable tracking when specialized transport is purchased from a bus, coaster, sedan, SUV, or other external transport provider.

### 5. Master Data Management Is Incomplete

Missing actions include:

- Hotel edit and deactivate.
- Hotel vendor edit and deactivate.
- Driver edit.
- Transport sector, package, and fare edit.
- Visa vendor deactivate.
- Consistent protection of all Settings read routes.
- Removal of dormant Visa Service controllers, requests, model, and Vue page after confirming no historical dependency.

### 6. Expenses and Payroll Are Not Travel-Attributed

The Travel Expenses page lists general Accounting bills, and Salaries links to general Payroll. Neither can currently be attributed to a group, voucher, agent, vendor, or transport service.

Phase 1 profitability should therefore be labelled **gross contribution** unless Travel-specific overhead allocation is introduced later.

### 7. Automated Test Coverage Is Insufficient

The existing ten unit tests cover basic hotel pricing, transport pricing, group request validation, and voucher bundles. There are no Travel feature tests for tenancy, agent privacy, imports, accounting postings, payment allocation, multicurrency, voucher approval, PDF access, corrections, or reports.

## Phase 1 Objective

Phase 1 will make the existing Travel workflow safe and reportable without adding airline ticketing, hotel inventory management, or other large booking-system features.

Phase 1 is complete when:

1. Agents can access only their own operational and financial records.
2. Company staff can safely correct or reverse mistakes without deleting accounting history.
3. Visa, hotel, and specialized transport supplier balances reconcile with payments and allocations.
4. Company owners can obtain reliable profitability, receivable, payable, and operational reports.
5. Important reports and statements can be printed or exported as PDF.

## Phase 1 Design Decisions

### Date Rules

- **Operational reports:** use `travel_date`; hotel-only vouchers use first check-in date.
- **Profitability by trip:** use the group service date derived from travel date or first hotel check-in.
- **Accounting reports:** use linked accounting transaction posting dates.
- **Payment reports:** use `payment_date`.
- Never silently mix these date bases in one total.

### Record Correction Rules

- Draft operational records may be edited or soft-deleted when no financial posting exists.
- Posted records are corrected through reversal and replacement entries.
- Approved vouchers remain immutable.
- An approved voucher amendment creates a new version linked to the prior voucher and reverses only the accounting effect being replaced.
- Every cancellation, reversal, or amendment requires a reason and records the acting user and timestamp.

### Access Rules

- Owner/admin: full Travel access.
- Accountant: financial reports, payments, allocations, expenses, statements, and read-only operations.
- Staff: operations according to explicit permissions, without cost/profit access unless granted.
- Agent member: own groups, passengers, vouchers, PDFs, payments, advances, and statement only.
- Cost fields must be omitted from server payloads, not merely hidden in Vue.

## Phase 1 Workstreams

### Workstream 1: Permission and Agent Isolation

1. Add explicit permission checks to every Travel GET controller.
2. Centralize member detection and linked-agent resolution in one reusable Travel access service.
3. Scope Dashboard, Reports, Agents, Vendors, Drivers, Transport Settings, Hotels, Payments, Groups, Vouchers, and PDFs consistently.
4. Remove company-only navigation items for agent members.
5. Return `403` for direct unauthorized URLs and avoid sending restricted financial fields to Inertia.
6. Add separate owner/accountant and own-agent report permissions where necessary.
7. Update `Permissions.php`, `role-permissions.php`, permission sync commands, and RBAC tests.

Acceptance criteria:

- An agent cannot retrieve another agent, group, voucher, passenger, payment, PDF, or statement by changing a UUID.
- An agent response never contains vendor cost, hotel cost, transport cost, company profit, or another agent's balance.
- Hidden navigation and backend authorization produce the same access result.

### Workstream 2: Reporting Foundation and Earnings Repair

1. Add a dedicated FormRequest for report date/filter validation.
2. Reject invalid date ranges and cap excessively large interactive ranges.
3. Separate report concepts:
   - Group Profitability by service date.
   - Collections by payment date.
   - Accounting Earnings by posting date.
4. Include visa, transport, and hotel amounts in revenue and cost.
5. Rename ambiguous totals:
   - `Period group balance` for selected group receivables.
   - `Total outstanding` only when intentionally showing the all-time agent balance.
   - `Gross contribution` for revenue less direct visa, transport, and hotel costs.
6. Paginate report details and calculate summaries from a separate validated aggregate query.
7. Add agent, vendor, group status, voucher status, service type, and payment-status filters where relevant.
8. Add PDF output using the same query/filter object as the on-screen report.

Acceptance criteria:

- Revenue minus direct cost equals gross contribution for every row and report total.
- Group report totals equal the sum of filtered rows.
- Payment totals are never presented as collections for a group cohort unless allocations are explicitly used.
- Accounting-date totals reconcile to linked GL transactions.

### Workstream 3: Passenger and Group Corrections

1. Keep group status informational because groups are created after visa approval; do not impose a pre-approval group lifecycle.
2. Add group editing for name, travel date, notes, agent/vendor selection, passenger data, and transport scheduling according to travel-lock rules.
3. Allow passenger correction/removal when no active approved voucher still covers the passenger.
4. When a financial change affects a posted group:
   - Lock the group and related rows.
   - Reverse the previous sale/cost transactions.
   - Recalculate passenger, visa, transport, and agent/vendor totals.
   - Create replacement sale/cost transactions.
   - Retain links between original, reversal, and replacement entries.
5. Prevent cancellation when active allocations or approved vouchers have not been reversed.
6. Add a visible activity timeline for status changes and corrections.

Required contract review:

- Cancellation reason, actor, and timestamp fields.
- Accounting reversal/replacement links or an amendment table.
- Status transition audit records.

### Workstream 4: Voucher and Payment Corrections

Voucher changes:

1. Allow deletion of an unapproved draft voucher and release its passenger assignments.
2. Move selected passengers atomically between draft vouchers in the same parent group.
3. Separate selected passengers into individual draft vouchers that retain the source itinerary, stays, flights, schedules, services, and parent group.
4. Keep approved vouchers immutable.
5. Add **Create amendment** for approved vouchers.
6. Link amendment versions and show the active version when printing.
7. Reverse prior hotel journals before posting changed Company-supplied stays.
8. Preserve prior PDF data for audit history.

Payment changes:

1. Add a payment details/receipt page with PDF output.
2. Add full payment reversal with mandatory reason.
3. Reverse allocation journals before reversing the original payment journal.
4. Restore group, agent, and vendor balances atomically.
5. Prevent editing amount, currency, exchange rate, direction, or party after posting.
6. Permit notes/reference correction only through an audited metadata action.

### Workstream 5: Transport Supplier Accounting

1. Reuse `umrah.visa_vendors` records with `vendor_type = transport_provider` as the supplier master unless a later contract review justifies a separate table.
2. Add a simplified Transport Vendor form that does not display visa adult/child rates.
3. Add a nullable transport supplier reference to specialized transport fare snapshots.
4. Add `transport_vendor_id` to sent payments and enforce exactly one vendor party: visa, transport, or hotel.
5. Calculate transport supplier outstanding amounts from immutable group transport items.
6. Allocate transport payments only to matching transport costs.
7. Include the transport supplier in AP transaction metadata and vendor statements.
8. Keep company-owned vehicles valid with no external supplier or payable.

Required contract updates must be completed before migrations.

### Workstream 6: Master Data Completion

1. Add edit/deactivate actions for hotels and hotel vendors.
2. Version or snapshot room-rate changes so historical vouchers remain unchanged.
3. Add driver edit/deactivate with assignment checks.
4. Add transport sector, package, and fare edit/deactivate.
5. Add visa/transport vendor deactivate with dependency checks.
6. Replace destructive actions with deactivate where historical references exist.
7. Remove dormant Visa Service UI/backend code only after verifying no route, foreign-key, report, or historical read dependency.

### Workstream 7: Phase 1 Reports

#### A. Group Profitability

Filters: service date, agent, visa vendor, transport vendor, group status, payment status.  
Columns: group, agent, passenger count, visa revenue/cost, transport revenue/cost, hotel revenue/cost, discount, total revenue, direct cost, gross contribution, received allocation, and balance.  
Access: owner/admin/accountant; optional staff permission.  
Output: screen and PDF.

#### B. Agent Statement

Filters: agent, date range, group, transaction type.  
Rows: opening balance, group charges, payment allocations, unallocated receipts/advances, reversals, and closing balance.  
Access: company staff for any agent; agent member for self only.  
Output: screen and PDF.

#### C. Receivable Aging

Buckets: current, 1-30, 31-60, 61-90, and over 90 days.  
Basis: group service/posting date selected explicitly.  
Columns: agent, group, date, receivable, allocated, balance, age, and bucket.  
Output: summary by agent, detailed rows, and PDF.

#### D. Vendor Statement and Payable Aging

Parties: visa, transport, and hotel vendors.  
Rows: cost recognition, payment allocations, unallocated advances, reversals, and closing balance.  
Filters: vendor type, vendor, date range, group, and allocation state.  
Output: screen and PDF.

#### E. Advances and Allocation Report

Sections: unallocated agent receipts and unallocated vendor payments.  
Columns: payment number, date, party, original currency amount, exchange rate, base amount, allocated amount, available amount, and age.  
Output: screen and PDF.

#### F. Passenger and Visa Status Report

Filters: travel date, agent, group, nationality, visa status, and vendor.  
Columns: group, passenger, passport, age, nationality, service type, visa status, and travel date.  
Agent members see only their own passengers.  
Output: screen and PDF.

#### G. Departure Manifest

Filters: departure date, airline, flight number, departure city, arrival city, agent, and group.  
Columns: passenger, passport, nationality, group, agent, flight, departure, arrival, and transport requirement.  
Output: printable/PDF manifest.

#### H. Hotel Rooming List

Filters: hotel, city, check-in range, agent, group, and Company/Self source.  
Columns: hotel, dates, nights, room type, rooms/beds, passengers, group, agent, and notes.  
Cost fields are company-only.  
Output: hotel-facing PDF without financial values and internal PDF with values.

#### I. Transport Dispatch

Filters: schedule date, sector/package, service, driver, terminal, agent, and group.  
Columns: schedule, route, vehicle, capacity, assigned passengers, driver/contact, terminal, group, and notes.  
Output: driver/dispatcher PDF without financial values.

#### J. Voucher Control Report

Filters: draft/approved, agent, creator, departure/check-in date, and cutoff status.  
Sections: awaiting approval, approaching deadline, overdue drafts, and recently approved.  
Output: screen and PDF.

### Workstream 8: Tests and Release Controls

Backend feature tests:

- Company isolation and RLS for every Travel table.
- Agent own-record scoping and UUID tampering attempts.
- Go VT import success and invalid workbook handling.
- Group creation and exact GL sale/cost entries.
- Standard bus deduction and specialized transport supplier payable.
- Independent multicurrency payments and immutable conversion snapshots.
- Allocation limits, duplicate prevention, and party matching.
- Payment reversal and balance restoration.
- Voucher approval, amendment, hotel posting, and PDF authorization.
- Report filter validation and reconciliation fixtures.

Frontend/E2E tests:

- Agent navigation and direct-URL denial.
- Group lifecycle and correction confirmations.
- Payment reversal loading, success, and error states.
- Report filters, empty states, pagination, and PDF actions.
- Desktop and mobile layouts for tables and printable documents.

Release checks:

- Run focused Travel tests on every workstream.
- Run `composer quality-check`.
- Run `php artisan layout:validate --json`.
- Run `bash validate-migration.sh` for schema changes.
- Test migrations against a production-like PostgreSQL backup.
- Reconcile sample agent, vendor, group, payment, and GL balances before deployment.

## Recommended Delivery Order

Implementation progress as of 2026-07-16:

- Phase 1A Safety: implemented.
- Phase 1B Corrections: implemented.
- Phase 1C Supplier Accounting: implemented.
- Phase 1D Reports and Operations: implemented with shared validated filters, pagination, agent scoping, and PDF output.
- Phase 1E Master Data and Cleanup: implemented with non-destructive activation controls and historical snapshot protection.

### Phase 1A: Safety

- Agent isolation and GET-route permission enforcement.
- Restricted navigation and payload redaction.
- Earnings calculation repair and validated date rules.
- Feature tests for permissions and report reconciliation.

### Phase 1B: Corrections

- Group lifecycle and cancellation.
- Passenger correction/removal.
- Draft voucher deletion.
- Approved voucher amendment.
- Payment/allocation reversal.

### Phase 1C: Supplier Accounting

- Transport supplier association.
- Transport payable calculation and allocation.
- Visa, transport, and hotel vendor statements.

### Phase 1D: Reports and Operations

- Agent statement and receivable aging.
- Group profitability.
- Vendor payable aging and advances.
- Passenger status, departure manifest, rooming list, transport dispatch, and voucher control reports.
- PDF output for all completed Phase 1 reports.

### Phase 1E: Master Data and Cleanup

- Complete edit/deactivate workflows. Implemented for hotels, hotel vendors, drivers, transport services, sectors, packages, fares, and visa/transport vendors.
- Remove dormant Visa Service implementation. Setup routes, writes, requests, controller, and Vue page removed; read-only model/table references remain for historical groups.
- Preserve hotel and transport pricing snapshots when current master rates change.
- Keep deactivated suppliers payable while historical balances remain outstanding.
- Final regression, reconciliation, and deployment validation.

## Explicitly Outside Phase 1

- Airline ticket booking or ticket inventory.
- Airline API/GDS integration.
- Hotel room inventory, allotment, or availability engine.
- Automated currency exchange-rate feeds.
- Automated government visa submission.
- Customer-facing booking portal.
- Complex expense or payroll allocation to individual trips.
- General-purpose analytics or dashboard builder.

These can be considered after the existing operational and accounting records are secure, correct, reversible, and reportable.
