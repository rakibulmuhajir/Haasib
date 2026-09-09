# Proposed data and service model

## Phase 1 principle: project, do not duplicate

Build a read-only operational timeline from existing canonical records:

| Operational fact | Current source |
|---|---|
| Outbound flight and Saudi arrival | `umrah.vouchers` onward flight fields |
| Return flight and Saudi departure | `umrah.vouchers` return flight fields |
| Passenger manifest | `umrah.voucher_passengers` and `umrah.passengers` |
| Hotel check-in/out and planned occupancy | `umrah.vouchers.hotel_stays` |
| Transport schedule and route | `umrah.group_transport_items` and `umrah.transport_sectors` |
| Vehicle, driver, capacity and provider | transport item relationships |
| Agent and group | voucher/group relationships |

Do not create a second editable itinerary table in Phase 1.

## Operational timeline projection

Introduce an application service such as `OperationalEventTimelineService` that emits a stable DTO. “Operations” is the product term; the longer backend name avoids confusion with Laravel events.

```text
event_key
event_type
scheduled_at
local_timezone
date_bucket
origin
destination
city
airport
group_id / group_number
voucher_id / voucher_number
agent_id / agent_name
head_passenger
passenger_count
passengers[]
hotel
room_type / room_count
transport_provider
vehicle
driver / driver_phone
readiness
readiness_issues[]
source_type / source_id
```

`event_key` should be deterministic so the same source event is stable across dashboard and reports.

`local_timezone` is derived from the event airport/city and is not an operator setting. It exists only so grouping and display use the same local clock.

## Projection rules

### Airport arrival

- Source only active, approved, non-superseded vouchers.
- Event time is `onward_arrival_at`.
- Airport is `onward_arrival_city` when it is a Saudi arrival airport.
- Origin is `onward_departure_city`.
- Destination is the first hotel city or the first matching transport sector destination.

### Airport departure

- Event time is `return_departure_at`.
- Airport is `return_departure_city`.
- Origin context is the last hotel city or matching transport sector origin.

### Hotel check-in/check-out

- Each valid hotel stay produces two events.
- `coming_from` for a check-in is the outbound flight origin for the first stay, otherwise the previous stay's city.
- `going_to` for a checkout is the next stay's city, otherwise the return departure airport.
- Same-city consecutive stays are hotel transfers, not inter-city movement.

### Inter-city movement

- Primary source is a scheduled transport item whose sector origin and destination normalize to different Saudi cities.
- Consecutive hotel stays in different cities create a required-movement expectation.
- If the expectation has no matching transport item and transport is included, mark it `needs_attention`; do not silently fabricate a dispatch.

### Planned city occupancy

- Count a passenger in a city for `check_in_date <= selected_date < check_out_date`.
- Exclude cancelled/superseded vouchers and deleted passenger pivots.
- Prevent passengers assigned to separated/amended vouchers from being counted twice.

## Readiness rules for Phase 1

Use deterministic states:

- `ready`: required flight/stay/transport fields exist and assignments are coherent.
- `needs_attention`: one or more actionable fields are missing or conflicting.
- `self_arranged`: the service is intentionally outside company responsibility.
- `cancelled`: excluded from active manifests but available in audit/control views.

Do not label a service `completed`, `arrived`, or `in Makkah` as an actual fact unless staff confirmation is stored. Use `planned` language.

## Local-time display rule

Follow the airline-ticket convention:

- A departure time is displayed in the departure place's local time.
- An arrival time is displayed in the arrival place's local time.
- Hotel and road-movement times use the local time of the city where the event occurs.
- `Today`, `Tonight`, and `Tomorrow` are calculated using that event-local time, not the office user's timezone.

There is no user-facing timezone preference or conversion control. The implementation still needs a small airport/city-to-IANA-timezone mapping so date buckets remain deterministic. Existing timestamp storage semantics must be verified once; that is an engineering concern, not an operator workflow.

## Role-shaped response

The same projection service can return different response detail according to a server-resolved Operations presentation profile:

- `summary`: aggregate movement counts only; intended for company owners.
- `operational`: manifest, passenger, flight, airport, hotel, transport, and readiness detail; intended for clerks/dispatchers.
- `agent_operational`: operational detail after agent self-scoping.
- `finance_linked`: event summaries plus permitted voucher/accounting references.

The backend must omit fields that the active profile or permission cannot expose. Frontend column visibility is presentation, not authorization.

## Candidate Phase 2 data additions

These are recommendations, not approved schema changes. Update `docs/contracts/umrah-schema.md` before any migration.

### Hotel stay confirmation metadata

Candidate fields inside the contracted hotel-stay snapshot, or normalized later:

- Confirmation status (`pending`, `requested`, `confirmed`, `rejected`, `cancelled`)
- Confirmation/reference number
- Room numbers
- Meal plan
- Hotel contact name/phone

### Transport execution metadata

Candidate additions to `umrah.group_transport_items`:

- Operational status (`scheduled`, `confirmed`, `dispatched`, `completed`, `cancelled`, `delayed`)
- Vehicle reference/registration
- Pickup instructions
- Dispatcher/coordinator contact
- Confirmed/dispatched/completed timestamps

Only fields demonstrated to be needed by real users should graduate into the contract.

## When to introduce a first-class event table

Do not add `umrah.itinerary_events` merely to make reports convenient. Consider it only if operators must:

- Manually add operational movements not represented by flights, hotels, or transport items
- Track actual versus scheduled times independently
- Reassign responsibility without changing commercial booking records
- Keep a durable event-level status and audit history

If those needs are validated, the event table should reference its source record and avoid duplicating commercial price/accounting fields.
