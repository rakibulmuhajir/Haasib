# Umrah prioritized feature backlog

**Purpose:** Canonical product-priority document for making Haasib genuinely useful to an Umrah travel company.

**Research basis:** The [eUmrah demo research](research/eumrah-demo/README.md), the [eTravel CRM training-call research](research/etravel-crm-one-session/README.md), and the current Haasib Umrah implementation.

## How to read this document

- **Must have (P0):** Required for a travel office to sell, prepare, operate, and account for Umrah bookings without fighting the software.
- **Should have (P1):** High-value improvements after the complete P0 workflow works reliably.
- **Good to have (P2):** Useful efficiencies that are not required for the first dependable operating workflow.
- **Later / validate first (P3):** Expensive or specialized capabilities that need real operator evidence before implementation.

Statuses:

- **Present:** The capability exists and should not be rebuilt.
- **Partial:** Useful foundations exist, but the user workflow is incomplete.
- **Missing:** No dependable user workflow exists yet.

Priority is based on daily usefulness and dependency order—not on what looked impressive in a competitor menu.

---

## P0 — Must-have features

### 1. Operations control page

**Status:** Present; continue validating and refining.

The clerk/dispatcher must immediately see:

- Tonight, tomorrow, custom date, and next-seven-day movements
- Airport arrivals and Saudi return departures
- Hotel check-ins and check-outs
- Makkah to Madinah and Madinah to Makkah transfers
- Scheduled transport pickups
- Passenger totals and, where the role permits, passenger/passport detail
- Missing flight, hotel, transport, vehicle, or driver information
- Printable movement reports

Role rules:

- Owner: aggregate `Moving in`, `Moving out`, inter-city, and exception totals; Operations is available but is not the owner's home page.
- Clerk/operations: detailed Operations page as the primary working view.
- Agent: only its own passengers and no supplier cost/driver-private detail.
- Accountant: source links and financial context without replacing the operational view.

Time must remain simple: display and group an event using the local clock of the place where the passenger is, like an airline itinerary.

### 2. Effective-dated rate books

**Status:** Missing reusable commercial layer; current provider prices are only a foundation.

Required behavior:

- One easy company default price for each sellable service
- Optional agent-specific override
- Named pricing categories only if the business actually uses repeated tiers
- Effective and end dates
- Clear precedence: approved booking override, then agent override, then company default
- Separate protected supplier cost and customer sale price
- Adult/child rules where relevant
- Transport, hotel, visa, and later add-on service pricing
- Immutable price/cost/exchange-rate snapshots on the booking
- Overlapping-rate validation

Operational users and agents must not choose ledger accounts or submit supplier cost truth.

### 3. Optional reusable Umrah packages

**Status:** Missing. Haasib has transport journey packages, not complete sellable Umrah packages. Complete packages are optional and must never be required for visa-only, visa-and-transport, transport-only, or hotel-only businesses.

A package template should define:

- Name and active dates
- Ordered Makkah/Madinah stays
- Default hotel or hotel class for each stay
- Default nights and room basis
- Default transport journey
- Optional ziyarat/add-on services
- Applicable rate book or fixed approved selling price
- Default voucher itinerary values
- Internal notes and customer-facing description

The package is a template. The booking receives immutable snapshots so later package edits never rewrite history.

A company that does not sell complete packages can skip this setup entirely. Its users should not see empty package fields or package-focused navigation as mandatory work.

### 4. Quick Booking workspace

**Status:** Missing as a dedicated workflow; current Visa Group creation is capable but too setup/accounting-oriented.

The clerk should first choose **What are you selling?**:

- Visa only
- Visa + standard transport
- Visa + specialized transport
- Transport only
- Hotel only
- Complete package
- Custom combination, when the role permits it

Haasib should then show only the relevant fields. For example, a visa-and-transport company should not have to create a package or enter hotels to record its normal work.

The clerk should be able to create a normal booking from one page using:

- Agent/customer
- Booking/group reference
- Travel date
- Package and room basis only when accommodation/package service is selected
- Passenger count or named passenger rows
- Passenger spreadsheet import
- Resolved visa, transport, hotel, and package defaults according to the selected service
- Plain-language selling total, received, and balance
- `Save draft` and `Ready for voucher` actions

The server must resolve rates, suppliers, costs, and accounting. The package reference is nullable; service-only bookings use the same rate engine without a package. The clerk should not need to understand journals.

### 5. Complete booking-to-voucher workflow

**Status:** Partial; universal vouchers and strong approval/amendment controls already exist.

The booking must become one complete service packet containing:

- Selected passengers
- Passport and visa status/reference
- Onward and return flights
- Repeating hotel stays
- Transport routes and assignments
- Self-arranged versus company-supplied services
- Customer-facing notes
- Company/agent branding where permitted
- Approval/readiness state
- Printable/PDF voucher

Keep one universal voucher. Do not create disconnected accommodation, transport, and package accounting records merely because the competitor has separate menu entries.

Approval must clearly explain missing requirements, for example:

- Return flight missing
- First or final stay missing
- Hotel dates conflict
- Transport required but not assigned
- Driver or vehicle missing
- Passenger has no voucher
- Hotel confirmation pending

Approved vouchers must continue to use Haasib's amendment/supersession workflow rather than being silently edited in place.

### 6. Passenger, passport, and visa workbench

**Status:** Partial; passenger identity, age, passport number, service type, and visa status already exist.

Required additions/workflow improvements:

- Fast passenger list editing
- Bulk visa-status update
- Structured MOFA/visa reference if the client uses it
- Duplicate-passport detection and review
- Search by passenger and passport
- Clear passenger-to-booking and passenger-to-voucher assignment state
- Existing safe passenger move/separate capability presented as a simple wizard

Passport scans are P1 because they require security, retention, storage, and download-audit rules.

### 7. Controlled booking and passenger import

**Status:** Partial; Haasib imports Mutamer rows into group creation but does not yet import a complete booking.

Required workflow:

1. Download versioned Haasib template.
2. Upload file.
3. Parse and preview rows.
4. Show row-level validation and duplicate conflicts.
5. Resolve agent, package, hotel, and status mappings.
6. Approve the import.
7. Create draft booking/passenger records.

The preview must never post accounting. A casual `Allow duplicate` checkbox must not bypass passport integrity.

### 8. Automatic accounting from Umrah work

**Status:** Present and stronger than the demonstrations; protect it.

The system must continue to handle automatically:

- Agent receivable and revenue
- Visa, transport, hotel, and ticket supplier cost/payable
- Receipts and supplier payments
- Partial and multi-booking allocations
- Advances/unallocated balances
- Refunds and reversals
- Discounts and approved adjustments
- Transaction currency and base-currency snapshots
- Balanced, traceable journals

Clerks should see `Sale`, `Cost`, `Paid`, `Balance`, and `Profit` where permitted—not debit/credit setup.

### 9. Essential operational and financial reports

**Status:** Largely present; spreadsheet export and a few presentation improvements remain.

Must remain dependable:

- Operations movement report
- Passenger and visa status
- Departure manifest
- Hotel rooming
- Transport dispatch/readiness
- Voucher control
- Group profitability
- Agent statement and receivable aging
- Vendor payable aging
- Advances and allocations
- Ticket sales, reconciliation, and cancellations
- Screen and PDF totals generated from the same validated dataset

Do not create separate reports for every airport/city/flight permutation. Use shared filters and saved views.

### 10. Role-appropriate home pages and data exposure

**Status:** Partial/present; treat as a permanent acceptance rule.

- Owner home: company totals, balances, movement totals, and serious exceptions
- Clerk/operations home: today's operational work and missing information
- Agent home: own bookings, passengers, vouchers, status, and statement
- Accountant home: collections, payments, allocations, reconciliations, and posting exceptions

Hiding columns in the browser is insufficient. Restricted fields must be omitted from server payloads.

---

## P1 — Should-have features

### 1. Hotel fulfillment details

**Status:** Missing structured data.

- BRN/booking reference
- Confirmation status and confirmation number
- Meal plan
- View type where relevant
- Excluded/free night handling
- Operational hotel contact
- Stay-to-supplier-posting drill-through

Add only the fields operators will actually maintain.

### 2. Ziyarat and configurable add-on services

**Status:** Missing.

- Makkah/Madinah ziyarat or other add-on type
- Date/time and pickup point
- Provider, vehicle/driver where applicable
- Customer price and supplier cost snapshots
- Voucher display
- Operations event
- Automatic accounting

Use a configurable service model instead of hardcoding two ziyarat checkboxes.

### 3. Secure passport-document handling

**Status:** Missing.

Before implementation, define:

- Who may upload, view, download, and delete
- Encryption/private storage
- Retention and deletion rules
- File type/size and malware validation
- Access audit history
- Agent isolation

### 4. Universal Umrah search

**Status:** Missing.

Search within the permitted scope by:

- Voucher number
- Booking/group number
- Passport
- Passenger
- PNR/flight
- Ticket
- Agent

Results should open the exact working record and must respect role scoping.

### 5. Spreadsheet export and saved report views

**Status:** Missing/partial.

- XLSX or CSV from the same report service used by screen/PDF
- Preserved filters and totals
- Saved views such as `Tonight at JED`, `Tomorrow Makkah to Madinah`, and `Unconfirmed hotels`
- Role-specific default views

### 6. Finance day book and balance explainer

**Status:** Partial across existing accounting/payment surfaces.

For owner/accountant roles:

- Today's receipts and payments
- Cash and bank position
- Opening, invoices, payments, refunds, adjustments, and closing balance
- Balance grouped by source: visa, hotel, transport, ticket, receipt, refund, adjustment
- Drill-through to the source booking/voucher/payment and canonical ledger entry

This belongs on the finance home—not the clerk's Operations page.

### 7. Searchable report launcher

**Status:** Partial.

When the report catalogue becomes large, provide a searchable launcher grouped by Operations, Sales, Receivables, Payables, Tickets, and Controls. Keep the existing purposeful reports; do not generate dozens of overlapping calculations.

### 8. Accounting integrity monitor

**Status:** Missing.

Detect and explain:

- Approved source missing its expected transaction
- Unbalanced journal
- Duplicate posting
- Source total differing from posted total
- Cancellation/supersession missing a reversal
- Orphan allocation or accounting reference

Repair must be an explicit, permission-controlled, audited action—not `edit and save again`.

---

## P2 — Good-to-have features

### 1. Hotel room/allotment inventory

Only build if the company controls and sells room stock. Possible scope:

- Hotel/room type allotment by date
- Purchased rooms/beds
- Sold and remaining capacity
- Overbooking warning
- Supplier reservation reference
- Rooming allocation

Do not build individual room/floor/bed management merely because it is visible in the demo.

### 2. Passport delivery queue

- Passports ready for collection/delivery
- Recipient and handover details
- Delivered by/date/time
- Proof/notes
- Agent-scoped status

### 3. Complaints and service issues

- Booking/passenger/service link
- Owner and assignee
- Severity/status/due date
- Resolution notes
- Operations exception visibility

Use the shared task system if it can satisfy this without an Umrah-only subsystem.

### 4. Actual transport execution status

- Assigned
- Driver confirmed
- En route
- Passenger collected
- Completed
- Delayed, cancelled, or no-show
- Scheduled versus actual time

This is different from the competitor's movement report. Implement only after a dispatcher confirms the workflow.

### 5. Booking and voucher audit timeline

A friendly timeline of creation, import, passenger changes, rate source, approval, amendment, service changes, payments, and reversals—backed by existing audit records.

### 6. Configurable document/branding templates

- Company or agent logo according to role and policy
- Customer-friendly package summary
- Detailed service breakdown
- English/Urdu terms
- Operational contacts with privacy controls

Keep one accounting transaction; presentation variants must not duplicate invoices.

---

## P3 — Later or validate first

- Scanner-assisted passport/booking entry
- Live airline status integration
- GPS/driver tracking
- Automated WhatsApp/SMS/email notifications
- Ticket-group/allotment seat inventory
- Promotional offers/campaigns
- Full hotel reservation/purchase/cancellation engine
- External supplier/customer portals beyond current agent self-service
- Advanced booking rule engine
- Mobile driver application

Each is a separate project with its own permissions, privacy, failure handling, and operating cost. None should delay the P0 booking-to-operations workflow.

---

## Patterns we will not copy

- Default/shared customer passwords
- Accounting setup on everyday operational forms
- Unrestricted backdated changes
- Direct agent access to supplier cost or margin
- Blind imports that immediately create financial postings
- Easy duplicate-passport bypasses
- Reopening an approved voucher to repair accounting
- Multiple overlapping reports with different totals
- Duplicate invoices for the same transaction/currency presentation
- Owner dashboards filled with passenger, airport, hotel, or driver detail

---

## Agreed implementation sequence

### Next project

1. Update the Umrah schema/product contract for independent service rate books, optional packages, and a nullable package reference.
2. Implement effective-dated default and agent-specific rates for visa, transport, hotels, and later add-ons.
3. Build Quick Booking with visa-only, visa-and-transport, transport-only, hotel-only, complete-package, and permitted custom modes.
4. Implement reusable Umrah packages as an optional shortcut for companies that sell them.
5. Feed every booking mode into the same existing voucher, Operations, and accounting workflows where applicable.

### Then

6. Add voucher readiness reasons and validated hotel/MOFA details.
7. Expand import from passengers-only to controlled full-booking import.
8. Add universal search, saved views, and spreadsheet exports.
9. Add P1 finance explanation and integrity controls.

P2 and P3 features remain outside implementation until the P0 workflow is working end-to-end and real operators confirm the need.
