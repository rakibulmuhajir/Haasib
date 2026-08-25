# QA walkthrough — agents and umrah vendors as parties

For Luna. Written 2026-08-25, against production `91faf107` plus the one fix
described in "Before you start".

## What changed, in one paragraph

An umrah **agent** used to hold its own name, phone, email and logo. So did an
umrah **vendor**. Those are the same four facts the accounting side already
kept for a customer and a supplier, so one party could exist twice with two
balances that disagreed. As of yesterday's deploy an agent *is* a customer with
umrah extras attached, and an umrah vendor *is* a supplier with umrah extras
attached. The four fields now come from the accounting record.

Nothing about this should be visible to you. That is the point of the test: the
names, phones and emails should read exactly as they did before, everywhere. A
blank where a name belongs is the failure this walkthrough is hunting.

## Before you start

- Work in the client's real company — `emar-al-khair` — not the demo company
  and not a scratch one. The bug this hunts only appears on rows that existed
  before the migration, and only that company has them. If the agent dropdown
  shows Al-Noor Travels, Madina Tours or Rehmat Travel Service, you are in the
  demo company and nothing you find here counts.
- Have two browser tabs: the app, and this page.

## How to report anything you find

For each problem: the URL, what you expected, what you saw, and a screenshot.
Three specific failure shapes matter more than usual here, so name which one it
is:

| What you see | What it means |
|---|---|
| A name is blank, `—`, `null`, or "Unnamed" | The party link did not resolve |
| A name is *wrong* — someone else's | Two records got linked to one party |
| A page 500s or shows "Server Error" | A query is still asking for a deleted column |

The third is the most serious and the least likely to be your fault. Log it and
move on rather than retrying.

---

## Part A — the agent-linked ticket booking

**This is the highest-value part of the walkthrough.** This path was completely
broken before yesterday and nobody has ever completed it successfully on real
data. Do it first and do it carefully.

Before the fix, every agent had no linked customer, so the form left the Buyer
field empty and disabled, and submitting failed with "The customer id field is
required." All fourteen agents now have a customer.

1. Go to **Umrah → Tickets → New booking**.
2. Open the **"Sold via agent"** dropdown.
   - *Expect:* all fourteen agents listed, each with a readable name.
   - *Fail:* empty dropdown, or entries that are blank or show a bare ID.
3. Pick an agent.
   - *Expect:* the **Buyer** field immediately fills itself with that agent's
     account, greys out, and the helper text reads "Billed to this agent's
     linked account."
   - *Fail:* Buyer stays empty, or greys out while still empty. **Stop and log
     it** — nothing past this point will work.
4. Click **"Clear agent, bill a walk-in customer instead."**
   - *Expect:* Buyer becomes editable and empty; you can pick any customer.
5. Re-select the agent and fill in the rest of the booking: supplier, at least
   one ticket with a fare, passenger details.
6. **Submit.**
   - *Expect:* it saves and lands on the booking page.
   - *Fail:* "The customer id field is required" — this is the original bug,
     unfixed. Log it with the agent's name.
7. On the saved booking page, check the agent's name and the supplier's name
   both display.
8. Repeat steps 1–7 with **two more agents**, ideally ones with unusual names
   (long, non-Latin script, punctuation). Fourteen agents were migrated by a
   script; if one of them landed on the wrong customer, comparing three is how
   it surfaces.
9. Go to **Umrah → Tickets** (the list) and confirm all three bookings appear
   with the right agent against each.

Then the accounting half, which is what the whole refactor was for:

10. Open **Customers** at `/<company>/customers`. Find the customer belonging to the agent
    you booked for.
    - *Expect:* the ticket you just sold appears on their account. One agent,
      one customer, one balance.
    - *Fail:* two customers with that agent's name, or the balance sitting on
      one while the ticket sits on the other.

---

## Part B — names, everywhere they appear

Fast pass. You are not testing the features, only that every agent name and
every umrah vendor name renders. Open each screen, scan for blanks, move on.

**Agents**

- Umrah → Agents (list) — every row named
- An agent's detail page — name, phone, email in the header
- An agent's edit page — the same three, filled in, editable
- Edit an agent's name, save, and reopen. The new name sticks, and the matching
  customer at `/<company>/customers` shows it too.
- Create a brand-new agent. It saves, and a customer appears for it in
  `/<company>/customers`.

**Umrah vendors**

- Umrah → Vendors (list) — every row named
- A vendor's detail page
- A vendor's **statement PDF** (the download on the detail page)
- Umrah → Transport Providers — list, a detail page, and a statement PDF
- Edit a vendor's name and confirm it follows through to `/<company>/vendors`
- Create a new vendor and confirm the same

**Everywhere they are named alongside something else**

- Umrah dashboard — every widget, especially Agent Balances, Vendor Balances,
  Cash Book, Departures, Needs Attention, Transport Readiness
- Groups: list, a group's detail page, its accounting tab, the edit page
- Payments: list, new payment (check the agent and vendor pickers), a payment's
  detail page, a payment receipt PDF
- Refunds: list, new refund, a refund's detail page
- Vouchers: list, a voucher, a voucher PDF
- Tickets: list, a booking's detail page

Pay particular attention to the **pickers** — the agent and vendor dropdowns on
the payment, refund, group and voucher forms. They fetch a narrowed set of
columns, which is exactly the shape that breaks the party link.

---

## Part C — reports

**Umrah → Reports.** Open each one with a date range wide enough to return
rows — the whole of 2026 is fine. An empty report proves nothing, so if one
comes back with no rows, widen the dates until it has some.

| Report | What to check |
|---|---|
| Passenger and Visa Status | Agent and Vendor columns populated |
| Agent Statement | Pick a specific agent; their name in the header and rows |
| Group Profitability | Agent column |
| Receivable Aging | Agent names |
| Vendor Payable Aging | Vendor names |
| Advances and Allocations | Both |
| Departure Manifest | Agent |
| Hotel Rooming List | Renders at all |
| Transport Dispatch | Vendor names |
| Voucher Control | Agent |
| Ticket Sales | Agent, supplier |
| Ticket Supplier Reconciliation | Supplier names; the 2350 clearing balance must read **zero** |
| Ticket Cancellations | Agent, supplier |

For each report also click **Download PDF**. The PDF is built from the same
data as the screen but renders separately, so a name can be present on one and
missing on the other.

Also try the **agent filter** at the top of the reports that have one: choosing
an agent by name should narrow the results to that agent.

---

## If you have time left

Log in as an **agent-role user** (one whose account is linked to an agent, not
a staff member) and confirm they still see only their own groups, payments and
reports. The agent-to-user link was not what changed, but it sits one column
away from what did.
