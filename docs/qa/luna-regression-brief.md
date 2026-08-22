# Luna — full regression brief

One pass, all findings reported together. Work top to bottom; Section A is the
one that matters most, because it is the only feature here that was never
reachable before today.

---

## 0. Before you start

**Environment**

1. **Work at `http://localhost:9001`.** Both servers are already up — the app on
   9001, Vite serving assets on 5173. Earlier drafts of this brief and
   `CLAUDE.md` said 5180; that was wrong, and there is a stale listener on 5180
   that answers every request with a 404. If a page 404s, check the port first.
2. The migration this work needs (`2026_08_22_000001_add_refund_id_to_payment_allocations`)
   is already applied to the dev database. You do not need to migrate.
3. Test company: **demo-babalsalam-travel**. Base currency **PKR**.

**Sign-ins.** One per role, all on the travel demo company, all with the
password **`demo-password`**. These are local dev fixtures and exist only in
this database.

| Role | Email | Can do |
|---|---|---|
| Owner | `demo@haasib.app` | everything |
| Manager | `manager@demo.haasib.app` | request **and** decide refunds |
| Accountant | `accountant@demo.haasib.app` | request **and** decide refunds |
| Operations | `operations@demo.haasib.app` | request only |
| Agent | `agent@demo.haasib.app` | request only — **is** Al-Noor Travels |

The agent seat is Al-Noor Travels itself, which is the agent holding the
250,000 advance below. That pairing is deliberate: it is the only agent with
money behind them, so it is the only one whose refund can be approved.

**You have standing permission to change data.** This is a local dev database
seeded from scratch and nothing in it is real. Create, edit, approve, cancel and
delete whatever a test needs — new agents, groups, payments, refunds, even new
users — without stopping to ask first. Rebuilding it is one command. The only
thing worth flagging is data you could **not** create when you should have been
able to; that is a finding, not a blocker. Do not stop a run to request
approval.

If a seat ever stops working, re-run
`php artisan db:seed --class="Database\Seeders\Demo\DemoRoleUsersSeeder" --force`
— it resets all four passwords and re-grants the roles.

**Browser validation is now switched off across the app.** Every form carries
`novalidate`, so the browser will no longer block a submit for a blank or
malformed field. This is deliberate and it is for you: it means you can submit
bad input and see what the *server* says. Do that everywhere. An error must
appear inline, next to the field it belongs to — not only as a toast, and never
as a silent no-op.

**Seed data you will need in Section A**

| Agent | Owes | Unallocated advance (spendable credit) |
|---|---|---|
| Al-Noor Travels | 0 | **PKR 250,000** — payment `UPM-00018`, ref `ADV-ALNOOR-2026-08` |
| Madina Tours | 397,500 | 0 |
| Rehmat Travel Service | 1,469,875 | 0 |
| QA Agent 2026 | 0 | 1.00 |

That 250,000 advance was added today. Before it, no agent in this database had
overpaid by so much as a rupee, and since a refund's ceiling is exactly what an
agent paid in excess of what they owe, every refund approval would have been
refused. **Al-Noor Travels is the agent to run the refund scenarios against.**
Madina Tours and Rehmat are the agents to use when you want to confirm a refund
is correctly *refused*.

---

## 1. Do not report these — already known

**Closed since your last round.** Items 1, 2 and 5 from your previous report are
fixed. What you flagged as "critical database corruption" was not corruption;
the data was correct and read wrongly. Please don't re-file any of these.

**Known open, already logged, fix pending.** If you hit these, skip them:

- `FuelStation → Sales → Form` indexes `[0]` into error strings as if they were
  arrays, so a server error there renders as a single character.
- `AmountInput` carries its own hardcoded currency-symbol table, separate from
  the app's. Symbols may disagree between an input field and a displayed amount.
- A currency code is appended to the symbol (e.g. `Rs PKR`) for any currency in
  a known-ambiguous group, even when nothing on the page is actually ambiguous.
- `UPM-00017` — your stray PKR 1.00 QA payment from last round is still in the
  database. Leave it. It is harmless and we have not reversed it.

---

## Section A — Refunds, end to end

This is a new feature and the largest surface. Refunds live at
`/{company}/umrah/refunds`.

**Who can do what.** Agent and operations roles can *request* a refund but not
decide one. Manager, accountant and owner can approve, reject, cancel and settle.
Test both sides.

**The lifecycle you are verifying.** Requested → Accepted → then either Refunded
(money paid back) or Kept as credit (money stays with the agent to spend later).
Rejected and Cancelled are the two ways out. The status wording on screen should
be exactly those words.

### A1 — An agent asks for a refund
Sign in as an agent-role user for Al-Noor Travels. Request a refund of
**PKR 100,000**. Expect: it saves, status reads **Requested**, and the record
names who requested it and when. Confirm the agent has no approve/reject control
anywhere on the page.

### A2 — A manager accepts it
Sign in as manager or accountant. Approve the request. Expect status **Accepted**.

**Then go straight to `/{company}/umrah/payments` and find `UPM-00018`.** This is
the single most important check in the whole brief:

- Its **Available** figure must have dropped from 250,000 to **150,000**.
- Its allocation column must now name the refund (a link to the refund record),
  alongside any groups it is applied to.

If Available is still 250,000, the money has been promised back and is still
showing as spendable — report that as critical. This is the exact defect that was
fixed today, so it is the one most worth confirming stayed fixed.

### A3 — Settle it as cash
Settle with **Pay it back**, choosing a bank account and a date. Expect status
**Refunded**. `UPM-00018` Available must still read 150,000 — settling moves the
money out, it must not give any back.

### A4 — Settle a different one as credit
Request and approve a second refund for Al-Noor, this time **PKR 50,000**, and
settle it with **Keep as credit**. Expect status **Kept as credit**.

Now on the Payments page there should be a **new payment row** for Al-Noor: the
credit, unallocated, for 50,000. The refund record should link to it.

Check the arithmetic across the whole Payments page for Al-Noor:
`UPM-00018` Available (100,000 after both refunds) + the new credit (50,000)
= **150,000**. The agent originally paid 250,000 and 100,000 was paid back to
them, so 150,000 is right. **If the total exceeds 150,000, money has been
duplicated — report it as critical and tell us the exact figures.**

### A5 — Spend the credit
Allocate the new 50,000 credit to a visa group that Al-Noor owes money on.
Expect it to apply, and the group's paid figure to rise by 50,000. Then try to
allocate the *same* payment again — it must refuse, with a readable message.

### A6 — Ask for credit back
Request a further refund for Al-Noor for an amount **larger than what is left
unspent**. It must be refused at approval with an inline message about exceeding
available credit. Then request one for an amount **within** what is left; that
one must approve.

### A7 — Refuse a refund with nothing behind it
Request a refund for **Madina Tours** (who owes money and has no advance) for any
amount. Approval must be refused. This is correct behaviour, not a bug — you are
confirming the refusal happens and that the message explains why.

### A8 — Cancel an accepted refund
Request and approve a refund for Al-Noor, then cancel it with a reason. Expect
status **Cancelled**, and — importantly — the advance's **Available** figure must
go back **up** by the cancelled amount. Cancelling un-promises the money.

Also confirm: a refund already settled (Refunded or Kept as credit) **cannot** be
cancelled. The control should be absent or refuse.

### A9 — A refund tied to a specific group
Create a refund against a group Al-Noor has already paid into. On approval the
existing allocation should be reversed and the remainder re-applied, so the
group's paid figure drops by exactly the refund amount and the payment shows both
the reduced group allocation and the refund draw.

### A10 — Scope and permissions
- An agent user must see **only their own** refunds in the list.
- An agent must not be able to open another agent's refund by pasting its URL.
- A user without approve permission must not be able to approve, including by
  crafting the request directly.

### A11 — Vendor refunds
Request a refund against a **vendor** rather than an agent. **Keep as credit must
not be offered** — that settlement only exists for agents. Confirm the option is
genuinely absent, not merely hidden.

### A12 — The books
For any refund you take all the way through, open the linked accounting
transaction. Debits must equal credits on every one. Report any transaction where
they do not, with the transaction number.

---

## Section B — Currency display

The rule that changed: **the company's own base currency is no longer announced
on screen.** An amount in PKR, in a PKR company, shows as a bare number. Only a
*foreign* amount carries a marker, and that marker is the **symbol**, not the
three-letter code.

Check across Invoices, Bills, Payments, Journals, the Umrah pages and the
dashboard:

- **B1** — No `PKR` appears next to ordinary amounts anywhere on screen.
- **B2** — A foreign-currency amount (e.g. SAR, USD) does carry a symbol.
- **B3** — **Exported and printed documents are the exception.** Open an invoice,
  bill or Umrah voucher document and confirm it states its currency **once**, in
  the masthead, reading `Currency: PKR`. A document leaves the app and loses the
  context that makes a bare figure readable, so it must say so — but only once,
  not on every line.
- **B4** — Any place still printing a currency code inline next to a base-currency
  amount is a bug. Tell us the page and the field.

---

## Section C — Status wording

Every record state should look and read the same wherever it appears — the same
chip, the same words. Twelve pages were converted to this. Walk them and report
any state that still renders as bare text, a filled-in coloured pill, or a
different word for the same thing:

Partners · Bills · Posting templates · Vendor credits · Credit customers ·
Fuel receipts · Investors · Pumps · Stock · Employees · Payslips · Payroll periods

Two specific things to confirm:

- **C1** — **Low stock** must read as amber (attention), not red. Running low is
  something needing a person, not a failure.
- **C2** — Every state must be legible **without colour**. Squint or view in
  greyscale: the word alone should tell you the state. A cancelled or reversed
  record must additionally be struck through.

---

## Section D — Form validation

Because browser validation is off, the server is now the only thing standing
between bad input and the database. Across **every form you can reach**, submit:

- **D1** — Completely empty.
- **D2** — Required fields blank but others filled.
- **D3** — A negative amount, and a zero amount.
- **D4** — Text in a number field; an impossible date (e.g. 31 February).
- **D5** — An amount with more decimal places than the currency allows.
- **D6** — A very long string (500+ characters) in a short text field.

Every one must produce a **readable message attached to the field that caused
it**. Report anything that: silently does nothing, throws a 500, shows a raw
framework exception, saves bad data, or reports the error only in a toast with no
indication of which field is wrong.

---

## Section E — Dashboard

Sign in as each of **owner, manager, accountant, operations, agent** and check the
dashboard tabs each role gets.

- **E1** — Owner and manager: an **Upcoming** tab and a **Money** tab. Accountant:
  Money only. Operations and agent: Upcoming only.
- **E2** — On Money: Cash position, Refunds awaiting decision, Cash book, Agent
  balances, Vendor balances. All should render with real data, not empty frames.
- **E3** — **Refunds awaiting decision** should list the refunds you leave in
  Requested state during Section A. Leave one unapproved and confirm it appears.
- **E4** — An agent's dashboard must show only their own figures.

---

## Section F — Look and layout

- **F1** — **An ordinary outgoing amount must never be red.** Money going out is
  ink with a minus sign. Red is reserved for genuinely adverse things — overdue,
  errors. Amber means something needs a person. Report any page painting normal
  outflow, or a normal fuel dispensing movement, as an alarm.
- **F2** — Amounts right-aligned, decimals lined up in a column, never wrapping.
- **F3** — At 320px wide and at 200% zoom: the page itself must not scroll
  sideways. Wide tables may scroll inside their own box.
- **F4** — Dark theme on every page you visit. Report anything unreadable.
- **F5** — Tab through a form and a table: the focus outline must be visible on
  every control at every step.

---

## How to report

One list, everything together. For each finding:

1. The exact URL and the role you were signed in as.
2. What you did, in steps someone can repeat.
3. What you expected, and what happened.
4. For anything involving money: **the actual figures**, and the payment or
   refund number. A number we can check beats a description we have to interpret.

Flag as **critical** only: money that duplicates or disappears, a transaction
whose debits and credits do not match, or one user seeing another's data.
Everything else is ordinary.
