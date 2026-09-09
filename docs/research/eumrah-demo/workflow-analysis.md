# Observed workflow analysis

## Operating model

The demonstrated system follows this broad sequence:

```text
Agent/client
  -> passport and visa processing
  -> voucher preparation
  -> admin readiness/approval
  -> flights + hotels + transport + ziyarat
  -> daily KSA operations and manifests
  -> invoice, receivable, supplier payable, and ledger
```

The critical product behavior is that itinerary data is reused. The user does not re-enter an arrival separately merely to produce an arrival report.

## Primary actors

| Actor | Observed responsibility |
|---|---|
| Office admin | Sets up bookings, approves vouchers, reviews global reports and accounts |
| B2B travel agent | Supplies passengers and can receive branded vouchers/statements |
| Makkah/Madinah staff | Uses geographically relevant operational information |
| Transport provider/dispatcher | Appears in arrival and movement reports |
| Hotel supplier | Appears in reservation/payment reports |
| Accounts staff | Uses invoice, ledger, payment, aging, and payable reports |

The video mentions configurable rights, but it does not prove how permissions are enforced. Haasib's existing RBAC and RLS remain the stronger foundation.

## Core workflow 1: passport to approved voucher

1. Visa/passenger data is entered manually or imported from MOFA.
2. Passengers are associated with an agent/client and group.
3. A voucher assembles the trip services.
4. An incomplete voucher remains pending/partial.
5. An administrator reviews and approves a complete voucher.
6. Approved data becomes available to print, operational reports, and invoicing.

Product lesson: approval should mean **operationally usable**, not merely financially posted.

## Core workflow 2: daily airport arrival

1. Operator chooses a date or short period.
2. Optionally filters by client, airport, Saudi company, transport provider, or flight.
3. System groups passengers/vouchers by landing details.
4. Report exposes first accommodation and transport responsibility.
5. Operator prints or exports the manifest.

The demo shows scheduled itinerary data. It does not show delay feeds, actual landing times, or dispatcher completion.

## Core workflow 3: city and hotel movement

1. Operator chooses Makkah or Madinah and a date range.
2. System lists check-ins or check-outs.
3. Each row explains where the passenger is coming from.
4. Hotel/room/confirmation detail supports reception and rooming work.
5. Daily totals support staffing, vehicles, and room planning.

“Coming from” is derived from the journey sequence:

- Origin country/airport -> first Saudi hotel
- Previous hotel city -> next hotel city
- Current hotel -> departure airport

## Core workflow 4: inter-city transport

The voucher can carry a full route or individual sectors. Sector-level entry supports scheduled movement between Makkah, Madinah, Jeddah Airport, and Madinah Airport. The reports menu exposes an inter-city trip report, although the video does not demonstrate the complete output in detail.

## Core workflow 5: hotel supplier management

Hotel stays generate both operational room-night information and supplier payables. A hotel report can be filtered by hotel or supplier, and confirmation/reference fields are surfaced alongside payable amounts.

## What to adopt

- Daily date-driven operational home
- Separate manifests for arrival, departure, hotel in/out, and inter-city movement
- Click-through counts and preserved filters
- Passenger totals and grouping by flight/hotel/route
- “Coming from” journey context
- Completeness/readiness gate before approval
- One itinerary feeding both operations and accounting
- Printable operational sheets

## What not to copy

- Accounting as the dominant navigation model
- Large permanent sidebar plus multiple unrelated top menus
- Dense, tiny typography and weak responsive behavior
- Dozens of open browser tabs as a normal workflow
- Ambiguous one-letter booking statuses without plain-language labels
- Reports that expose every possible column without a task-specific default
- Reliance on color alone to convey status
