# Umrah module — full walkthrough on a fresh company

For Luna. Written 2026-08-25, corrected 2026-08-29 against her first run.

This builds one travel company from nothing and runs the whole Umrah module
through it: one agent, four groups covering all three transport modes, hotels,
tickets, money in and money out. Where the party walkthrough asked "does the
name render", this one asks "does the money land where it should".

**Run this on your local machine, not production.** You will create and delete
a lot, and some of it is meant to be wrong.

## What changed since the first run

Every route, name and figure below has been corrected against what you actually
found. The steps that were impossible as written — the fare negative test, the
group-linked refund, the module toggle — are gone or rewritten. Three fixes
also shipped, and each has a check in place where you hit it:

- A group part-paid by its agent reads **Partly paid**, not Paid (Part 9).
- Draft voucher stays read **"Priced on approval"** and count their nights
  (Part 5).
- Typed flight dates stay put when you move to the next field (Part 5). A date
  typed as `01/11/2026` is 1 November — it used to be stored as 11 January.

Four things you logged are known and not worth logging twice: the "3 mutamers
imported" toast on a duplicate import, the Vehicles/Pax display on a
per-passenger fare, the capacity hint's closing sentence, and company deletion
leaving the record in place.

## Setup

```
php artisan serve --port=9001
npm run dev
```

Then http://localhost:9001. Log in as `demo-manager` / `demo-password` — the
field takes a username or an email, and `manager@demo.haasib.app` works too.

Octane is not available in this checkout, so `php artisan serve` is the one to
use. `npm run dev` prints its own port (5173, 5174, whichever is free) and you
never visit it — it only serves assets to the app on 9001.

Keep a scratch file open. Every part asks you to write down a number or an id
that a later part needs.

## The shape of what you are building

One agency, **Rihla Travel QA**, base currency SAR, that also handles some PKR.
It sells umrah packages through a single agent, buys visas from one provider,
buses from two, hotel rooms from one, and air tickets from a supplier.

Four groups, chosen so each transport mode gets exercised:

| Group | Transport mode | Pax | Passengers come from | Why it exists |
|---|---|---|---|---|
| A | Self-arranged (none) | 4 | Typed by hand | Visa-only pricing, no transport anywhere |
| B | Standard bus | 7 | Imported (6) + one child typed | Bus charge off the vendor's rates, child fare |
| C | Specialized | 6 | Two files imported into one group | Two fare items, a Hajj surcharge, hotels |
| D | Specialized | 4 | Imported (2) + two typed | Created, then **edited** — the adjustment posting |

### The mutamer files

Four anonymised sample workbooks live in `docs/qa/fixtures/`. Between them they
carry 14 passengers, all adults.

| File | Rows | Used for |
|---|---|---|
| `mutamer-list-6-passengers.xlsx` | 6 | Group B |
| `mutamer-list-3-passengers-a.xlsx` | 3 | Group C, first import |
| `mutamer-list-3-passengers-b.xlsx` | 3 | Group C, second import |
| `mutamer-list-2-passengers.xlsx` | 2 | Group D |

They are real exports with every name, passport number and agent replaced, so
the column layout is exactly what the importer expects.

The import reads **four columns only** — Mutamer name, Mutamer Age, Passport
Number, Nationality. Everything else in the sheet is ignored, including the
sheet's own **Visa Status** column: every imported row lands as `received`
whatever the file says. Three of these four files say "Visa Not Issued" and all
of them import identically. Worth confirming that is intended rather than
assumed.

Ages arrive as a plain number with no date of birth. Under 12 prices as a
child; everyone in these four files is 27 or older. Imports **append**, so two
files can be loaded into the same group one after the other.

## How to report anything you find

The URL, what you expected, what you saw, a screenshot. For anything involving
money, include the numbers you expected and the numbers on screen — a wrong
total is a different bug from a missing one.

Three shapes matter here:

| What you see | What it means |
|---|---|
| A dropdown is empty when it should have options | Setup is incomplete, or a query is filtering wrongly |
| A total is wrong | Pricing or posting bug — the serious kind |
| A page 500s | Log it and move on; do not retry |

Each part ends at a safe stopping point. This is two or three sittings.

---

## Part 1 — the company

1. Go to `/companies/create`.
   - **Name:** `Rihla Travel QA`
   - **Industry:** the travel/umrah one — this is what turns the module on
   - **Base currency:** `SAR`
   - **Country:** Pakistan
   - **Owner:** yourself
   - Note the slug it generates — probably `rihla-travel-qa`. Everything below
     writes it as `<company>`.
   - **Use this page, not the quick-add form on `/companies`.** The inline form
     picks PKR for you and never offers the base-currency choice, which is the
     one setting the rest of this document depends on.
   - If the form fails silently on submit, look at the secondary currency: an
     untouched one leaves an empty exchange rate that the backend rejects
     without marking the field.
2. Finish the onboarding wizard at `/<company>/onboarding`. All of it: company
   identity, fiscal year, bank accounts, default accounts, tax settings,
   numbering.
   - **Do not skip this.** Without default accounts nothing can post to the
     ledger, and every later step fails with an error that does not say so.
   - Give yourself at least one **cash** account and one **bank** account. You
     will pay from both.
3. Confirm **Umrah** is in the sidebar. A travel company gets the module
   automatically — there is no module toggle under Settings to switch on.
   - *Fail:* if `/<company>/umrah` says "This module is not enabled for the
     selected company", the industry on the company is wrong. Fix that before
     going on; nothing past here works without it.
4. In `/<company>/settings`, add **PKR** as a second currency at rate `0.0125`.
5. Open `/<company>/umrah`.
   - *Expect:* the dashboard renders, all widgets empty, no errors.
   - Empty widgets on a brand-new company are correct. A 500 is not.

**Stop point.** From here on you are entering data.

---

## Part 2 — the parties

Five kinds of counterparty, and they live on four different screens. Getting
this wrong is the most common reason a later dropdown is empty.

### The agent

`/<company>/umrah/agents/create`

- **Name:** `Sahil Travel Network`
- **Agent number:** `AG-QA-01`
- **Phone / email / city / country:** anything; use Lahore, PK
- Give it a **login username and password** — `sahil_agent` / `qa-password`.
  Underscores, not hyphens: a hyphen is rejected by the username rule. Part 9
  logs in as this.
- Voucher access: allow create and edit, cutoff 24 hours.

Then check `/<company>/customers`. **A customer should exist for the agent**,
with the same name. One agent, one customer. If there is no customer, stop —
nothing involving agent money will work.

### The visa vendor

`/<company>/umrah/vendors`

- **Name:** `Bab Al-Salam Visa Services`
- **Service type:** Visa provider
- **Default:** yes
- **Adult retail:** `900` · **Adult cost:** `750`
- **Child retail:** `500` · **Child cost:** `400`

> **All four rates are required for the vendor to appear in the group form.**
> The group form filters to vendors with complete rates and says nothing about
> the ones it dropped. If your vendor is missing from Part 4's dropdown, this
> is why.

Check `/<company>/vendors` — an accounting supplier should exist for it.

### The transport providers

`/<company>/umrah/transport-providers` — create two.

| | Haramain Coach Company | Own Fleet Transport |
|---|---|---|
| Company owned | no | yes |
| Standard bus retail | `120` | `100` |
| Standard bus cost | `90` | `70` |
| Charge child fare | yes | no |

The two exist so Part 4 can move a payable from one to the other.

### The hotel vendor

`/<company>/umrah/settings/hotel-vendors/create`

- **Name:** `Anwar Hospitality`, city Makkah.

### The ticket supplier

> This one is **not** an Umrah vendor. Ticket bookings draw their supplier from
> the accounting vendor list, a different table on a different screen. Creating
> it under Umrah → Vendors will not make it appear in the ticket form.

`/<company>/vendors` → new vendor, **name:** `Skyline Ticketing`.

**Checkpoint.** `/<company>/umrah/agents` and `/<company>/umrah/vendors` both
list their rows with readable names. `/<company>/customers` has one customer,
`/<company>/vendors` has four suppliers (visa, two transport, ticketing).

---

## Part 3 — vehicles, routes, fares and hotels

This is the specialized-transport chain. It has four links and the fare at the
end needs all of them.

### Drivers

`/<company>/umrah/settings/drivers` — create `Kamal Uddin` and `Faisal Rehman`.

### Vehicles

`/<company>/umrah/settings/transport-services`

| Name | Vehicle type | Seats | Driver |
|---|---|---|---|
| `Coaster 22` | Coaster | `22` | Kamal Uddin |
| `Hiace 12` | Hiace | `12` | Faisal Rehman |

Seat counts matter: the group form uses them to warn when you have booked
fewer seats than passengers.

### Sectors

`/<company>/umrah/settings/transport-services` (same page, sectors section)

**Look at the list before creating anything.** A new company arrives with
several sectors already seeded. Create only the ones missing from this list,
and use whatever the seeded ones are called rather than making duplicates.

| Code | Name | Origin | Destination |
|---|---|---|---|
| `JED-MAK` | Jeddah to Makkah | Jeddah | Makkah |
| `MAK-MAD` | Makkah to Madinah | Makkah | Madinah |
| `MAD-JED` | Madinah to Jeddah | Madinah | Jeddah |

### A package

Same page, packages section. **Name:** `Complete Umrah journey`, containing all
three sectors.

### Fares

Same page, fares section. A fare covers **either** a sector **or** a package,
chosen from a single control — so there is no way to set both and nothing to
test there.

| Name | Vendor | Vehicle | Sector / Package | Basis | Sale | Cost | Hajj sale | Hajj cost |
|---|---|---|---|---|---|---|---|---|
| `Full journey — Coaster` | Haramain Coach | Coaster 22 | Package: Complete Umrah journey | Per vehicle | `2400` | `1800` | `300` | `200` |
| `Makkah to Madinah — Hiace` | Haramain Coach | Hiace 12 | Sector: MAK-MAD | Per passenger | `60` | `45` | `0` | `0` |
| `Full journey — Own fleet` | Own Fleet Transport | Coaster 22 | Package: Complete Umrah journey | Per vehicle | `2400` | `1500` | `300` | `200` |

The third exists so Part 4 can move a vehicle to a different supplier.

### Hotels

`/<company>/umrah/settings/hotels/create`, both under Anwar Hospitality.

| Hotel | City | Room type | Retail | Cost |
|---|---|---|---|---|
| `Dar Al-Eiman Makkah` | Makkah | Quad | `1200` | `950` |
| `Dar Al-Eiman Makkah` | Makkah | Triple | `1500` | `1200` |
| `Al-Haram Madinah` | Madinah | Quad | `900` | `700` |

**Stop point.** Setup is done. Everything after this creates money.

---

## Part 4 — the four groups

Two ways in. Groups A and D show **manual entry**, groups B, C and D show
**file import**, and B and D show both on the same group — import the sheet,
then add someone by hand.

The numbers below are what the module should compute. Check each one on the
group's page after saving. **A total that disagrees is a finding**, and worth
more than anything else in this walkthrough.

### Group A — visa only, no transport, typed by hand

`/<company>/umrah/groups/create`

- **Name:** `QA-A Visa Only`, agent Sahil Travel Network, vendor Bab Al-Salam
- **Transport:** Self-arranged / none
- **Travel date:** any date about a month out
- **4 passengers, typed in one at a time.** Real-looking names and passport
  numbers, all adults, service "Visa included".

Watch the totals update as you add each passenger — they should move on every
row, not only on save.

| | |
|---|---|
| Visa sale | 4 × 900 = **3,600** |
| Visa cost | 4 × 750 = **3,000** |
| Transport | **0** |
| Margin | **600** |

Also check: no transport fields appear anywhere on the saved group, and the
edit page offers no vehicles section.

### Group B — standard bus, imported then added to

- **Name:** `QA-B Standard Bus`, same agent and visa vendor
- **Transport:** Standard bus, provider **Haramain Coach Company**

1. **Import** `mutamer-list-6-passengers.xlsx` (6 rows).
   - *Expect:* "6 mutamers imported", the empty starter row replaced, six named
     passengers with passports, ages and nationality filled, and the passenger
     count and visa pricing recalculated on the spot.
   - *Fail:* the count stays at 1, the pricing does not move, or the ages are
     blank.
2. **Then add a seventh by hand** — a child. Passengers are entered with an
   **age as a number**, not a date of birth, so type an age under 12.

Haramain charges child fare, so all seven are billable on the bus, but the
visa prices the child at the child rate:

| | |
|---|---|
| Visa sale | 6 × 900 + 500 = **5,900** |
| Bus sale | 7 × 120 = **840** |
| Total sale | **6,740** |
| Visa cost | 6 × 750 + 400 = **4,900** |
| Bus cost | 7 × 90 = **630** |
| Total cost | **5,530** |
| Margin | **1,210** |

Then try one thing: switch the provider to **Own Fleet Transport**, which does
*not* charge child fare. The bus should reprice to 6 billable passengers at
Own Fleet's rates — 6 × 100 = 600 sale, 6 × 70 = 420 cost. Switch back to
Haramain before saving.

### Group C — specialized, two files into one group

- **Name:** `QA-C Specialized`, same agent and visa vendor
- **Transport:** Specialized

1. **Import** `mutamer-list-3-passengers-a.xlsx` (3 rows). Confirm three passengers.
2. **Import** `mutamer-list-3-passengers-b.xlsx` (3 rows) into the same group, without leaving
   the page.
   - *Expect:* the second import **appends** — six passengers now, the first
     three untouched, and the pricing recalculated to six.
   - *Fail:* the second import replaces the first, duplicates it, or leaves the
     passenger count at three.
3. Try importing `mutamer-list-3-passengers-b.xlsx` a **second** time. No rows
   should be added.
   - Known and cosmetic: the toast still reads "3 mutamers imported" even
     though the passenger count does not move. Do not log it again.
4. **Vehicle 1:** fare `Full journey — Coaster`, quantity `1`, passengers `6`,
   terminal **Hajj**, and **set a Schedule** — any date/time near the travel
   date
5. **Vehicle 2:** fare `Makkah to Madinah — Hiace`, quantity `1`, passengers
   `6`, terminal Standard, and **set a Schedule** as well

> **Give every vehicle a schedule.** The Transport Dispatch report in Part 8
> selects on that date and shows nothing without it — an empty dispatch sheet
> means the schedules are missing, not that the vehicles are.

| | |
|---|---|
| Visa sale | 6 × 900 = **5,400** |
| Vehicle 1 sale | 2,400 + 300 surcharge = **2,700** |
| Vehicle 2 sale | 60 × 6 = **360** |
| Total sale | **8,460** |
| Visa cost | 6 × 750 = **4,500** |
| Vehicle 1 cost | 1,800 + 200 surcharge = **2,000** |
| Vehicle 2 cost | 45 × 6 = **270** |
| Total cost | **6,770** |
| Margin | **1,690** |

Two known display oddities here, already logged — read past them:

- A per-passenger fare can show its Vehicles / Pax as `2 / 6` after you entered
  a quantity of 1.
- The capacity hint always ends "Booking fewer seats than passengers raises the
  vehicle count on save", even when there is plenty of room.

The money is what matters on this page; check the totals above against the
figures on screen.

### Group D — specialized, imported, extended, then corrected

- **Name:** `QA-D To Be Edited`, same agent and visa vendor
- **Transport:** Specialized

1. **Import** `mutamer-list-2-passengers.xlsx` (2 rows).
2. **Add two more passengers by hand**, adults. Four in total.
3. **One vehicle:** fare `Full journey — Coaster`, quantity `1`, passengers
   `4`, terminal Standard, **with a Schedule set**.

| | |
|---|---|
| Visa | 3,600 sale / 3,000 cost |
| Transport | 2,400 sale / 1,800 cost |
| Total | **6,000 sale / 4,800 cost** |

Write down Group D's totals before going further.

**Now edit it.** This is the feature that shipped yesterday and the
highest-value test in this part.

`/<company>/umrah/groups/{D}/edit` — change the vehicle **quantity from 1 to
2**, save.

Expected:

- Transport sale **4,800**, transport cost **3,600**
- Total sale **8,400**, total cost **6,600**
- The group's **accounting tab** shows rolled-up totals, not a list of journal
  entries, so read the change rather than looking for two new rows: sale rises
  by exactly 2,400 and cost by exactly 1,800.
- *Fail:* the totals move by the full new amount rather than the difference, or
  they do not move at all.

Three more edits on Group D, checking the accounting tab after each:

1. **Add a second vehicle** — `Makkah to Madinah — Hiace`, quantity 1,
   passengers 4. Transport sale should rise by 240 (60 × 4), cost by 180.
2. **Remove it again.** The same amounts should reverse.
3. **Move the remaining vehicles to another supplier** — switch the fare from
   `Full journey — Coaster` (Haramain) to `Full journey — Own fleet` (Own
   Fleet Transport). The sale is the same, the cost drops from 1,800 to 1,500
   per vehicle — 3,600 to **3,000** across the two — and, the part that
   matters, **Haramain's payable should fall and Own Fleet's should rise**.
   Check both on `/<company>/umrah/transport-providers`.

Finally, try to save Group D with **no vehicles at all**.
*Expect:* a refusal saying a specialized group must keep at least one vehicle,
and pointing you at self-arranged transport instead. Money unchanged.

### One import check worth doing

Open any imported passenger and look at the **visa status**. Every imported row
lands as `received`, including the six that the sheet marks "Visa Not Issued".
If that is wrong, it is wrong for every group built from a file.

**Stop point.**

---

## Part 5 — vouchers and hotels

Hotels reach a group through vouchers, not through the group form.

The form does not have one bundle selector. **Services are chosen per
passenger**, and whether a hotel is company-supplied or self-arranged is set
**per stay**. It also opens with **three itinerary rows** — delete the ones you
do not use, or a half-filled row is saved with a stray date on it.

1. `/<company>/umrah/vouchers/create`, group **QA-C Specialized**.
2. Assign **3 of the 6 passengers**, with visa, transport and hotel services
   against each.
3. Two hotel stays, both **company-supplied**:
   - `Dar Al-Eiman Makkah`, Quad, 1 room, three nights
   - `Al-Haram Madinah`, Quad, 1 room, two nights
   - **Delete the unused third row.**
4. Fill in an onward and a return flight leg — airline, flight number, both
   cities, takeoff and landing.
   - Type the dates straight into the field as `dd/MM/yyyy HH:mm` and press
     Enter or Tab. The value stays put when you move to the next field; if a
     date you entered disappears, that is a regression and worth logging
     immediately.
5. Save as a **draft**, reopen it, confirm everything came back.
   - *Expect:* each company-supplied stay reads **"Priced on approval"** and
     shows its **night count** — 3 and 2. A draft carries no amounts on
     purpose; rates are taken at approval.
   - *Fail:* `0.00` where the charge should be, or a night count of 0 against
     two real dates.
6. **Approve** it. On `/<company>/umrah/vouchers/{id}/accounting` the hotel
   charge should now be on the ledger and Anwar Hospitality should have a
   payable.
7. **Download the voucher PDF.** Passenger names, hotel names, agent name,
   night counts and both flight legs should all be present.

Then the lifecycle:

8. **Amend** the approved voucher — change a room count. The accounting should
   move by the difference, exactly as the group did.
9. Create a **second voucher** for the other 3 passengers, no hotel stays.
10. **Move a passenger** from voucher 2 to voucher 1, then **separate** one
    passenger out of voucher 1 into their own voucher.
11. **Cancel** the separated voucher. Its charges should reverse and the other
    vouchers stay untouched.

> **Watch for:** a passenger appearing on two vouchers at once, or a cancelled
> voucher still carrying a balance.

**Stop point.**

---

## Part 6 — tickets

1. `/<company>/umrah/tickets/create`.
2. **Sold via agent:** Sahil Travel Network.
   - *Expect:* the Buyer field fills itself with the agent's account, greys
     out, and reads "Billed to this agent's linked account."
3. Click **"Clear agent, bill a walk-in customer instead."**
   - *Expect:* Buyer becomes **editable and empty**.
   - This was broken until yesterday — it used to leave the agent sitting in
     the field. Confirm the fix.
4. Re-select the agent. **Supplier:** Skyline Ticketing. **PNR:** anything.
5. **Two tickets:**

| | Ticket 1 | Ticket 2 |
|---|---|---|
| Airline | SV | PK |
| Route | LHE-JED | KHI-JED |
| Gross fare | `1800` | `1600` |
| Taxes | `200` | `180` |
| Service fee | `100` | `100` |
| Supplier cost | `1700` | `1500` |
| Currency | SAR | SAR |

6. **Submit.** Expected: an invoice for **3,980** against the agent's customer
   (1,800 + 200 + 100 + 1,600 + 180 + 100), and a supplier bill for **3,200**.
7. Check the agent's customer at `/<company>/customers` — the invoice should be
   on that one account, and there should be exactly one customer with that name.
8. **Cancel ticket 2.** Buyer returns `1,500`, supplier returns `1,400`.
   - Expected: a credit note for 1,500 against the invoice and a vendor credit
     for 1,400 against the bill. The customer's balance drops by 1,500, not by
     the full ticket.
   - The buyer return is a number **you type** — there is no automatic fee
     retention. If you return less than the ticket, the difference stays
     owed, and that is correct.

**Stop point.**

---

## Part 7 — money, both directions

### In, from the agent

1. `/<company>/umrah/payments/create`, direction **in**, party Sahil Travel
   Network, **10,500 SAR** from your bank account.
2. **Allocate 10,000 of it:** 3,600 to Group A, 6,000 to Group B, 400 to Group
   C. That leaves **500 unallocated on purpose** — the refund below needs it.
3. Group A should now read fully paid; Groups B and C partly, with B owed 740
   and C owed 8,060. The agent's customer balance drops by 10,500.
4. Try to allocate **more than the payment** — add another 1,000 somewhere.
   Expect a refusal, or the save button to stay disabled.
5. **Download the payment receipt PDF.** Agent name, amount, allocations.

### Out, to vendors

6. Out to **Bab Al-Salam Visa Services**, 9,000 SAR from bank.
7. Out to **Haramain Coach Company**, 2,000 SAR from cash.
8. Out to **Anwar Hospitality**, 1,500 SAR from bank.
9. Each vendor's payable should fall by what you paid. Check their detail
   pages, and download a **vendor statement PDF** for one.
10. **Reverse** the Haramain payment. Their payable back up by 2,000, the cash
    account restored, and the payment kept visible as **Reversed** with its
    reason rather than deleted.

### A payment in a second currency

11. Out to **Anwar Hospitality**, **100,000 PKR**, exchange rate `0.0125`.
    Expected base amount **1,250 SAR** — base is amount × rate. Check the
    vendor's balance moved by 1,250, not by 100,000. Reverse it afterwards.
    - **Not Skyline Ticketing.** An umrah payment pays an umrah vendor: visa,
      transport or hotel. The ticket supplier is an accounting vendor and its
      bill is settled through Accounting, not this screen. Its absence from
      the picker is correct.

### Refunds

> A refund pays back money the agent has **overpaid**, so it works from the
> unallocated part of a receipt and has no group selector. That is why step 2
> left 500 sitting unallocated — allocate the whole receipt and there is
> nothing to refund.

12. `/<company>/umrah/refunds/create` — party **agent** Sahil Travel Network,
    **500 SAR**, a reason, and a **service**: hotel, ticket or transport.
    - The service says which part of the package came back. Refunds are
      partial by nature — one passenger out of a group not travelling is the
      ordinary case.
    - **Visa is not offered, on purpose.** A group is built after the visas
      come back approved and only the approved ones are imported, so by the
      time a group exists there is no visa left to refund. The form says so if
      you go looking for it.
13. **Approve**, then **settle** it. The money and the agent's balance should
    move only on settlement, not on approval.
14. Try a second refund for another 500. Expect a refusal: the advance is spent.
15. Create one more and **reject** it. Nothing should post.
    - *There is no Cancel on a requested refund, and that is correct.*
      Cancelling reverses posted ledger entries, so it only appears once a
      refund has been approved. **Reject** is the action on a requested one.

### Expenses

15. `/<company>/umrah/expenses/create` — 100,000 PKR at 0.0125, paid from cash.
    Expect **1,250 SAR** on the ledger.
16. **Reverse** it. Expect the cash account restored and the record kept.

**Stop point.** Every kind of money the module handles has now moved.

---

## Part 8 — reports, with data in them

**There is no `/umrah/reports` index — that URL 404s.** Open a report from the
**Reports** section of the sidebar, which lists ten of them. The remaining
three — Ticket Sales, Ticket Supplier Reconciliation and Ticket Cancellations —
are not in the sidebar at all; reach them from the **Report dropdown at the top
of any report page**, which lists every report you have permission to see.

Set the date range to the whole of 2026 on each — the default is the current
month and most of your data may sit outside it. Every report should now return
rows. For each: check the names render, check the totals against what you
built, and **download the PDF**.

| Report | Where | What it should show |
|---|---|---|
| Group Profitability | sidebar | Four groups; margins 600 / 1,210 / 1,690 / Group D as edited |
| Agent Statement | sidebar | Charges from four groups **and the ticket booking's invoice**, the 10,500 receipt, and the 500 refund on its own line under **Refunded**, naming the service it paid back |
| Receivable Aging | sidebar | The agent's outstanding balance |
| Vendor Payable Aging | sidebar | All four suppliers; Haramain's payable restored after the reversal |
| Advances and Allocations | sidebar | The 10,500 receipt, its three allocations, and the 500 that was refunded |
| Passenger and Visa Status | sidebar | All 21 passengers, agent and vendor columns populated |
| Departure Manifest | sidebar | Passengers by travel date — **needs Part 5's voucher approved**, it reads flight itineraries |
| Hotel Rooming List | sidebar | The Group C voucher's rooms, with 3 and 2 nights against them |
| Transport Dispatch | sidebar | The scheduled vehicles from Groups C and D, with drivers — **empty unless you set schedules in Part 4** |
| Voucher Control | sidebar | The vouchers from Part 5, including the cancelled one |
| Ticket Sales | Report dropdown | The booking, both tickets, agent and supplier named |
| Ticket Supplier Reconciliation | Report dropdown | Skyline Ticketing; the 2350 clearing balance must read **zero** |
| Ticket Cancellations | Report dropdown | Cancelled ticket 2 — buyer 1,500, supplier 1,400 |

Also: use the **agent filter** where a report has one — with only one agent it
should change nothing, but it must not empty the report. Check
`/<company>/umrah/reports/earnings`. And open each group's **accounting tab**
one final time to confirm the totals still match Part 4.

---

## Part 9 — the agent's own view

Log out. Log back in as `sahil_agent` / `qa-password`.

- *Expect:* the four groups, the payments and the refunds that belong to this
  agent, and nothing else.
- Reports: only the self-service ones (agent statement, passenger status,
  departure manifest, hotel rooming, voucher control). The financial reports
  should not be offered.
- Cost figures and margins should be **hidden** — an agent sees what they owe,
  not what the company paid.
- Open **QA-C Specialized**. Its payment status must read **Partly paid**: 400
  against 8,460. It used to read Paid here, because the page worked the status
  out from a balance an agent is never sent. Receivable and balance stay hidden
  as dashes; the status beside them must still be right.
- *Fail:* a cost amount, a vendor cost, or another company's data visible
  anywhere.

Try creating a voucher as the agent — allowed, within the 24-hour cutoff you
set in Part 2. Try editing a group whose travel date has passed. Note what
happens.

---

## When you are done

The two things worth reporting even if nothing broke:

1. **Any total that disagreed with this document.** If the document is wrong,
   that is worth knowing too — say which number you got.
2. **Anywhere the module let you do something that should not be possible**:
   allocating more than a payment, a voucher with no passengers, a specialized
   group with no vehicles, an agent seeing costs.

Clean-up is optional — the company is local and can be deleted at
`/companies`.
