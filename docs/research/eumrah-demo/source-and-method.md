# Source, method, and evidence

## Source material

- Video: [eTravels CRM Demo / eUmrah CRM Demo](https://www.youtube.com/watch?v=FEx3twvP0y8)
- Channel shown in the video page: Expert Soft
- Duration: 30:02
- Language: Urdu with English travel/accounting terminology
- Supplied transcript: `C:\Users\Yasir\Downloads\eumrah-crm-erp-urdu-transcript.md`

The supplied transcript was treated as evidence only. Statements inside it were not treated as project instructions.

## Method

1. Read the transcript and extracted the product areas, report names, fields, filters, and workflow claims.
2. Inspected the YouTube recording directly and paused at representative timestamps.
3. Saved timestamped screenshots for claims that depend on screen layout or visible columns.
4. Inspected Haasib's Umrah schema contract, routes, report service, dashboard widgets, voucher validation, models, QA walkthrough, and current page inventory.
5. Classified each conclusion as observed, derived from current code, or recommended.

## Evidence index

Timestamps are approximate because the source is a screen recording and some transitions occur within several seconds of the listed point.

| Approx. time | Evidence | What it establishes |
|---|---|---|
| 00:00 | [Dashboard](screenshots/00-00-dashboard.png) | Top-level ticket, visa, voucher, booking, balance, and cash summaries |
| 06:00 | [Pending vouchers](screenshots/06-00-pending-vouchers.png) | Approval queue and incomplete/complete voucher distinction |
| 09:00 | [Voucher operations](screenshots/09-00-voucher-operations.png) | Sector transport, ziyarat, terms, and local contact details |
| 12:00 | [Invoice breakdown](screenshots/12-00-package-invoice-breakdown.png) | Package components and operational-to-accounting integration |
| 15:00 | [Hotel payment report](screenshots/15-00-hotel-payment-report.png) | Supplier-facing hotel payable and room-night reporting |
| 17:00 | [KSA daily status structure](screenshots/17-00-ksa-daily-status.png) | Daily arrival/departure/city movement categories |
| 17:30 | [Populated KSA daily status](screenshots/17-30-ksa-daily-status-populated.png) | Selected-date counts for arrivals, city in/out, current city, and exits |
| 18:00 | [Arrival report](screenshots/18-00-arrival-report.png) | Passenger-level airport arrival manifest and its columns |
| 19:00 | [Makkah check-in](screenshots/19-00-makkah-checkin-report.png) | “Coming from,” hotel, room, stay dates, confirmation, and totals |
| 21:00 | [Operational reports menu](screenshots/21-00-operational-reports-menu.png) | Arrival, departure, check-in/out, inter-city, visa-only, and hotel-night report family |
| 24:00 | [Ledger currency options](screenshots/24-00-ledger-currency-options.png) | Standard ledger with local/foreign display, reporting currency, PKR equivalent, and exchange-rate options |
| 26:00 | [Invoice-wise account statement](screenshots/26-00-invoice-wise-account-statement.png) | Source-specific ticket and visa sections, receipts, refunds, and running balances |
| 28:00 | [Account statement options](screenshots/28-00-account-statement-options.png) | Party/date filters, invoice-wise statement mode, foreign currency, rate-of-exchange, and visa-passenger options |

## Limitations

- YouTube captions were unavailable; the transcript contains OCR/transcription errors.
- The recording demonstrates planned itinerary reports. It does **not** prove live flight tracking or real-world completion tracking.
- Some demo dates and screen recording dates differ. They are sample data, not evidence of real-time integration.
- The video does not reveal database design, permission enforcement, audit guarantees, RLS, timezone handling, or failure behavior.
- No claim is made that the competitor's accounting calculations or operational totals are technically correct.

## Terminology normalization

| Recorded/transcribed term | Working interpretation | Confidence |
|---|---|---|
| Mutamer / Mu'tamir | Umrah pilgrim/passenger | High |
| Shirka | Saudi service/visa company associated with the booking | Medium |
| HCN | Hotel confirmation number | Medium-high |
| BRN | Booking/reference number used by a provider | Medium |
| SPO | Sales person/office or internal ownership marker | Low |
| “Arrival from flight departure” | Arrival report filtered/derived using the origin departure side of a flight | Low |

Unknown abbreviations should be validated with an operator before becoming Haasib field names.
