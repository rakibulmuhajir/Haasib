# Hotel confirmation spectator walkthrough

14 September 2026. Local built-in browser only; no production changes or deployment.

Test voucher: **QA-PAY-0911-B**, hotel **QA-PAY-0911 Test Hotel**, 10–12 November 2026.

## Passed in this walkthrough

1. Changed Confirmed to Pending, saved a staff-only note, and observed the new history entry.
2. Operations, custom 10–12 November / Hotel check-ins / Needs attention, showed exactly one pending check-in.
3. Update hotel confirmation opened the voucher's Internal details & history tab. Confirmed it with a newer note.
4. Submitted an older copy from a second tab. Save was rejected with: “This stay was changed by another update. Reload the page before saving.” Reload confirmed that the newer note survived and the stale test text was absent.
5. The same pending Operations queue then showed zero matching movements.
6. Cleared BRN and confirmation number using keyboard selection/deletion. Confirmed saved successfully with both references blank. Restored the QA reference labels afterward.
7. Typed an unsaved reference and cancelled the dialog. Reopening discarded the cancelled change.
8. Passenger preview showed accommodation but no staff-only note or confirmation history.
9. Hotel charge/cost remained 400/240 PKR before and after. Final Accounting page showed hotel margin 160, group receivable 1,600, received 400 and balance 1,200 PKR. The latter totals were observed at the end, not independently baselined during this walkthrough.

The automation's empty-string fill did not clear existing textbox contents; keyboard deletion did. This was verified before the successful blank-reference save and is not evidence of an application save defect.

Voucher left Confirmed with QA-labelled references and the spectator-test note. Its history is retained. No application code changed in this walkthrough.

This walkthrough did not repeat agent-login, multi-hotel, amendment or reconfirmation scenarios in the browser. Those remain covered by the earlier request-level/regression tests documented in `umrah-hotel-confirmations-feature-set.md`; this is not a claim of exhaustive browser acceptance.
