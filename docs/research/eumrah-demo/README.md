# eUmrah CRM/ERP demo research

Research date: 2026-09-05  
Source: [eTravels CRM Demo / eUmrah CRM Demo](https://www.youtube.com/watch?v=FEx3twvP0y8) (30:02, Urdu)  
Inputs: the supplied Urdu transcript plus direct inspection of the recorded screens.

## Executive conclusion

Haasib is not primarily missing accounting capability. It is missing an **operational projection** over travel data it already records.

The competitor's interface is visually dated and accounting-heavy, but it answers the morning questions of an Umrah operator much better:

- Who is arriving today, tonight, or tomorrow?
- At which airport, on which flight, and at what time?
- Who must be picked up, by which transport provider and vehicle?
- Who is checking into or out of Makkah and Madinah?
- Who is moving from one city to another?
- How many pilgrims are currently planned to be in each city?
- Which vouchers or service arrangements are incomplete?

The best lesson is not its orange tables or menu forest. It is the way one itinerary feeds a family of date-driven operational reports.

## Most important findings

1. **Date is the primary operational entry point.** The competitor exposes a daily KSA status panel and reports that default to a day or short date range.
2. **Arrival, departure, hotel movement, and inter-city movement are separate operational questions.** A generic departure list is not enough.
3. **“Coming from” is a first-class field in the presentation.** A Makkah check-in distinguishes a pilgrim arriving from Pakistan from one arriving from Madinah.
4. **Reports are actionable manifests, not executive summaries.** They include passenger/passport, flight, airport, hotel, room type, checkout date, transport provider, and Saudi service company.
5. **Voucher approval is an operational readiness gate.** Incomplete vouchers remain pending; only complete vouchers can be approved.
6. **The voucher is the itinerary packet.** Flights, any number of hotel stays, sector-based transport, ziyarat, contacts, and passenger identity are assembled once and reused.
7. **Accounting is generated from operations, but operators do not need to begin in accounting.** Haasib should retain its stronger ledger while moving financial detail out of the operator's main path.

## Recommendation

Make **Operations** the default Umrah landing experience for operational roles such as clerks and dispatch staff. It remains available to company owners, but their home page and Operations view should lead with aggregate movement totals rather than passenger manifests. An event is anything scheduled to happen to or for a pilgrim: airport arrival, airport departure, hotel check-in/out, city transfer, transport pickup, or ziyarat. Build the first version as projections over existing approved, non-superseded vouchers, voucher passengers, hotel stays, and group transport items. That provides a unified timeline without an immediate migration.

Display every scheduled time in the local time of the place where that leg happens, as airline itineraries do. Do not make office/user timezone selection part of the workflow.

Add operational status fields only after the read-only projections have been validated with real operators. This avoids solving a workflow we have not yet observed by inventing a miniature airline control tower on day one.

## Research artifacts

- [Source, method, and evidence](source-and-method.md)
- [Cleaned transcript notes](transcript-notes.md)
- [Competitor screen inventory](screen-inventory.md)
- [Observed workflows](workflow-analysis.md)
- [Accounting, reporting, and voucher findings](accounting-reporting-voucher-findings.md)
- [Haasib gap analysis](haasib-gap-analysis.md)
- [Proposed operational experience](proposed-operational-experience.md)
- [Proposed data and service model](proposed-domain-model.md)
- [Phased implementation roadmap](implementation-roadmap.md)

## Confidence

The screen and report findings are high confidence because they were verified visually. Exact labels such as `Shirka`, `HCN`, `BRN`, and `SPO` are retained cautiously because the narration and recording do not define every abbreviation. Items described as recommendations are explicitly separated from features observed in the competitor.
