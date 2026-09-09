# Phased implementation roadmap

This roadmap deliberately starts with useful read-only operations over existing data. Schema changes come only after workflow validation.

## Phase 0: decisions and test fixtures

Priority: P0

- Adopt the airline-style local-time rule: each departure, arrival, hotel event, or road movement displays and buckets in the local time of its location.
- Confirm whether “arrival” means scheduled landing in Saudi Arabia and “departure” means the return flight leaving Saudi Arabia.
- Configure Operations presentation profiles for owner summary, clerk/dispatcher detail, agent-scoped detail, and accountant-linked summary.
- Create fixtures covering JED arrival, MED arrival, Makkah-first, Madinah-first, Makkah -> Madinah, Madinah -> Makkah, hotel-only, self-arranged transport, split vouchers, amendments, and cancellation.
- Add the operational terminology to the relevant experience/specification document before UI implementation.

## Phase 1: operational projections and reports

Priority: P0

### Backend

- Add `OperationalEventTimelineService` under `build/modules/Umrah/Services/`.
- Keep it read-only and company-scoped through `CurrentCompany` and `TravelAccessService`.
- Exclude cancelled and superseded vouchers and deleted passenger assignments.
- Add report builders for:
  - Airport Arrivals
  - Airport Departures
  - Hotel Check-ins
  - Hotel Check-outs
  - Inter-city Movements
  - Planned City Occupancy
- Add shared filters for agent, airport/city, airline/flight, hotel, transport provider, and readiness.
- Reuse the existing generic report page and PDF rendering where it remains readable.

### Routes and authorization

- Keep every route under `/{company}/umrah` with `auth`, `identify.company`, and `require.module:umrah`.
- Add explicit `UMRAH_OPERATIONS_VIEW` if existing report permissions are too broad.
- Preserve agent self-scoping and prevent supplier cost/profit leakage.
- Resolve the Operations presentation profile on the server and omit passenger/itinerary detail from summary payloads; do not rely on hidden frontend columns.
- Register permissions in `App\Constants\Permissions`, sync them, and update `config/role-permissions.php`.

### Frontend

- Add an Operations page using `<script setup lang="ts">`, Inertia navigation, Shadcn/Vue components, and shared Haasib table/status primitives.
- Use one event-type filter for all events, arrivals, departures, hotel movements, transfers, pickups, and later ziyarat.
- Add Today, Tonight, Tomorrow, Next 7 Days, and custom date controls.
- Make summary counts link to filtered manifests.
- Show readiness issues in plain language and preserve filter state in the URL.
- Use current Haasib layout, table, filter, status, and role/mode conventions rather than introducing a separate dashboard design language.
- Give owners aggregate `Moving in`, `Moving out`, and inter-city totals; give clerks the full operational manifest.
- Make reports printable and usable on a small laptop and mobile device.

### Verification

- Feature tests for tenant isolation and agent scoping.
- Unit tests for every projection rule and date boundary.
- Regression tests for split/amended/cancelled vouchers.
- Tests preventing double-counted passengers and events.
- PDF rendering checks for wide arrival/check-in reports.
- Event-local date-boundary tests for departure, arrival, hotel, and transfer locations.

## Phase 2: true readiness and confirmation controls

Priority: P1

- Interview/test with at least one office operator and one Saudi operations user using Phase 1 reports.
- Update `docs/contracts/umrah-schema.md` with only validated new fields.
- Add hotel confirmation reference/status if operators actively maintain it.
- Add transport vehicle reference, pickup instructions, operational status, and timestamps if dispatchers maintain them.
- Add FormRequests, permissions, command/service actions, audit entries, and full success/error/loading UI cycles.
- Replace the current finance-biased transport readiness widget or move it to a finance section; introduce an operational readiness widget based on assignment completeness.

## Phase 3: workflow acceleration

Priority: P1/P2

- Bulk assign driver/vehicle/provider to selected movements.
- Print/export one flight manifest, one hotel handoff, or one route dispatch sheet.
- Add internal share links with preserved date/filter state.
- Add staff contact actions on mobile.
- Add saved views such as “Tonight at JED” or “Tomorrow Makkah -> Madinah.”

Notifications or WhatsApp distribution should be a separate approved project because they create external communications and require recipient/privacy rules.

## Phase 4: actual movement tracking, only if validated

Priority: P2

- Add scheduled-versus-actual event tracking.
- Record en-route, completed, delayed, cancelled, and no-show outcomes.
- Consider a first-class itinerary event table only when source projections are no longer sufficient.
- Preserve an immutable operational audit trail and never rewrite accounting history when only an operational status changes.

## Suggested delivery slices

1. Airport Arrivals + Tonight/Tomorrow dashboard
2. Hotel Check-ins/outs + planned Makkah/Madinah occupancy
3. Inter-city movement + real operational readiness
4. Airport Departures + printable handoff packs
5. Confirmations/status updates based on operator feedback

## Acceptance scenarios for the first slice

- A clerk opens Operations and immediately sees tonight's JED and MED landings in each event location's local time.
- Clicking JED arrivals shows flights grouped by time with passenger totals.
- Expanding a row shows the exact passengers and passports without duplicating separated/amended vouchers.
- Each arrival shows first hotel, transport provider, vehicle/driver, and explicit missing-data warnings.
- An agent login sees only its own passengers and no supplier costs.
- A cancelled or superseded voucher never appears in an active manifest.
- PDF output fits the page and contains the same totals as the screen.
- Office location does not alter an event's displayed local time or operational date bucket.
- An owner can open Operations and sees movement totals without passenger, ticket, airport, or hotel detail.
- Operations is the clerk/dispatcher landing experience, not the owner's home page.

## Deferred deliberately

- Live airline data
- GPS/driver tracking
- Automated WhatsApp messages
- Full hotel inventory/allotment engine
- Normalizing every hotel stay into a new table
- Copying the competitor's broad accounting menus

These may become valuable, but none is required to solve the immediate “who is arriving or moving next?” problem.
