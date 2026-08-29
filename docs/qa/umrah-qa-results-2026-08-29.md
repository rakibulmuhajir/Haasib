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
