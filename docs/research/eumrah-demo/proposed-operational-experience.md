# Proposed Umrah Operations experience

## Product-language decision

Use **Operations** as the user-facing umbrella. Do not make Arrivals the module name because arrivals are only one event type. Keep precise report/view names underneath it:

- Arrivals
- Departures
- Hotel check-ins
- Hotel check-outs
- City transfers
- Transport pickups
- Ziyarat

In backend code, prefer a specific name such as `OperationalEventTimelineService` so it is not confused with Laravel application/domain events.

## Role-shaped navigation and presentation

Recommended order for operational roles:

1. Operations
2. Groups
3. Vouchers
4. Passengers & visas
5. Tickets
6. Agents
7. Payments
8. Reports
9. Settings

Accounting remains integrated and available, but should not be the first mental model presented to an Umrah operator.

Operations is not the company owner's home page. The owner can open Operations from navigation, but receives an executive movement summary rather than a passenger manifest. Role configuration selects the presentation; it is still one Operations feature and one set of underlying projections.

| Presentation profile | Default landing | Operations content |
|---|---|---|
| Company owner | Existing owner/business dashboard | Totals moving in, moving out, and between cities; no passenger, ticket, airport, or hotel detail by default |
| Operations clerk/dispatcher | Operations | Full flight, airport, hotel, passenger, transport, and readiness detail |
| Agent user | Existing agent landing or Operations as configured | Operational detail limited to the agent's own records |
| Accountant | Existing finance dashboard | Summary events with links to permitted voucher/accounting references |

The server must shape the payload for the selected profile. Hiding columns in Vue is not sufficient for passenger identity or supplier-sensitive data. Configuration may choose a default profile per role while explicit permissions remain the security boundary.

## Operations home

### Date navigator

At the top:

```text
[Today] [Tonight] [Tomorrow] [Next 7 days] [Choose date]
```

Times follow the local time of the event location, like an airline itinerary. Departure uses the departure location's local time; arrival uses the arrival location's local time; hotel and road movements use the local time of that city. `Today`, `Tonight`, and `Tomorrow` use that same event-local clock. No user-facing timezone switcher is needed.

### Daily summary

Clickable counts:

- Airport arrivals
- Airport departures
- Makkah check-ins
- Makkah check-outs
- Madinah check-ins
- Madinah check-outs
- Makkah -> Madinah
- Madinah -> Makkah
- Planned in Makkah
- Planned in Madinah

Labels must clearly say **planned** until actual completion tracking exists.

For the owner presentation, reduce this to aggregate movement cards such as `Moving in`, `Moving out`, `Makkah -> Madinah`, and `Madinah -> Makkah`. Do not render passenger rows, ticket details, airports, or hotels in that profile.

### Needs attention

Operational exceptions should appear before financial balances:

- Arrival has no transport assignment
- Transport included but no driver/vehicle
- Passenger count exceeds vehicle capacity
- Approved voucher lacks required flight or stay data
- Hotel stay has no confirmation reference (after P1 data exists)
- Inter-city hotel transition has no matching transport sector
- Duplicate/conflicting itinerary assignment

### Timeline sections

Group rows by event and time:

```text
Tonight arrivals
  22:35  SV-726  JED  14 pax  -> Makkah
          Bus assigned | Driver missing | Voucher UVR-0042

Tomorrow city movements
  08:00  Makkah -> Madinah  27 pax
          1 bus | Driver Ali | Ready
```

## Report specifications

### Airport Arrivals

Default grouping: arrival airport -> flight -> arrival time.

Core columns:

- Arrival time/date
- Airport
- Airline/flight
- Origin
- Agent
- Voucher/group
- Head passenger
- Pax
- Passenger names/passports on expansion
- First hotel/city
- Transport provider
- Vehicle/driver
- Readiness

### Airport Departures

Use return departure fields, not outward departure from the passenger's home country.

Core columns:

- Pickup time where known
- Departure time/date
- Airport
- Airline/flight
- Origin hotel/city
- Agent, voucher/group, head passenger, pax
- Transport provider, vehicle, driver, readiness

### Hotel Check-ins and Check-outs

Default grouping: city -> hotel -> date.

Core columns:

- Date
- City/hotel
- Coming from / going to
- Voucher/group/agent
- Head passenger and pax
- Room type/count
- Nights
- Source (company/self-arranged)
- Confirmation status/reference when available

### Inter-city Movements

Default grouping: route -> schedule.

Core columns:

- Scheduled pickup
- Origin and destination
- Group/voucher
- Pax and capacity
- Transport provider
- Vehicle/service
- Driver/contact
- Pickup notes
- Readiness/status

### City Occupancy

For a selected date, show planned passengers and room requirements in each city. A pilgrim belongs to a city when the date is on or after hotel check-in and before hotel checkout. Same-day transfer handling must be explicit to prevent double counting.

## Filters

All operational views should share:

- Date/date range and quick presets
- Agent/client
- Airport or city
- Airline/flight
- Hotel
- Transport provider
- Saudi visa/service provider where useful
- Readiness/status

Applied filters should remain in the URL so the screen can be bookmarked or shared internally. A single event-type filter lets the same screen act as “All events,” “Arrivals,” “Departures,” or “City transfers” without multiplying navigation items.

## Interaction details

- Clicking a summary count opens the corresponding filtered manifest.
- Rows expand for passenger names and passports; the default table stays scannable.
- Voucher/group links preserve the return URL.
- PDF is optimized for dispatch/airport/hotel handoff.
- CSV can follow after the report definitions stabilize.
- Empty states explain whether there is no travel or missing itinerary data.
- Owner presentation uses direct aggregate language such as “42 moving in” and “31 moving out.” Clerk presentation exposes the manifest and readiness details. Accountant presentation may expose references and financial links, but operational vocabulary should not become ledger jargon.

## Mobile priority

Airport and city staff need a compact mobile list more than a miniature desktop dashboard:

- Time, flight/route, pax, and readiness visible without horizontal scrolling
- Tap to call driver/provider
- Tap to open voucher/passenger list
- Offline/export enhancements are later scope, not assumed from the competitor demo
