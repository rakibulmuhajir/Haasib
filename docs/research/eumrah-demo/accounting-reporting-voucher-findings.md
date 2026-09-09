# Accounting, reporting, and voucher findings

The operational reports are the strongest lesson from the demo, but the accounting, reporting, and voucher sections also contain useful patterns. They should be adopted selectively because Haasib's accounting foundations are already more rigorous.

## Voucher findings

Evidence: [pending voucher queue](screenshots/06-00-pending-vouchers.png), [voucher operations](screenshots/09-00-voucher-operations.png), [package invoice breakdown](screenshots/12-00-package-invoice-breakdown.png)

### 1. Voucher as the complete service packet

The competitor's voucher combines:

- Passenger and passport/visa identity
- Outbound and return flights
- Repeating hotel stays
- Full-route or sector-by-sector transport
- Repeating ziyarat/excursions
- Terms in English or Urdu
- Makkah/Madinah operational contacts
- Agent/company branding and QR access

Haasib already supports passengers, flights, repeating hotel stays, and configurable transport sectors. The clearest missing voucher capability is first-class ziyarat and structured operational contact/confirmation detail.

### 2. Approval as a readiness gate

The pending list visually separates partially completed vouchers from vouchers that can be approved. The useful principle is:

> An approved voucher should be safe to hand to the pilgrim and usable by airport, transport, and hotel staff.

Haasib should improve this with explicit reasons rather than the competitor's vague `Half Edit` label:

- Missing return flight
- Missing first hotel
- Transport required but unassigned
- Driver or vehicle missing
- Passenger not assigned
- Hotel confirmation pending

### 3. Compact versus detailed financial presentation

The demo provides a summarized package invoice and a full component breakdown. Haasib should retain one accounting transaction/invoice while offering two presentation modes:

- **Package summary:** one customer-friendly total
- **Service breakdown:** ticket, visa, hotel, transport, ziyarat, discount, tax, and base-currency equivalent

Do not create duplicate invoices merely to display transaction and base currency. Haasib's multi-currency contract should remain authoritative.

### 4. Branding and contacts

The voucher can carry the agent's branding and can show contacts based on selected hotels and transport/ziyarat providers. These are useful customer-service features, but contact visibility needs a privacy and role review before implementation.

## Reporting findings

### 1. Reports are organized around questions

The strongest reports answer one operational or financial question at a time:

- Who is landing?
- Who is checking in?
- Who is moving cities?
- Which hotel rooms/nights are required?
- Which vouchers remain incomplete?
- What is owed by this client or to this supplier?

Haasib's common report renderer is reusable, but the default columns and filters must remain task-specific.

### 2. Shared filter vocabulary

The competitor repeatedly uses:

- Date/date range
- Client/agent
- Arrival airport or destination city
- Flight
- Saudi service company (`Shirka`)
- Transport provider
- Hotel and hotel supplier
- Room type
- Booking/stock confirmation status

The repetition is useful: users learn one filter grammar. Haasib can implement the same consistency through shared event filters rather than duplicated forms.

### 3. Summary-to-detail drill-down

Dashboard counts open the underlying report. This pattern should apply to:

- Event counts -> filtered Operations view
- Pending passengers -> passengers missing vouchers
- Agent balance -> agent statement
- Supplier payable -> supplier statement
- Hotel room count -> hotel check-in/rooming list

The selected date and filters must survive the drill-down.

### 4. Print/PDF as operational output

The arrival and check-in reports are designed to be printed. Haasib already has generic PDF infrastructure and should produce distinct layouts for:

- Airport manifest
- Hotel handoff/rooming list
- Transport dispatch sheet
- Agent/customer statement

The screen can remain interactive; the PDF should remove UI-only columns and optimize grouping/totals.

## Accounting findings

### 1. Party balance dashboard

The demo surfaces receivables, payables, cash/bank, and a ranked client balance list. Clicking a value opens the underlying statement. Haasib should retain operational priority on the Operations home, while the finance section can use the same drill-through behavior.

### 2. One operation feeds component accounting

Voucher/package activity becomes ticket, visa, hotel, transport, and ziyarat charges. This is consistent with Haasib's direction that business operations post accounting automatically. Haasib should preserve its current safeguards and never require an operator to create journal entries manually.

### 3. Invoice-wise party statement

Evidence: [invoice-wise account statement](screenshots/26-00-invoice-wise-account-statement.png), [statement options](screenshots/28-00-account-statement-options.png)

The demonstrated statement shows:

- Ticket, visa, and voucher invoices separately
- Payments allocated against invoices
- Partial payment and remaining balance
- Unadjusted/unallocated receipts
- Opening and closing balance
- Local or foreign currency view

Haasib already has agent statements, advances/allocations, and aging. A valuable improvement is an operator-friendly invoice grouping with drill-through, not another independent ledger calculation.

### 4. Head-wise ledger

Evidence: [ledger currency options](screenshots/24-00-ledger-currency-options.png)

The competitor can summarize a party ledger by ticket sales, visa charges, hotel vouchers, cash receipts, bank receipts, and general vouchers. This is useful for explaining a balance. Haasib can provide the same understanding as an **account balance explainer** backed by its existing ledger and source references.

### 5. Hotel supplier reporting

Evidence: [hotel payment report](screenshots/15-00-hotel-payment-report.png)

The hotel-wise payable report links operational room nights to the supplier amount. Haasib currently separates Hotel Rooming and Vendor Payable Aging. A combined drill-through from a stay to its supplier posting would make reconciliation easier without merging the accounting calculations.

### 6. Posting integrity monitor

The demo describes a “voucher crash report” that finds vouchers whose debit or credit posting did not complete. The recovery method shown—reopen and modify the voucher—is not desirable, but the control idea is excellent.

Haasib should eventually expose an accounting integrity monitor that detects:

- Referenced transaction missing
- Unbalanced journal
- Operational record posted twice
- Approved record with no expected posting
- Superseded/cancelled record whose reversal is missing
- Source total differing from posted total

Repair must be an explicit, audited service action; editing a voucher should never be the magic wrench.

### 7. Group ticket inventory

The competitor tracks PNR purchases, sales, sold seats, and remaining seats with accounting integration. Haasib now has ticket booking and cancellation models, but group allotment/seat inventory is a separate future capability and should not be smuggled into the Operations work.

## Patterns not to copy

- Do not duplicate one invoice in multiple currencies; present one transaction with its currency and base equivalent.
- Do not post hundreds of imported MOFA rows blindly; validate, preview, and approve before accounting effects.
- Do not treat reopening/editing a voucher as a posting repair mechanism.
- Do not expose supplier cost, payable, or margin to agent users.
- Do not create ten ledger variants with overlapping totals. Provide a canonical statement plus purposeful groupings.
- Do not place financial balances above urgent event exceptions for an operations user.

## Recommended priority outside Operations

| Improvement | Priority | Reason |
|---|---|---|
| Explicit voucher readiness reasons | P0 | Directly improves event reliability |
| Summary/detail invoice presentation | P1 | Better customer communication without accounting duplication |
| Hotel stay -> supplier posting drill-through | P1 | Joins operations and reconciliation |
| Account balance explainer/head-wise grouping | P1 | Makes accounting understandable to non-accountants |
| Accounting integrity monitor | P1 | High-value safety control |
| Ziyarat as a first-class voucher/event service | P2 | Useful but outside the immediate arrival/movement gap |
| Group ticket allotment inventory | P2 | Valuable separate workflow |
