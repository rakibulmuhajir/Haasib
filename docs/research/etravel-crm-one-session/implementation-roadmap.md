# Proposed implementation roadmap after the second demo

This is a product sequence, not authorization to create tables. Every schema change must first update `docs/contracts/umrah-schema.md` and follow the project's database, RBAC, controller, model, and frontend remediation guides.

## Product flow to build toward

1. **Commercial setup** — the owner/manager defines only the services the company sells: visa, transport, hotels, add-ons, and optional reusable packages.
2. **Quick Booking** — the clerk first chooses the service combination, then enters or imports passengers; the system asks only for relevant details and resolves prices/defaults.
3. **Service Voucher** — the clerk confirms flights, stays, transport and passenger assignments; approval becomes a readiness gate.
4. **Operations** — the team works arrivals, hotel changes, city transfers and departures in local event time.
5. **Money and reports** — posting, collections, supplier payments, statements and profitability remain automatic and role-appropriate.

Owners see totals and exceptions. Clerks see passengers, tickets, airports, hotels and operational actions. Agents see only their own business. Accountants see sources, allocations and journals. A role may open another section when permitted, but it should not be forced into the wrong home page.

## Slice 0 — validate the proposed domain (short, before coding)

- Walk through three realistic bookings with the client: standard package, custom itinerary, and hotel/transport-only.
- Confirm whether agent pricing is one override per service or named tiers/categories are genuinely needed.
- Confirm whether package price is calculated from components or can be a fixed sell price with underlying cost.
- Confirm which fields operators actually maintain: MOFA number, BRN, hotel confirmation, meal/view, ziyarat, passport scan.
- Confirm whether controlled hotel room stock is sold. If not, defer room inventory/allotments.
- Decide which existing Visa Group language becomes user-facing `Booking`, `Trip`, or `Group` without renaming the database prematurely.

Deliverable: approved flow and contract proposal. No migration yet.

## Slice 1 — Packages and effective-dated rate books (P0)

### Simplified setup experience

- Keep one default company price for each service.
- Allow optional agent-specific override; introduce categories only if the client has repeated tiers.
- Add effective and end dates with overlap checks.
- Snapshot the resolved price/cost/rate source on the booking so later changes never rewrite history.
- Build an optional Umrah package template with ordered city stays, default hotels or hotel class, nights, room basis, transport journey, and optional ziyarat/add-ons. Service-only companies may skip packages entirely.
- Keep the owner's setup concise; advanced accounting mappings live behind accountant permissions.

### Guardrails

- Contract first; UUID/RLS/company context throughout.
- Server resolves precedence: explicit approved booking override -> agent override -> company default.
- Do not let agent users submit supplier cost or exchange-rate truth.
- Reject overlapping active rules at the same scope.
- Package templates never own historical accounting; bookings own immutable snapshots.

## Slice 2 — Quick Booking workspace (P0)

### Clerk view

- `What are you selling?`: visa only, visa + transport, transport only, hotel only, complete package, or a permitted custom combination
- Agent/customer
- Booking/group reference and travel date
- Package and room basis only when the selected service uses them
- Passenger count or named passenger rows
- Spreadsheet import with preview
- Only the relevant resolved visa/transport/hotel/package defaults shown as editable operational values
- Plain-language sell total, received, and balance; no debit/credit or chart-account choices
- `Save draft` and `Ready for voucher` actions with explicit missing-data reasons

### Owner view

- Booking count, passenger count, sales total, outstanding and exceptions
- No passport, ticket, airport, hotel detail by default

### System behavior

- Reuse existing agent/vendor/hotel/transport/passenger/group services.
- Generate or reuse the Visa Group as the financial source rather than creating a second competing booking aggregate. Package selection remains nullable.
- Post only at the existing approved business boundary.
- Preserve Inertia success/error/loading handling and Sonner feedback.

## Slice 3 — Voucher readiness and fulfillment details (P1)

- Add structured hotel BRN/confirmation status/reference only after operator validation.
- Add optional meal plan, view type and excluded-night behavior if these affect the issued voucher.
- Add MOFA/visa reference fields and audited bulk status update.
- Add secure passport document handling only with storage, access, retention and download-audit rules.
- Add configurable ziyarat/add-on services with schedule, provider, price/cost snapshots and an Operations event.
- Turn voucher approval into an explicit readiness checklist rather than a generic `Final` flag.
- Keep Haasib's amendment/supersession accounting; never reopen an approved voucher in place.

## Slice 4 — Full booking import and bulk work (P1)

- Downloadable Haasib template with version and example rows.
- Upload -> parse -> preview -> validate -> resolve duplicates -> approve.
- Report row-level errors without discarding valid work.
- Allow passenger-only import and full booking import as separate explicit modes.
- Support package/agent/hotel/reference/status mapping.
- Never create financial postings during preview.
- Treat SMS/WhatsApp as a separate, consented outbound-message project.

## Slice 5 — Reporting and accounting experience (P1)

- Add XLSX/CSV export to the canonical report service so screen, PDF and spreadsheet share filters and totals.
- Add saved report views such as `Tonight at JED`, `Tomorrow Makkah -> Madinah`, and `Agent balances` instead of cloning reports.
- Add global Umrah search by voucher, group, passport, passenger, PNR and ticket with server-side role scoping.
- Add source-grouped account balance explanation: visa, transport, hotel, ticket, receipt, refund and adjustment.
- Add a finance home/day-book view for owner/accountant only: receipts, payments, cash, bank, exceptions and drill-through.
- Add the previously identified posting-integrity monitor before offering any automated repair action.

## Deferred until proven

- Hotel room/allotment inventory and overbooking controls
- Complaints and passport-delivery queues
- Scanner booking
- Promotional offers
- Ticket-group seat inventory
- Live airline data, GPS and driver tracking
- Automated external messaging

These are visible in the competitor but were not sufficiently exercised—or are separate products in their own right.

## Recommended immediate decision

Approve or revise **Slice 1 + Slice 2** as the next project: `Service Rate Books + Optional Packages + Quick Booking`. Operations should remain in place and consume the better itinerary data produced by that work; it should not be expanded with speculative dispatch statuses first.
