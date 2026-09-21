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

### Lubricant quantity is not reflected in daily-close amounts — confirmed

In Day 1 daily close, entering Mobil Super 4L quantity 4 at Rs 2,400 and Open Engine Oil quantity 3 L at Rs 1,100 left the line amounts at Rs 2,400 and Rs 1,100. The review total was Rs 3,500, while the fixed E2E case expects Rs 12,900 (4 × 2,400 + 3 × 1,100). The app therefore calculates expected closing cash as Rs 226,500 instead of the test's Rs 235,900. Entering the prescribed count displays a Rs 9,400 cash-over warning before posting.
