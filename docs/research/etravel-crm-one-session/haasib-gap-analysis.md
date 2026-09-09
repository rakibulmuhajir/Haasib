# Haasib gap analysis against the tutorial

## What Haasib already does better

Haasib should not be rebuilt in the competitor's shape. It already has stronger foundations in several areas:

- Tenant isolation, route-based company context, UUIDs, RLS, and centralized RBAC
- Agent-linked self-service with server-side scoping and supplier-cost privacy
- Explicit visa, transport, hotel, ticket, payment, refund, and expense accounting
- Independent receipts/payments with allocations and advances rather than booking-bound cash only
- Multi-currency snapshots and balanced posting rules
- Draft/approve/cancel/amend/supersede voucher history
- Safe passenger move/separation rules that prevent duplicate hotel posting
- Configurable transport sectors, packages, providers, vehicles/services, fares, drivers, and schedules
- A unified Operations timeline for airport arrivals/departures, hotel check-ins/outs, city transfers, transport pickups, owner summaries, readiness warnings, and printable output
- Purposeful Umrah reports for profitability, statements, aging, passenger status, departure, rooming, dispatch, voucher control, and ticketing

The competitor's value is its breadth of daily travel-office workflows and its setup-to-booking acceleration—not its accounting architecture or dated UI.

## Capability matrix

| Capability | Tutorial | Haasib today | Verdict | Recommended treatment |
|---|---|---|---|---|
| Agent/customer master and portal login | Customer type, branch, category, account and login | Agents can link to users and are self-scoped; voucher access is configurable | Present/stronger | Keep Haasib model; improve presentation rather than duplicate customer types |
| Employee permissions | Many per-action finalization checkboxes and module tree | Central permissions plus role-aware navigation/payloads | Present/stronger foundation | Add only proven workflow permissions; avoid a checkbox jungle |
| Branch visibility | Party/staff branch and cross-branch options | Shared company/RBAC infrastructure exists, but the Umrah flow is primarily company-scoped | Partial | Validate real multi-branch operating need before adding Umrah-specific branch dimensions |
| Provider-linked accounts | Sale, cost and payable accounts on each provider | Posting services and party/vendor accounting create controlled transactions | Present by a safer design | Do not force clerks to choose ledger accounts on supplier setup unless an accountant requests mapping |
| Agent/category price rules | Category default plus customer-specific override and precedence | Current visa/transport providers expose current default rates; groups can snapshot/override allowed totals | Missing reusable commercial layer | P0: simple effective-dated rate books with default and agent override |
| Visa component rates | Adult/child/infant plus transport, ground, portal and VAT | Adult/child visa rates and independent transport; infant visa charging was intentionally removed; tax is shared infrastructure | Partial | Model named sellable components, not competitor-shaped columns; keep age policy explicit |
| Effective/cease dates | Visa, hotel, transport sale and cost validity windows | Current hotel/provider rates are mostly one active value per item | Missing | P0: effective-dated prices with overlap validation and immutable booking snapshots |
| Hotel master | Supplier, city, details, accounts, settings | Hotels and hotel vendors with Makkah/Madinah and per-bed retail/cost | Present but simpler | Add only booking/operations fields that deliver value |
| Hotel room inventory/allotment | Individual rooms/beds/floors, active windows, import | Rate catalogue exists; no room/allotment inventory | Missing | P2 unless the client sells controlled room stock; do not confuse room type pricing with inventory |
| Umrah package catalogue | Named package links hotels and booking defaults | Transport journey packages exist, but no reusable Umrah product/itinerary package | Missing | P0: package template with ordered stays, nights, transport/ziyarat options and pricing reference |
| Quick booking | Compact customer/package/hotel/passenger entry | New Visa Group is capable but setup/accounting-oriented and asks for many transport/commercial choices | Partial and high-friction | P0: role-aware Quick Booking that hides accounting and resolves defaults server-side |
| Full booking lifecycle | Draft/final, group/IATA/visa status, service details | Group status is intentionally hidden/internal; voucher has draft/approved/cancelled | Partial | Keep safe approval semantics; expose plain-language readiness instead of copying `Final` everywhere |
| Passenger/passport entry | Passenger rows and passport copy | Passenger identity/status and spreadsheet import; no document attachment | Partial | P1: secure passport document workflow only with retention/access rules |
| MOFA/visa reference updates | Create booking + update MOFA or update only; IATA/visa bulk menus | Passenger visa status exists; no structured MOFA number/bulk update workflow | Missing | P1: previewed bulk reference/status import with audit trail |
| Full booking import | Template creates booking and applies package/customer/IATA/hotel defaults | Mutamer sheet import populates passenger rows in group creation | Partial | P1: expand to preview/validate a complete booking; never post blindly |
| Duplicate passport control | Company flag and import checkbox | Tenant passenger validation exists but no user-facing import policy switch | Partial | Prefer strict duplicate detection with explicit review/merge; avoid a casual bypass checkbox |
| Passenger transfer/split | Existing booking, new split, or other customer | Voucher passenger move and separate workflows with billing ownership and history | Present/stronger | Keep current safer workflow; consider a clearer wizard |
| Complete service voucher | Passenger, PNR/visa, flights, hotels, transport, ziyarat, price/cost | Universal voucher has passengers, flights, repeating stays, service bundles, PDF and amendment history | Present core | Preserve universal voucher instead of separate duplicated voucher types |
| Hotel confirmation details | BRN, confirmation, meal plan, view, excluded night | Stay snapshots have hotel, city, dates, room quantity/type, source and price; no structured confirmations/meals | Missing operational detail | P1: add only after contract update and operator validation |
| Ziyarat/add-on services | Makkah/Madinah ziyarat schedule; generic service invoices | No first-class ziyarat/generic Umrah add-on service | Missing | P1/P2: configurable itinerary service that also becomes an Operations event |
| Arrival/movement reporting | Numerous separate airport/city/flight/PAX report variants | Unified Operations timeline plus report/PDF and role-specific detail | Present and more coherent | Keep one Operations grammar; add saved views instead of dozens of near-duplicate reports |
| Report catalogue and search | Searchable catalogue with task-specific filters | Common report page and individual report navigation | Partial | P1: searchable report launcher/saved views if report count grows |
| Excel export | Arrival, ledger and day-book Excel outputs | Umrah reports currently expose PDF, not spreadsheet export | Missing | P1: safe XLSX/CSV export from the same validated report dataset |
| Party ledger/aging | Ledger, AR/AP variants and currency views | Agent statement, receivable/vendor aging, advances and core ledger accounting | Present/stronger calculation | Add invoice/source grouping and drill-through, not duplicate ledgers |
| Day Book | Receipts/payments plus cash/bank positions by day/branch | Payment register and cash/bank accounting exist; no equivalent Umrah-facing daily cash workspace was found | Partial | P1 for owner/accountant; keep it out of the operations clerk's home |
| Opening balances/financial period | Company financial period and opening-list entry | Shared accounting contracts cover opening/accounting concerns outside Umrah | Shared concern | Confirm existing Accounting UX before adding anything Umrah-specific |
| Universal search | Search by voucher reference, passport or passenger | No equivalent module-wide search was found in Umrah navigation/pages | Missing | P1: privacy-aware global Umrah search with role scoping |
| Complaints/passport delivery/intimation | Visible operational queues | Not first-class workflows | Missing, not yet validated | P2: interview operators before creating tables |
| Dashboard | Balances, voucher status, bookings/PAX and planned KSA/city counts | Role-aware Umrah dashboard plus Operations; owners and agents see scoped summaries | Partial/strong direction | Keep owner home summary-level; Operations is the clerk/dispatcher landing page |
| SMS | Import can send SMS | No automatic external messaging in this scope | Deliberately absent | Separate consent/template/audit project; do not bundle into import |

## The central product lesson

The tutorial's best pattern is the dependency chain:

`rate/package setup -> quick booking/import -> service voucher -> daily operations -> automatic accounting`

Haasib currently has strong service-voucher, operations, and accounting segments. Its weakest link is the reusable commercial setup and fast booking experience before the voucher. That is why merely adding more reports would still leave the product feeling accounting-first.

## Patterns not to copy

- Default shared passwords or user-managed password resets from a customer record
- Broad backdating/custom-cost permissions without explicit audit reasons
- Manual ledger-account selection on operational screens
- A separate report for every small permutation when filters/saved views answer the same question
- Direct import actions that create records/post money before a validation preview
- `Allow Duplicate` as an easy escape hatch for passport conflicts
- Raw custom sale/cost overrides exposed to agents
- Separate accounting invoices merely to show multiple currencies
- A giant setup tree as the everyday clerk experience
