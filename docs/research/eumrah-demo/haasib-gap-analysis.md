# Haasib gap analysis

## Current strengths

Haasib already has foundations the competitor demo does not establish:

- Company-scoped routes, tenant isolation, RLS, UUIDs, RBAC, and audit-oriented accounting
- Agent, visa vendor, transport provider, driver, hotel, and hotel vendor records
- Visa groups and passenger/passport records
- Approved/draft/cancelled and amendment/supersession controls for vouchers
- Outbound and return flight fields on vouchers
- Repeating hotel stays with city, dates, room type/count, source, and price snapshots
- Configurable transport sectors and scheduled group transport items
- Ticketing, refunds, payments, allocations, supplier accounting, and multi-currency controls
- Existing PDF reports and verified QA scenarios

The right move is to expose this data through a unified **Operations** timeline, not rebuild the accounting engine.

## Current reporting surface

`TravelReportService` currently defines:

- Group Profitability
- Agent Statement
- Receivable Aging
- Vendor Payable Aging
- Advances and Allocations
- Passenger and Visa Status
- Departure Manifest
- Hotel Rooming List
- Transport Dispatch
- Voucher Control
- Ticket Sales
- Ticket Supplier Reconciliation
- Ticket Cancellations

Only four are primarily operational, and none answers airport arrivals or daily city movement directly.

## Gap matrix

| Operator need | Competitor | Haasib today | Gap | Priority |
|---|---|---|---|---|
| Tonight/tomorrow arrivals | Passenger-level arrival manifest | No arrival report | Missing | P0 |
| Airport return departures | Dedicated departure report | “Departure Manifest” uses onward departure, normally origin-country departure | Semantically mismatched | P0 |
| Makkah/Madinah check-ins | Separate city check-in report with “coming from” | Hotel Rooming List is keyed by check-in but is rooming-oriented | Partial | P0 |
| Hotel check-outs | Dedicated report | No dedicated checkout projection | Missing | P0 |
| Makkah <-> Madinah movement | Inter-city trip report | Generic Transport Dispatch by scheduled transport row | Partial | P0 |
| Current planned population by city | Daily KSA status counts | No occupancy snapshot | Missing | P0 |
| Flight/transport/hotel readiness | Pending voucher gate plus operational reports | Voucher Control plus transport assigned/paid widget | Finance/cutoff biased | P0 |
| Hotel confirmation control | HCN/status visible | Hotel stays have no structured confirmation state/reference | Missing structured data | P1 |
| Vehicle/dispatcher execution state | Transport context shown; completion not proven | Driver, vehicle/service, sector and schedule exist; no operational lifecycle | Missing | P1 |
| Report filtering | Date, agent, airport/city, flight, hotel, transport provider, Saudi company | Common report UI exists; filter definitions vary and are narrower | Partial | P0 |
| Printable daily manifests | PDF/print buttons | Generic report PDF infrastructure exists | Mostly present | P0 |
| Ziyarat operations | Repeating voucher rows | No first-class ziyarat itinerary model in the current contract | Missing | P2 |

## Why the current widgets feel accounting-first

### “Most urgent groups”

The current `DeparturesWidget` ranks future groups and then past groups that still owe money. That is a sensible collections widget, but it is not a dispatch/arrival tool. A group that flew yesterday and owes money is financially urgent, not operationally departing.

### “Transport for upcoming groups”

The current `TransportReadinessWidget` reports whether a vendor is assigned and whether money has been paid. It does not answer whether the pickup time, route, passenger load, vehicle, driver, or hotel handoff is ready.

Both widgets are useful; they are simply mislabeled or misplaced for an operations-first dashboard.

## Important engineering finding

Most P0 reports can be built without a migration:

- Arrival: `Voucher.onward_arrival_at` and `onward_arrival_city`
- Return departure: `Voucher.return_departure_at` and `return_departure_city`
- Passenger manifest: `Voucher.passengers`
- Hotel check-in/out and occupancy: `Voucher.hotel_stays`
- Inter-city movements: `GroupTransportItem.scheduled_at` plus `TransportSector.origin/destination`
- Driver/vehicle/provider readiness: existing transport item relationships

Only structured confirmation/actual execution state requires new data. Per project rules, those additions must first be written into `docs/contracts/umrah-schema.md`.

## Risks to resolve

- Voucher hotel stays are JSON snapshots, so database-side filtering and indexing are less straightforward than normalized rows.
- Transport is attached to a visa group while vouchers can split passengers. A dispatch report must avoid claiming every group passenger is on every voucher/vehicle.
- Multiple active vouchers, amendments, cancellations, and separated passengers require strict de-duplication.
- Datetime storage semantics must be checked once so “tonight” can use the event location's local clock, matching airline itinerary display.
- “Current city” is a planned location derived from hotel dates, not proof of physical presence.
