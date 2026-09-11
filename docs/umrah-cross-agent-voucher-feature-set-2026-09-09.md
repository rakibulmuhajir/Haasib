# Travelling-party vouchers across agents

Status: local implementation in progress. **Do not deploy this feature set yet.**

## Agreed business rule

Agent A buys a passenger's visa/transport. The passenger joins Agent B's travelling-party voucher. Agent A retains its existing charges, payments and supplier obligations. Agent B is responsible for subsequent services it purchases. Joining a party does not cancel hotels or move an old debt. The group leader is not automatically the payer.

Import group membership and purchase references remain distinct from voucher membership. Repeat journeys are separate purchases even when passport numbers repeat.

## Implemented first slice

- Company staff can use Move Passengers to move between ordinary draft vouchers across groups/agents within one company.
- Destination choices identify agent, group, voucher and current pax count. Agent logins cannot discover or transfer into another agent's vouchers.
- Passenger and assignment retain their original purchase group; only voucher membership changes.
- Cross-group moves can empty the original draft without deleting it or changing its purchases. Empty drafts cannot be approved.
- Existing financial totals, payments and allocations remain unchanged; both voucher histories record source/destination agents.
- Previously approved vouchers, amendments, posted hotel records and shared-billing drafts are not eligible for cross-group moves in this slice.
- Recipient agent payloads hide passenger transport charges and private notes.
- Original passenger deletion is blocked while assigned to another group's voucher.
- Amendment copying preserves each passenger's original group reference.
- Mixed-group vouchers can now print and approve. Empty vouchers and vouchers whose original purchase group is missing/deleted/cancelled remain blocked.
- Mixed-provider output maps passenger row numbers to visa/transport providers without prices or original-agent private notes. Four header slots remain; multiple providers are identified in the service table rather than showing a misleading single provider logo.
- Operations preserves one party hotel booking and splits movement checks by original transport purchase group. Original transport manifests include transferred passengers without adding them to the destination group's transport purchase. Agent users cannot drill into another agent's original group.
- Hotel approval resolves the destination agent's effective rate. The HTTP lifecycle test proves original charges/payment allocations remain unchanged through approval, repeated approval, hotel amendment and cancellation.
- Fixed an existing supplier-balance reversal bug: the reversed voucher must be excluded before its cancelled/superseded marker is written, otherwise supplier balances retain cancelled stays or double-count amendments.
- No schema migration or production data edits.

## Work required before release

1. Approved-voucher transfer is now implemented locally (see issued-party verification below). Pending amendments and shared-hotel-billing vouchers remain explicitly blocked; these are not silently cancelled or rebilled.
2. Explicit leader selection is implemented: Create/Edit display a checkbox for every member, with at most one selected leader. Selecting another unchecks the previous one; deselecting a member clears their leadership. The saved UUID is validated against selected/current members and printed explicitly. Legacy vouchers without a selection show `Not selected`, rather than inventing a leader from row order. A leader moved out clears the source selection; destination leadership remains unchanged. Amendments preserve the choice and separated copies retain it only when that passenger is present.
3. Browser acceptance for staff and recipient agents, including hotel-only destination booking behavior, optional contacts/branding, payment entry/allocation and actual movement schedules for passengers travelling on different dates within an import batch. Automated tests currently prove preservation of existing payment allocations, not every new payment-entry scenario.
4. User acceptance, followed by a separately authorized release. No deployment performed.

## Automated checks

`build/tests/Feature/Umrah/CrossAgentVoucherTransferTest.php` covers successful and repeated transfer, original purchase/payment preservation, cross-agent permissions, cross-company rejection, private-data suppression, invalid passengers/destination, empty-source handling, amendment provenance, mixed-provider print/PDF, destination-agent hotel pricing/amendment/cancellation, original transport attribution, aggregate/detail counts and cancelled-source approval rejection.

Related regression suites: VoucherAmendmentAccountingTest, VoucherAmendmentAndApprovalTest, VoucherDraftEditingTest and VoucherPrintProfilesTest. Test execution is isolated to `haasib_test`; local demo and production records are not reset.

Build: `npm run build`. Browser E2E has not yet been performed for this slice. Passing backend tests/build is not completion of the release gate above.

The verification entries below are chronological; earlier draft-only and no-browser statements describe the earlier slices, not the current implementation.

Verification on 9 September:

- Final focused run: **30 passed, 118 assertions** (18 transfer cases plus 12 amendment/accounting/draft regressions).
- Earlier broader run: **63 passed, 317 assertions**, including 36 print/profile tests. Subsequent changes were last-passenger handling, extra transfer tests and shared-billing/empty-selection safeguards; print/profile tests were not rerun after those final backend-only changes.
- Production frontend build passed. Existing font-resolution warnings remain.
- PHP formatting check passed for all seven affected PHP files.

Second-slice verification on 9 September:

- **128 passed, 875 assertions** across CrossAgentVoucherTransferTest, OperationsTest, VoucherAmendmentAccountingTest, VoucherAmendmentAndApprovalTest, VoucherPrintProfilesTest and CommercialPricingTest.
- Final print placement refinement rerun: **2 passed, 10 assertions**. Accommodation stays directly below passengers; mixed-provider details follow flights.
- Frontend production build, focused ESLint and PHP formatting checks passed.
- Generated the application PDF through its authenticated route using synthetic test data; rendered with Poppler and visually checked. Three passengers, two original provider sets and three hotel stays fit on one A4 page without clipped tables. This is a representative sample, not a guarantee that every passenger/contact combination fits one page.
- Browser interaction E2E and release acceptance remain outstanding. Test data/QA output are isolated; no local demo voucher or production record was edited.

Leader checkbox verification:

- Migration `2026_09_09_000001_add_voucher_group_leader` applied to the **local** database; it adds a nullable UUID field and does not backfill a guessed leader. Deployment must include this migration.
- **66 tests passed, 370 assertions** across transfer/leader, print-profile and amendment suites; production build, ESLint and PHP formatting passed.
- Browser checked on the local Create page with two existing unassigned passengers: selecting the second leader unchecks the first; removing that selected passenger clears/disables their leader checkbox. Edit-page check/uncheck also verified. No demo voucher was saved or modified during browser checks.
- HTTP tests verify create/update validation, invalid/multiple/unselected leaders, explicit printing and clearing, transfer preservation and amendment/separation behavior.

## Issued-party transfer slice — 9–10 September

- Supports approved → draft, draft → approved and approved → approved transfers for company staff with voucher update and approval permissions. A reason is mandatory when either voucher is approved. Agent logins retain draft-only, own-agent access.
- No approval/amendment/cancellation accounting is invoked during transfer. Existing rooms, hotel sale/cost journals, purchasing agent, original visa/transport group, payments and allocations stay unchanged. Room capacity and any actual booking changes must be handled explicitly; moving a person does not reserve another bed.
- Current approved copies increment their printed version number. Audit history snapshots the before/after names, passport references, original groups and leader, plus the retained commercial/itinerary data. This is a readable audit history, **not a pixel-exact archived PDF/reprint facility**.
- Source leadership clears only when that person moves; destination leadership stays unchanged. One-passenger sources can now open the transfer dialog. Empty approved sources retain purchases and hotel booking visibility, but do not generate flight/city movements.
- Cancelled/superseded copies, pending amendments and shared-billing copies/owners are rejected. Transfers into an approved destination reject invalid original purchase groups atomically. Repeated submissions cannot duplicate membership or add another history entry.
- Added destination-sensitive reason visibility, disabled submission while required information is missing, explicit booking/reprint guidance and readable transfer history under Internal details & history. Malformed destination IDs stop at validation instead of reaching PostgreSQL UUID parsing.
- Browser verified staff transfer using two new, clearly labelled local QA vouchers: `QA-ISSUED-20260909171832-0` and `QA-ISSUED-20260909171833-1`. Source is now empty, destination has two synthetic passengers, destination leader is unchanged, both show version 2, and source history shows v1 → v2. The records contain external test stays and no posted hotel charges. Existing demo records and production were not edited.
- Initial issued-transfer run: **33 passed, 264 assertions**. Subsequent extra edge cases and the empty-party Operations contract change require the final rerun recorded below. An earlier broad regression run had 110 passes and one obsolete empty-party movement expectation; missing-stay checks remain covered with assigned passengers, and a dedicated empty-party test covers retained hotels without ghost movements.
- This slice adds no schema migration. The previously implemented nullable leader migration is still required for deployment.
- Remaining release gates: recipient-agent browser acceptance, new payment-entry/allocation scenarios, different travel-date cohorts within one imported batch, optional contacts/branding in the complete cross-agent flow, and user acceptance. No release performed.

Final verification on 10 September:

- **147 passed, 1,029 assertions** across CrossAgentVoucherTransferTest, OperationsTest, VoucherAmendmentAccountingTest, VoucherAmendmentAndApprovalTest, VoucherDraftEditingTest, VoucherPrintProfilesTest and CommercialPricingTest.
- After restricting commercial audit snapshots to financially authorised viewers, a final targeted run passed **5 tests, 133 assertions**, including operations-staff denial/privacy, all issued/draft transfer combinations and posted-hotel accounting preservation.
- Tests used `php -d xdebug.mode=off -d memory_limit=512M vendor/bin/pest ...` against the isolated test database. The final commands did not change the local PHP configuration.
- Final production build and focused ESLint/Pint checks passed; existing font-resolution warnings remain. `git diff --check` passed.
- Local browser verified missing-reason submission disabled, successful single-passenger approved → approved cross-agent transfer, updated passenger counts, preserved recipient leader, printed version 2 and readable source history. QA records remain labelled for inspection; the one-off fixture creation script was removed.

## Remaining acceptance checks — 10 September follow-up

- Added a real payment HTTP lifecycle after a cross-agent move: Agent B's new receipt allocates only to B's group, balanced journals are posted, and A's original group is unchanged. Reusing the payment number is rejected. Attempting to allocate B's receipt to A's group rolls back without creating a payment, allocation or journal.
- Confirmed one import group can have separately dated vouchers: only the correct passenger appears on each arrival date.
- Reproduced and fixed a pickup-report defect: each scheduled pickup previously listed all approved passengers from the original import group, including people travelling two weeks later. Pickup suggestions now match each passenger's **current** approved voucher arrival/departure date and route or its hotel city-transfer dates, while retaining original transport purchase provenance.
- Explicit scheduled headcount is retained. Missing itinerary matches and differences between that headcount and matching passengers are needs-attention. Multiple same-route pickups on the same date do **not** receive guessed passenger lists; they require allocation confirmation. No group-size fallback fabricates a full-batch manifest. This remains an itinerary-derived report, not an explicit per-vehicle dispatch allocation feature.
- Updated the report regression fixture: its city pickup before the passengers' arrival now correctly has no passenger suggestions and a warning, while its entered headcount remains visible. Arrival, departure and city-transfer cohort tests include ambiguous same-day pickups.
- Recipient-agent browser check used the existing `demo-agent` login (Al-Noor). On the synthetic receiving voucher, the transferred traveller and print preview were visible; Accounting, Move Passengers and transfer audit history were absent. Direct access to the other agent's synthetic voucher returned 404.
- QA fixture preparation for that recipient check used the transfer command on the two existing synthetic records after an attempted browser submission did not persist. This is **not** counted as another successful browser transfer. Both QA vouchers now have one passenger and version 3; no real purchase or existing demo traveller was edited.
- Contacts/footer HTTP print test confirms the destination retains its own Makkah/Madinah representatives, footer and agent identity. Source-only contact names/phone/footer do not leak into the receiving agent's print.
- Initial combined follow-up run: **102 passed, 784 assertions** across transfer, Operations and four payment suites. Final run additionally covers contact isolation and ambiguous pickups; see result below.
- No production release, new migration or new permissions in this follow-up. Remaining manual acceptance: user checks the complete clerk payment-entry screen and the travelling-party workflow on representative data, and accepts the explicitly excluded shared-billing/pending-amendment/vehicle-allocation cases before authorising release.
- Final follow-up run: **139 passed, 1,015 assertions**, covering CrossAgentVoucherTransferTest, OperationsTest, PaymentAllocationReversalTest, PaymentSettlementAccountTest, PaymentSubmissionReviewTest, AgentGroupPaymentStatusTest and VoucherPrintProfilesTest. Pint and `git diff --check` passed. This follow-up changes backend projection/tests only; no frontend asset rebuild is required by these changes. The existing owner demo session was restored after the agent checks.

## Browser failure correction and group visibility — 10 September 2026

- Reproduced the previously unpersisted browser move. A long destination option forced the form beyond the dialog's grid column. The Move button overflowed outside the dialog; clicking it dismissed the dialog rather than submitting. This was a layout failure, not a successful request or a failed accounting transaction.
- Constrained the form's minimum width, wrapped dropdown options, widened the dialog within the viewport and enabled vertical scrolling. Verified the long QA label and the Move button remain inside the dialog. Added progress indication and retained the dialog/input when a redirect carries an error flash.
- Group detail now shows original purchase passengers and a current-voucher link/elsewhere indicator. A separate table lists passengers joining the group's vouchers from other purchase groups. It does not change original membership or accounting. Displayed purchase headcount comes from the actual passenger list; the earlier synthetic fixtures had a stale stored count of zero.
- Removed visa-processing status controls from the group passenger list, Add Passenger and Correct Passenger dialogs. Kept historical database values and legacy endpoints unchanged; this is not a visa-processing feature.
- The server projection excludes released assignments, cancelled/deleted/superseded vouchers and pending amendment drafts. Foreign voucher links/details are withheld from original-agent users; receiving agents get travelling identity but no foreign group link, private notes or prices.
- **Actual browser round trip, no command/DB movement:** QA Issued Traveller 1 moved from Al-Noor's QA voucher to Madina Tours' QA voucher (source 1 → 0, target 1 → 2, versions 3 → 4). Then moved back via the browser (counts 1 each, versions 4 → 5). Missing-reason submission was disabled. Read-only audit verification confirmed two new pairs of moved-out/moved-in records with the entered reasons.
- Final QA state: Al-Noor's original passenger, QA Issued Traveller 0, travels on Madina Tours' voucher; Madina Tours' original passenger, QA Issued Traveller 1, travels on Al-Noor's voucher. Both group pages visibly show the outgoing passenger in the original-purchase table and the incoming passenger in the joining table, with correct voucher links. Both voucher versions are 5. Only labelled synthetic records were moved; no real customer or production records were changed.
- Added 7 regression cases for both group pages, return movement, agent privacy and inactive/historical assignments. Focused transfer run: **50 passed, 461 assertions**. Broader run (transfer, Operations, AgentGroupPaymentStatus and VoucherPrintProfiles): **127 passed, 1,037 assertions**. Pint, focused ESLint and diff whitespace checks passed. Production asset build passed with existing font-resolution warnings.
- These corrections require a frontend rebuild; they add no migration or permission. The earlier leader migration remains part of the overall feature set. No production release performed.
