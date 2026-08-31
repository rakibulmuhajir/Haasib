# Umrah walkthrough QA results — 2026-08-29

Environment: local Laravel server at `127.0.0.1:9001`, Vite at `127.0.0.1:5174`.
Test company: **Rihla Travel QA 2** (`rihla-travel-qa-2`), SAR base currency and PKR secondary currency at 0.0125.

## Recording verdict

**Not recording-ready yet.** Core group, ticket, payment, report, and agent-isolation flows work, but the walkthrough is materially stale and the voucher/agent views contain blockers that would be visible in a recording.

## Passed

- Travel-company onboarding and Umrah module activation.
- Agent creation and linked accounting customer creation.
- Visa, transport, hotel, and ticket supplier setup.
- Drivers, vehicles, sectors, journey, specialized fares, and hotels.
- Group A totals: 3,600 sale / 3,000 cost / 600 profit.
- Group B totals: 6,740 sale / 5,530 cost / 1,210 profit. Own-fleet toggle also recalculated correctly.
- Group C totals: 8,460 sale / 6,770 cost / 1,690 profit.
- Group D initial totals and quantity edit: 6,000/4,800 became 8,400/6,600 as expected.
- Spreadsheet imports append correctly and duplicate import does not add rows.
- Ticket agent selection auto-links and locks the buyer; clearing the agent empties and unlocks the buyer.
- Two-ticket booking posted 3,980 buyer invoice and 3,200 supplier bill.
- Ticket 2 cancellation posted buyer return 1,500 and supplier return 1,400.
- Agent receipt of 10,000 allocated 3,600 / 6,000 / 400 correctly.
- Full-year Group Profitability report returns all four groups with expected totals.
- Agent login only offers the five self-service reports and hides cost/margin amounts.

## Recording blockers / defects

1. **Agent payment status is wrong.** Group C has only 400 paid against 8,460, but the agent detail labels it **Paid**. Receivable and balance are hidden as dashes, making the screen contradictory.
2. **Voucher hotel amounts disappear after save.** The create form preview calculated 21,600 charge / 17,000 cost for the selected stays, but the saved draft displayed 0.00 charge and 0.00 cost per company-supplied stay.
3. **Voucher flight date-time controls are difficult to complete reliably.** Typing values opens stacked calendar popovers; saved flight dates remained unset. Approval correctly refused the incomplete itinerary, so the approval/accounting/PDF lifecycle could not be completed.
4. **Company deletion is incomplete.** Removing a company from `/companies` only removes/deactivates membership. The company remains active and its name stays reserved; the first test company became an unreachable zombie record.
5. **Company creation can fail silently.** Selecting no secondary currency leaves a hidden empty exchange-rate value that fails backend numeric validation without a visible field error.

## Walkthrough mismatches / UX defects

- The documented Octane command is unavailable in this checkout; `php artisan serve` works.
- The documented frontend port 5180 did not match the active Vite port (5174), and the tested app was served at 9001.
- Creating from the inline `/companies` form auto-selected PKR and skipped the base-currency choice. Use `/companies/create` for the recording.
- Umrah is auto-enabled for a travel company; there is no Umrah toggle under Settings > Modules.
- The documented agent username `sahil-agent` fails validation; `sahil_agent` works.
- Passenger entry uses numeric age, not date of birth.
- Several transport sectors are pre-seeded; creating all three from the walkthrough causes duplicates.
- A fare has one coverage selector, so the “select a sector and a journey simultaneously” negative test is impossible in this UI.
- Duplicate spreadsheet import leaves passenger count unchanged but still shows the misleading toast “3 mutamers imported.”
- Group C’s per-passenger Hiace schedule displays `Vehicles / Pax 2 / 6` after quantity 1 was entered.
- The specialized-capacity hint says “Booking fewer seats than passengers raises the vehicle count on save” even when 44 seats are available for 4 passengers.
- Group Accounting shows rolled-up figures but no visible journal-entry history, so the walkthrough cannot visually confirm original postings plus differential adjustment entries there.
- `/umrah/reports` is a 404. Reports are individual routes opened from the Reports menu.
- The Reports menu contains ten operational reports, not the thirteen listed; ticket sales, supplier reconciliation, and ticket cancellations are absent from that menu.
- The voucher form has no single Bundle selector. Service selection is passenger-based, and company/self hotel sourcing is configured per stay.
- The voucher form creates three itinerary stay rows by default; the unused third self-arranged row persisted with a partial date.

## Test data left in place

- Groups UGR-00001 through UGR-00004.
- Voucher UVR-00001 remains a draft because its itinerary could not be completed reliably.
- Ticket booking TB-00001; its second ticket is cancelled.
- Payment UPM-00001 for 10,000 SAR with three allocations.
- Agent login: `sahil_agent` / `qa-password`.

## Recommended pre-recording fixes

Fix the agent payment-status calculation and voucher persistence/date-time flow first. Then update the walkthrough routes, username, onboarding/module instructions, fare negative test, report inventory, and voucher terminology. Re-run voucher approval/accounting/PDF and the remaining vendor-payment/refund/expense report coverage after those blockers are resolved.

## Follow-up verification — revision 57b367d8

- **PASS:** Agent Group C now displays `Partly paid` with 400.00 paid. Cost, receivable, and balance values remain hidden.
- **PASS:** Draft company-supplied stays display `Priced on approval` instead of misleading 0.00 amounts.
- **PASS after re-save:** UVR-00001 recalculated 3 nights for Dar Al-Eiman and 2 nights for Al-Haram. The retained pre-fix draft still showed zero nights until it was updated once; no automatic backfill occurred.
- **PARTIAL:** Typing a date-time and pressing Enter or Tab closes/commits without opening stacked calendar popovers.
- **FAIL / recording blocker:** Committing a value in another flight date-time field clears the previously committed field. Reproduced at human pace with 700 ms pauses: onward departure persisted after Tab, then disappeared when onward arrival was committed. Repeating across the four fields leaves only the most recently committed value. The itinerary therefore still cannot be completed and voucher approval/accounting/PDF remain blocked.

## Continued QA after 57b367d8

- **PASS:** Payment over-allocation is blocked in the UI; the submit button remains disabled when allocation exceeds the payment.
- **PASS:** A 9,000 SAR payment to Bab Al-Salam allocated across its group obligations and appears as UPM-00002.
- **PASS:** A 2,000 SAR Haramain payment posted as UPM-00003, then reversed. The original payment and allocations remain visible with `Reversed` status and the supplied reason.
- **PASS:** A 100,000 PKR expense at 0.0125 posted as 1,250 SAR, then reversed. The reversed record remains visible and posted-expense total returns to zero.
- **WALKTHROUGH MISMATCH / BLOCKER:** Refund creation has no group selector. The requested 500 SAR agent refund cannot be approved because the agent has no unallocated advance; the UI states that only excess/unallocated payments can be refunded. The walkthrough's group-linked 500 refund is therefore not executable after allocating the entire 10,000 receipt.
- **PASS:** Full-year Group Profitability, Agent Statement, Receivable Aging, Vendor Aging, Advances, Passenger Status, Hotel Rooming, and Voucher Control reports return data and expose PDF actions.
- **EXPECTED DATA GAP:** Departure Manifest returns no rows because the draft voucher still has no committed flight itinerary.
- **DATA/BEHAVIOR GAP:** Transport Dispatch returns no rows even though Groups B-D contain transport selections and drivers. The report appears to require scheduled transport records, while the walkthrough expects the group vehicle selections themselves to appear.
- **PASS:** Passenger Status reports all 21 passengers.

## Second pass — revision c1ba1ed7

- **PASS:** The rewritten walkthrough contains the corrected server command, company-creation route, `sahil_agent` username, 10,500 receipt/refund logic, transport scheduling requirement, report-dropdown routing, and previously identified UI caveats.
- **FAIL / recording blocker remains:** The four voucher date-time inputs still do not retain values together. After entering all four using alternating Enter and Tab commits, only the most recently committed value survives; earlier fields clear during later commits. No stacked calendar popovers appear, but the itinerary remains impossible to complete and approval remains blocked.
- **PASS:** After adding schedules to Group C's two transport rows and Group D's row, Transport Dispatch returns three rows representing five vehicles, 16 passengers, both drivers, and the expected terminals. This confirms the revised walkthrough's scheduling explanation.
- **PASS:** Added 500 SAR as an unallocated agent receipt (UPM-00004) to adapt the retained first-pass data to the corrected 10,500 scenario. URF-00001 then approved and settled successfully; its final status is `Refunded`.
- **TEST-DATA NOTE:** The retained company now has two incoming records (10,000 allocated plus 500 unallocated/refunded), rather than the corrected walkthrough's single 10,500 receipt. Functionally this exercises the same 10,000 allocation plus 500 refund balance, but the report presentation differs from a clean recording run.
- **PASS:** The Report dropdown exposes all thirteen reports. Ticket Sales shows two tickets and 3,780 gross fare; Ticket Supplier Reconciliation shows Skyline Ticketing and a zero clearing-control balance; Ticket Cancellations shows the 1,500 buyer return, 1,400 supplier return, and 100 net cost.

## Asset-controlled recheck — c1ba1ed7 built bundle

The flight-field failure reported in the two earlier sections was a **false app defect caused by stale Vite assets**. `public/hot` kept the Laravel page attached to an older dev-server bundle even though the repository was on c1ba1ed7. After testing with Vite stopped and the built assets served directly:

- **PASS / asset fingerprint:** Typing `01/11/2026 08:00` renders exactly day-first with no comma, confirming the fixed bundle. The stale asset rendered month-first with a comma.
- **PASS:** Alternating Enter and Tab across all four flight date-time inputs retains every value. All four also survive moving focus to another control, with no stacked calendar popovers.
- **PASS:** UVR-00001 saved with complete onward and return flights, two company hotel stays, and the unused third stay removed.
- **PASS:** Voucher approval priced hotels at 21,600 charge / 17,000 cost / 4,600 margin. Consolidated group position became 30,060 receivable, 29,660 balance, and 6,290 profit.
- **PASS:** Voucher PDF export triggered successfully.
- **PASS:** Departure Manifest now returns the three voucher passengers under flight SV101, with agent, route, passports, and transport populated.

**Final correction:** the date-time picker is not a recording blocker on c1ba1ed7. Recording or QA sessions must either restart Vite after changing/pulling frontend code or remove `public/hot` and use a fresh production build. The remaining known defects are the company-deletion zombie record and silent company-creation failure when secondary currency is untouched; neither blocks the Umrah recording.

## Remaining negative tests

- **PASS:** Removing the final vehicle from specialized Group D is refused. The edit page remains open, totals are unchanged, and the inline message explains that specialized transport requires a vehicle and self-arranged transport should be chosen to remove transport.
- **PASS:** Voucher passenger exclusivity is enforced in the create UI. UVR-00001's three passengers appear only under `Already Assigned`; only the other three can be selected. Clearing every passenger disables `Save Voucher`.
- **PASS:** Approved voucher lifecycle controls are status-safe: UVR-00001 exposes Amend and Cancel, but not Edit, Delete Draft, or Approve again.
- **PASS:** URF-00002 was rejected with a reason and remained non-posting. It does not appear in the full-year Agent Statement.
- **WALKTHROUGH/UI MISMATCH:** A requested refund has no pre-approval Cancel action, including when opened by the manager who created it. Only Reject and Approve are offered. URF-00003 remains Requested because the documented cancel path cannot be executed.
- **PASS:** For a past-dated group, the agent cannot edit through either the UI or a direct URL. The Edit Group action disappears and the direct edit route returns 403 with `This group cannot be modified by your agent login.` Group D's date was restored to Oct 2, 2026 afterward with an audit reason.
- **FAIL / authorization-cutoff defect:** The same agent can still create a voucher after the group's travel date and beyond the configured 24-hour cutoff. The UI offered Create Voucher and accepted an empty hotel-only draft; UVR-00002 was created for temporarily past-dated Group D with four passengers and no hotel stays. The server did not refuse it.
- **PASS with report caveat:** Rejected and merely requested refunds do not appear in Agent Statement. However, settled URF-00001 also has no explicit statement row; the 500 receipt UPM-00004 appears with zero remaining advance after settlement. This conflicts with the walkthrough expectation that the statement visibly lists the 500 refund.

## Full remaining QA — revision dbf09455

Environment was deliberately asset-controlled: Laravel at `127.0.0.1:9001`, no Vite process, no `public/hot`, and the production bundle from `dbf09455`.

### Fixed paths verified

- **PASS:** Agent refund services are party-aware. Agent refunds offer Hotel, Ticket, Transport, and Other but not Visa. Visa-vendor refunds still offer Visa.
- **PASS:** A new 50 SAR agent Ticket refund saves and reopens as service `ticket` rather than `other`.
- **PASS:** Full-year Agent Statement now has a separate 500 SAR `Refunded` column/row for URF-00001, naming `Refunded -- Visa`, without changing charge or receipt totals.
- **PASS:** The 24-hour voucher cutoff now uses the group travel date when the voucher has no itinerary. An agent was refused against a past-dated group with a visible inline message and toast; the group date was restored afterward.
- **PASS:** Group C remains `Partly paid` for the agent while receivable, balance, sale, and transport cost stay hidden.

### Group D adjustment and supplier reconciliation

- **PASS:** Adding one Hiace raised transport sale/cost by exactly 240/180; removing it reversed exactly 240/180.
- **PASS:** Switching the remaining two Coasters from Haramain to Own Fleet kept sale at 4,800, reduced cost from 3,600 to 3,000, and raised profit from 1,800 to 2,400.
- **PASS:** Haramain payable fell from 6,500 to 2,900 and Own Fleet payable rose from 0 to 3,000. Group D is intentionally left on two Own Fleet Coasters.

### Voucher lifecycle

- **BLOCKER — amendment 500:** `Amend` on approved UVR-00001 fails while copying its passengers. PostgreSQL rejects the duplicate `(company_id, visa_group_id, passenger_id)` rows under `umrah_voucher_passengers_company_id_visa_group_id_passenger_id_`. The dialog remains open and provides no durable explanation to the user. No amendment is created, so room-count differential accounting cannot be tested.
- **PASS:** UVR-00003 was created for Group C's other three passengers as a Visa + Transport draft with no hotel stays. Passenger exclusivity remained correct.
- **BLOCKER — no-hotel approval:** UVR-00003 cannot be approved as written. `ApproveVoucherRequest::hasCompleteItinerary()` requires `hotel_stays !== []` for every service bundle, including Visa + Transport. This contradicts both the bundle and walkthrough step 9.
- **PASS:** Separating one passenger from UVR-00003 created UVR-00004 and removed that passenger from the source; no passenger appeared twice.
- **PASS with workaround:** UVR-00004 approved after adding a temporary self-arranged stay, then cancelled with a reason. It remains visible as Cancelled and the other vouchers remain intact.
- **BLOCKED BY AMENDMENT:** Moving a passenger from voucher 2 into approved voucher 1 is unavailable. `Move Passengers` only has an eligible draft target; the amendment failure prevents voucher 1 from producing that draft target.
- **FAIL — time-zone inconsistency:** Typed flight times persist, but the voucher detail adds five hours while the PDF prints the entered time. For example, 13:00/17:00 saved on UVR-00004 displays as 18:00/22:00 on the detail page. UVR-00001 likewise displays 13:00 on screen while its PDF prints 08:00.

### Vendor payments and currency

- **PASS:** UPM-00005 paid Anwar Hospitality 1,500 SAR and auto-allocated it to UGR-00003; its payable fell by 1,500.
- **BLOCKER — Skyline unavailable:** The Umrah outgoing-payment vendor selector contains only the Umrah visa, transport, and hotel vendors. Accounting ticket supplier Skyline Ticketing is absent, so the documented 100,000 PKR payment to Skyline cannot be entered.
- **PASS (isolated conversion):** A 100,000 PKR payment against Anwar previewed and posted a base amount of exactly 1,250 SAR at 0.0125. QA payment UPM-00006 and its allocation were then reversed, restoring the vendor balance while retaining the audit trail.

### PDF and report verification

- **PASS:** Actual PDF bytes were generated through the same controllers for the payment receipt, Haramain supplier statement, and all thirteen reports for 2026. Every output is a valid, readable one-page PDF with aligned tables, no clipping, no broken glyphs, and correct page orientation.
- **PASS:** UVR-00001 PDF contains Sahil Travel Network, all three passenger names/passports, both hotels, 3/2 nights, both transport rows, and both flight legs.
- **PASS:** UPM-00001 receipt contains Sahil Travel Network and allocations of 3,600 / 6,000 / 400 to Groups A/B/C. Retained data uses a separate UPM-00004 for the refundable 500, so this receipt is 10,000 rather than the clean walkthrough's single 10,500 receipt.
- **PASS:** Haramain statement closes at 2,900 and contains Group B's 630 plus Group C's 2,000 and 270 transport costs. The reversed 2,000 payment correctly contributes zero payment for the period.
- **PASS:** Group Profitability closes at 48,800 revenue / 38,300 direct cost / 10,500 contribution; Group D reads 8,400 / 6,000 / 2,400 and Group C reads 30,060 / 23,770 / 6,290.
- **PASS:** Departure Manifest, Hotel Rooming, Transport Dispatch, Voucher Control, Ticket Sales, Ticket Supplier Reconciliation, and Ticket Cancellations contain the expected retained data. The ticket clearing control is zero and UVR-00004 appears as Cancelled.
- **FAIL — ticket invoice absent from Agent Statement:** The full-year statement contains the four group charges, allocations, and the refund row, but no TB-00001 ticket invoice/charge. Total charges remain 48,800 (groups only), contradicting the walkthrough's expectation that the ticket invoice is included.

### Final role/UI check

- **PASS:** The agent dashboard shows only its four groups and balances. Group C shows `Partly paid`; no cost, supplier payable, margin, or profit value is exposed.
- **UX:** Group transport schedules on the agent group detail render as raw ISO values such as `2026-10-01T06:00:00.000000Z`, unlike the human-formatted voucher schedule.

## Recording verdict after dbf09455

**Not recording-ready.** The fixed cutoff, refund service, refund statement row, supplier reallocation, PDFs, and role isolation are sound. Recording remains blocked by the approved-voucher amendment 500, the impossible no-hotel approval for a Visa + Transport voucher, the unavailable Skyline ticket-supplier payment, and the missing ticket invoice on Agent Statement. The five-hour screen/PDF flight-time disagreement is also visible enough to fix before filming.

## Recheck — revision 39aa8b41

- **PASS:** UVR-00001 amendment now creates UVR-00005 instead of returning a 500. The amendment preserves the three passengers, complete itinerary and hotel stays.
- **FAIL / recording blocker:** Amendment accounting double-counts the superseded voucher. UVR-00001 had hotel charge/cost of 21,600/17,000. UVR-00005 changed the first room count from 1 to 2, producing 36,000/28,400, so the group should move by exactly +14,400/+11,400 to 36,000/28,400. Instead, approving UVR-00005 produced approved hotels 57,600 and hotel cost 45,400, with Group C receivable 66,060 and profit 13,890. This is the old amount plus the new amount, not a replacement/difference. UVR-00001 correctly reads `Superseded by UVR-00005`, but the consolidated accounting still includes it.
- **PASS:** UVR-00003 (Visa + Transport, no hotel stays) saved a complete flight itinerary and approved successfully. The approval no longer requires an irrelevant hotel stay.
- **PASS:** Entered flight wall-clock times render consistently on the voucher detail: e.g. 09:00/13:00 and 15:00/19:00 remain those times after save. Transport schedules also render as formatted times rather than raw ISO strings.
- **PASS:** Agent Statement now includes `INV-01001` / `Ticket booking` as a 3,980 SAR charge, as well as the existing receipt and refund rows.
- **WALKTHROUGH DATA NOTE:** The ticket statement and amended Group C totals are necessarily inflated while the amendment accounting defect remains; do not use the retained QA company for a recording until this is corrected or rebuilt with clean data.

## Recording verdict after 39aa8b41

**Still not recording-ready.** Five of the six reported fixes passed in the browser. Amendment accounting is a financial-integrity failure, so it is the remaining must-fix before recording.
