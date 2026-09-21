# Fuel Station Manual E2E — 20 September 2026

## Current status

The browser-only run is in progress against the manually created company `Mehran Manual E2E`.
Setup completed so far includes the company, petrol and diesel products, two tanks, four pump/nozzle opening readings, two lubricant products, three bank accounts, cash, opening balances, all six buyers with Rs 200,000 credit limits, and all three suppliers.

Day 1 daily close is entered through all four sections, but final posting is paused because the review totals expose a lubricant calculation issue.

## Issues found before Day 1

### Customer settings update exposes an unhandled Laravel error — confirmed

Changing a customer's payment terms from Net 30 to Net 15 sends a `PATCH` request to:

`/{company}/customers/{customer}`

The route supports `GET`, `HEAD`, `PUT`, and `DELETE`, but not `PATCH`. The browser displays Laravel's `MethodNotAllowedHttpException` debug page with the exception trace, request headers, cookies, HTTP method, and URL. This is an HTTP 405 and a production error-handling defect.

Expected behavior: the update method and route must agree, and any business or routing failure must return the normal Inertia validation/toast response. Production must not render Laravel exception pages or expose request details.

### Opening balance entry is split across two screens — confirmed usability issue

The bank account form accepted an opening balance and showed it on the account detail page, but the account's current balance remained zero. The separate Accounting → Opening balances page was required to post the cash and bank balances to the ledger.

## Remaining browser work

Employees are complete. Continue with the fixed Day 1–14 transactions from `docs/fuel-station-e2e.md` after resolving the Day 1 review issue below.

### Day 1 retry: buyer search returns no customers

After restarting the local server and logging back in, the corrected Day 1 form calculates lubricant sales correctly (Rs 12,900; total sales Rs 377,900). However, the Cash Out credit-sale customer search returns “No customers found” for both `Al-Habib` and `CUST-00001`, despite the buyer having been created during setup. The close remains unposted rather than creating a duplicate through Quick Add.

### Lubricant quantity is not reflected in daily-close amounts — confirmed

In Day 1 daily close, entering Mobil Super 4L quantity 4 at Rs 2,400 and Open Engine Oil quantity 3 L at Rs 1,100 left the line amounts at Rs 2,400 and Rs 1,100. The review total was Rs 3,500, while the fixed E2E case expects Rs 12,900 (4 × 2,400 + 3 × 1,100). The app therefore calculates expected closing cash as Rs 226,500 instead of the test's Rs 235,900. Entering the prescribed count displays a Rs 9,400 cash-over warning before posting.


---

## Resolution — 21 September 2026

### Lubricant quantity — root cause found, fixed in two places

**The UI.** `recalculateOtherSaleAmount()` was always correct (`quantity * unit_price`). What
was wrong was when it ran. The lubricant quantity and unit-price fields were wired to `@input`
on the shadcn `Input` **component**, which only reaches the inner element as a fallthrough
listener. They were the only two fields in `DailyClose/Create.vue` wired that way — every other
recalculation in that form (seven of them) uses the declared `@update:model-value` emit. So the
amount kept the value it had when the item was picked, at the default quantity of 1: 1 x 2,400
and 1 x 1,100 = 3,500, against the expected 12,900. The 9,400 cash-over warning was exactly
that gap. Both fields now use the declared emit.

**The server, which mattered more.** `DailyCloseService` took `(float) $sale['amount']` from the
request and posted it to revenue. `quantity` and `unit_price` were validated, stored, and never
used. Two consequences:

- Any UI bug that miscalculated the amount posted a wrong revenue figure, and the close
  **balanced against it**, because the reconciliation uses the same number. Day 1 would have
  posted 3,500 of lubricant revenue and looked correct.
- The three fields were independent, so a crafted request could post arbitrary revenue —
  `quantity: 1, unit_price: 1, amount: 999999` booked 999,999 — and store a line whose figures
  never multiplied out, which then fed `ProductProfitabilityReportService`.

The amount is now derived on the server and the request's own figure is ignored. The ledger no
longer depends on the browser being right.

Covered by `tests/Feature/FuelStation/DailyCloseOtherSalesAmountTest.php`.

**Day 1 can resume.** Expected closing cash should now come out at the script's Rs 235,900.

### The two issues found before Day 1 are fixed and deployed

Production is on `64b7b2e5`. Both need re-verifying rather than carrying forward.

- **The 405 on customer settings.** Fixed. It was never only payment terms: `useInlineEdit`
  sends `PATCH` and the route was registered `PUT`-only, so **all nine** inline fields on the
  customer page and **ten** on the vendor page were dead, plus both address saves. Worth
  re-testing more than the one field.
- **Opening balance split across two screens.** Fixed. The bank account form now posts the
  ledger entry itself, through the same command the Opening Balances page uses. The second
  visit is no longer needed.

### One correction to this report

> Production must not render Laravel exception pages or expose request details.

Production does not. `APP_ENV=production` and `APP_DEBUG=false` on the server; the trace with
headers and cookies appeared because the run was against local, where `APP_DEBUG=true`. The 405
was broken functionality, not an information-disclosure defect, and should be filed as such.
