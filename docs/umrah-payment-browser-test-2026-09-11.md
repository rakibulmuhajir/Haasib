# Cross-agent payment browser acceptance — 11 September 2026

Result: normal charged transfer/payment/accounting paths pass, but release remains blocked by a negative-allocation validation defect. The payment-date default also needs correction for local dates.

## Environment and scope

- Local app at `127.0.0.1:9002`, seeded owner demo login. Tested the shared staff payment-entry UI; a separately logged-in clerk persona was not exercised in this run.
- Created isolated `QA-PAY-0911` agents A/B, visa/transport/hotel suppliers, two purchase groups, three synthetic travellers and two draft vouchers. Fixture preparation used the application group service to post actual test charges. It did not transfer passengers, approve vouchers or record payments.
- Browser performed the transfer, B's hotel approval, all payment submissions and statement inspection. Read-only database checks reconciled financial records and journal balances. No real money was transferred and no production data was changed.

## Successful flow

1. Original group A: two passengers; visa revenue PKR 2,000, transport revenue 400, visa cost 1,400, transport cost 300. Group B: one passenger; visa revenue 1,000, transport revenue 200, visa cost 700, transport cost 150.
2. Moved `QA-PAY-0911 Traveller A1` from A's draft voucher to B's through Move Passengers. A retained one travelling passenger, B had two. Original group charges and journal references remained unchanged.
3. Recorded receipt `UPM-00020`, PKR 600 from A, allocated only to group A. Group balance became 1,800.
4. Approved B's Company hotel voucher through the browser: one double room, two nights, rate 100 per bed/night, cost 60. B alone gained hotel revenue 400 and supplier cost 240; B's group balance became 1,600. A gained no hotel charge.
5. Recorded receipt `UPM-00021`, PKR 400 from B, allocated only to group B. B's group balance became 1,200.
6. Recorded supplier payment `UPM-00022`, PKR 100 to the QA hotel supplier, allocated to B. Hotel payable became 140; agent receivables did not change.
7. Browser statements confirmed A's original charge 2,400 / allocated receipts 600 / closing receivable 1,800 and B's charge 1,600 / allocated receipts 400 / closing receivable 1,200. Supplier balances from read-only reconciliation: visa 2,100, transport 450, hotel 140.
8. All 13 related group, hotel, receipt, allocation and credit journals balanced, including the deliberately reproduced invalid-input case below. Balanced journals alone do not mean that case is correct.

## Invalid cases

| Case | Observed result |
| --- | --- |
| Empty amount and no agent | Rejected with inline errors; no payment created |
| Negative payment amount (-1) | Rejected with inline amount error |
| Zero payment | Rejected with inline amount error |
| Allocate 700 against a 600 receipt | Submit disabled; explicit allocation-limit message |
| Allocate 2,500 against A's 2,400 balance | Server rejected; inline outstanding-balance message |
| Select B after A | Allocation rows belong only to B; A's group is not offered |
| Negative allocation (-1) against a positive 10 receipt | **FAIL: saved receipt as unallocated credit instead of rejecting input** |

## Defects to fix

### Negative allocation silently becomes credit

- Reproduction: Record Payment → Agent A → amount 10 → group allocation -1 → Record Payment.
- Actual: created `UPM-00023` for 10, with no allocations. Statement shows available advance 10 and net due 1,790 while group receivable remains 1,800.
- Expected: preserve the entered fields, show a clear allocation validation error, and create no payment/journal.
- Cause: `Payments/Create.vue` filters allocations using `base_amount > 0` before submission. A negative entry is silently omitted; the server sees an intentionally unallocated receipt and accepts it. The UI must reject negative/non-finite allocation entries before that normalization. Empty/zero allocation may still mean intentionally unallocated credit.
- The PKR 10 synthetic credit is retained as test evidence, not deleted or silently corrected.

### Date defaults to UTC rather than the user's local day

- During this 11 September Asia/Karachi session, new payment forms defaulted to 10 September. Valid test payments were manually set to 11 September.
- `Payments/Create.vue` initializes the date using `new Date().toISOString().slice(0, 10)`, which returns the UTC date. Use the established local-date helper; this does not require a complex timezone UI.

## Automated evidence

- Transfer, allocation reversal, settlement account, submission review, agent/group payment status and voucher amendment accounting: **76 passed, 605 assertions**.
- TravelReportsTest: **9 passed, 107 assertions**.
- Combined this run: **85 passed, 712 assertions**. The negative-allocation browser failure is not covered by these passing tests; do not treat the suite as full release sign-off.

## Records retained for retesting

- Agent A: `01a08cb7-8df9-71cf-9d1b-5028f07e7c25`; group `01a08cb7-8e57-710c-a60e-6d1683275c6c`; voucher `01a08cb7-8eff-7285-99d2-cf3682cf77e7` (draft).
- Agent B: `01a08cb7-8f21-726d-a73a-28f6697dc3ce`; group `01a08cb7-8f3b-7304-ba55-1234b57825bb`; voucher `01a08cb7-8f92-7232-b259-913f4b6a0d81` (approved).
- Hotel supplier: `01a08cb7-8dea-736b-8956-8f4dd42b8866`.
- Payments: `UPM-00020` through `UPM-00023`.

No application fixes or release were performed during this testing-only run. Fix the two defects, add frontend regression coverage, and repeat the failed browser cases before closing this release gate.

## Follow-up fixes and verification — 11 September 2026

Both defects above are now fixed locally. The original failure evidence remains unchanged.

- Payment allocations are validated before omission/normalization. Negative, malformed and non-finite values block the entire submission, show an inline row error and a Sonner toast, and retain the entered data. Invalid entries no longer inflate the credit preview. Decimal text entry preserves malformed input for validation instead of allowing a number input to erase it into an empty value.
- Empty and zero rows remain valid intentional credit. Switching party clears allocations; positive allocations, Auto allocate and the existing over-allocation protection remain functional.
- A shared `localDateInput()` helper supplies the standalone payment form and the group payment dialog's initial/reset dates. No timezone configuration was added.

Verification:

- Browser: Agent A, receipt 10, allocation -1 — stayed on form with row error and toast. Repeated with `bad` — rejected. Read-only database checks before and after confirmed the same two Agent A receipts (UPM-00020 and UPM-00023); neither invalid attempt created a receipt.
- Browser: switch to Agent B — only B's allocation row appears, blank and without the stale validation error. Allocation 11 against amount 10 disables submission; zero remains allowed; Auto allocate fills 10.00.
- Browser: corrected valid receipt **UPM-00024**, dated **2026-09-11**, received **PKR 10** from QA Agent A; **PKR 5** allocated to QA-PAY-0911-A and **PKR 5** retained as credit. Register and group page agree. Group A now shows paid **605** and balance **1,795**. Original UPM-00023 remains untouched.
- Read-only journal check: UPM-00024 receipt journal debit/credit **10/10**, allocation journal **5/5**.
- Frontend regression tests: **20 passed** (16 new payment/date tests plus 4 existing stay-date tests). Includes mixed valid/invalid allocations, malformed/non-finite input, blank/zero, correction, selected-party filtering and UTC-midnight/year-boundary dates in Karachi and Los Angeles.
- Backend regression rerun: **85 passed, 712 assertions** across transfer, allocation reversal, settlement accounts, submission review, agent/group payment status, voucher amendment accounting and travel reports.
- Production build and targeted ESLint passed; `git diff --check` passed. Build reports existing unresolved font asset warnings.

These two payment defects no longer block local acceptance. This is not full feature-set or production sign-off; the release tracker's other acceptance requirements still apply. No deployment performed.
