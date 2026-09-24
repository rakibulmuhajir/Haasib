# Fuel Station E2E — Controlled Test Script

Two trading weeks, 1–14 March 2026.

Every figure below is fixed in advance. Type exactly what is written; the expected result
after each day is stated, so a mismatch is a defect rather than a judgement call.

**The point of this script is the integration, not the form.** Only three things genuinely
belong to the daily close — meter readings, tank dips, and the cash count. Everything else is
posted where it actually happens: a bank deposit at the banking screen, an expense at the
expense screen, a trust deposit on the amanat page, a credit sale on the sale form. The close
must then pick each of them up **automatically, exactly once**, for that business date.

Entering everything into the close would only prove the close's own form works. It would not
prove the thing the module is actually built on.

---

## How this run is to be done

**Through the browser, by hand. Not through the service.**

There are two ways this week has been put through the software and they answer different
questions. `SevenDayOperationsTest` calls `DailyCloseService` directly and passes 10/10 - the
posting engine is proven and does not need proving again. Nothing in this document is about
the engine. This run exists to test the **screens**, and the only way to test a screen is to
use it.

So: no tinker, no artisan, no calling a service to "save time" on a day that is tedious to
enter. A day entered any way other than through the UI has not been tested, and recording it
as passed is worse than skipping it.

**Go straight through to day 14 without a break.** The days chain - each opening cash is the
previous day's closing - so a day entered wrongly invalidates every day after it. A run that
stops at day 5 and resumes tomorrow is a run whose second half is testing yesterday's
mistakes.

### The four rules a previous run broke

Every one of these produced a day that looked fine and proved nothing.

1. **Do the "before the close" steps first, on their own pages.** Day 3 wants a delivery bill,
   an electricity expense and a standalone credit sale entered *elsewhere*; day 4 wants its
   200,000 banked at the bank screen. Those four steps are most of the integration this script
   exists to test. When they were skipped, day 3's closing cash came out exactly 74,000 high -
   42,000 + 2,000 + 30,000 - and the close was right; the setup was missing.

2. **Never type the app's expected figure into the counted-cash box.** That forces the variance
   to zero and the day proves nothing. Count what the script says was counted, and if the
   variance is not what the script predicts, that is the finding - write it down, do not adjust
   the count until it matches.

3. **Never use the form's test-seed button.** Days 4 and 5 of a previous run were filled with
   it, which is why their expenses read "Test station expense" and their deposits bore no
   relation to the script.

4. **Check the buyer on every credit sale.** A previous run put all three credit sales against
   Al-Habib Transport because the name was picked from a stale "recent" list. The close stores
   faithfully whatever it is handed.

### Before you start

- Rebuild the frontend bundle, or you will be testing code that is no longer there. A stale
  Vite build is what made the buyer search appear broken.
- Clear any saved draft for the dates you are about to enter. The form autosaves to
  `localStorage` under `daily-close-draft-<companyId>-<date>`, so a reload restores whatever
  was last on screen - including a wrong figure you were about to correct.
- Re-seed. It purges its own company, so the week starts clean.

### Recording the result

For each day write down: the four expected figures from the script, what the screen actually
showed, and whether they match. A day is **passed** only if it was entered as specified and
the figures agree. If something is entered wrongly, say so and mark the day invalid - an
invalid day is a useful fact, a day silently adjusted until it balanced is not.

---

## 0. Setup

```powershell
cd D:\projects\haasib\build
php artisan db:seed --force --class='Database\Seeders\ScenarioFuelStationSeeder'
```

Idempotent — it purges its own company first, so re-run it to reset the week.

| | |
|---|---|
| URL | `http://localhost:8000` (`php artisan serve`) |
| Login | `scenario@haasib.test` / `scenario-password` |
| Company | **Mehran Filling Station** — `/scenario-mehran-fuel` |

The seeder stops on the morning of 1 March 2026 with **no closes posted**.

### What the close imports, and from where

| Entered at | Picked up by the close as |
|---|---|
| `/banking/transactions` | Cash moved to or from a bank on that date |
| `/expenses` | Cash paid out of the drawer |
| `/fuel/amanat` | Trust money received or repaid |
| `/fuel/sales/form` (credit) | A receivable against that day's metered litres |
| `/fuel/receipts` | Stock into the tank, and a payable to the depot |
| `/payments` | A buyer settling an invoice |
| Payroll | Approved payslips paid out |

Each of these already has a unit test proving the pickup. This script proves it through the
screens, which is where it actually has to work.

### Fixed reference data

**Tanks** — Tank 1 Petrol (30,000 L), Tank 2 Diesel (25,000 L)
**Opening stock** — Petrol 18,000 L, Diesel 12,000 L, **cash 50,000.00**, bank 2,000,000.00 (HBL)

| Nozzle | Fuel | Opening meter |
|---|---|---|
| P1A | Petrol | 100,000 |
| P1B | Petrol | 200,000 |
| D2A | Diesel | 300,000 |
| D2B | Diesel | 400,000 |

**Rates** — Petrol 300.00 / Diesel 310.00 until 3 March; from **4 March** 306.00 / 314.00
**Cost** — Petrol 292.00 · Diesel 302.00
**Lubricants** — Mobil Super 4L @ 2,400.00/unit · Open Engine Oil @ 1,100.00/L (both open at zero)
**Banks** — HBL Current · Meezan Current · UBL Savings
**Buyers** — Al-Habib Transport · Sindh Goods Carriers · Mehran Logistics · Karachi Cement
Haulage · Indus Travel · Pak Freight Lines (15-day terms, limit 200,000)
**Suppliers** — PSO Depot — Korangi · Mobil Distributor — Karachi · Al-Noor Maintenance
**Amanat holder** — Karachi Cement Haulage

### How the daily close screen works

Five tabs — **Meter Sales · Tank Dip · Cash In · Cash Out · Review & Post**. Each has its own
Save button and **all four must be saved before Post is accepted**; Review shows
`n/4 sections saved`. Set the business date first.

Anything already posted elsewhere for that date should **appear on the close ready-made**. Do
not type it in again — a figure you have to enter twice is the bug this script is looking for.

---

## Day 1 — Sunday 1 March · everything in the close

The baseline. Nothing posted elsewhere, so the close stands alone.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 100000 | 100350 | 300.00 |
| P1B | 200000 | 200350 | 300.00 |
| D2A | 300000 | 300250 | 310.00 |
| D2B | 400000 | 400250 | 310.00 |

Other sales: Mobil Super 4L × **4** · Open Engine Oil × **3** L

**Tank Dip** — Petrol **17298.0** (stick 432.4) · Diesel **11498.5** (stick 287.5)
**Cash In** — Opening cash **50000**
**Cash Out** — Credit: Al-Habib Transport **40000** · Food & Tea "Staff tea" **600** ·
Food & Tea "Attendant meals" **1400** · Deposit HBL Current **150000**
**Review** — Closing cash **235900** → Post

### Expected
| | |
|---|---|
| Fuel sales | 365,000.00 |
| Lubricants | 12,900.00 |
| **Cash variance** | **0.00** |
| Stock variance | −2.0 L petrol · −1.5 L diesel |

---

## Day 2 — Monday 2 March · amanat posted on its own page

**Before the close** — `/fuel/amanat` → Karachi Cement Haulage → **Deposit 25,000**,
dated 2026-03-02, received into the cash drawer.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 100350 | 100750 | 300.00 |
| P1B | 200350 | 200750 | 300.00 |
| D2A | 300250 | 300550 | 310.00 |
| D2B | 400250 | 400550 | 310.00 |

Other sales: Mobil Super 4L × **2** · Open Engine Oil × **5** L

**Tank Dip** — Petrol **16495.5** (412.4) · Diesel **10897.0** (272.4)
**Cash In** — Opening cash **235900**. ⚠ **The 25,000 amanat should already be listed here.**
Do not add it again.
**Cash Out** — Credit: Sindh Goods Carriers **55000** · tea **600** · meals **1400** ·
Deposit Meezan Current **160000**
**Review** — Closing cash **480200** → Post

### Expected
| | |
|---|---|
| Fuel sales | 426,000.00 |
| **Cash variance** | **0.00** |
| Amanat held | 25,000.00 |

**What this day tests:** the close saw the amanat page's deposit. If the variance is
**−25,000.00**, the close did not pick it up. If it is **+25,000.00**, it was counted twice.

---

## Day 3 — Tuesday 3 March · delivery, expense and a credit sale, all posted elsewhere

**Before the close**, three things:

1. `/fuel/receipts/create` — PSO Depot — Korangi, **10,000 L Petrol @ 292.00** into Tank 1,
   dated 2026-03-03. Total **2,920,000.00** on credit.
2. `/expenses/create` — **Electricity**, "Electricity bill — February", **42,000**,
   paid from Cash Drawer, dated 2026-03-03.
3. `/fuel/sales/form` — **credit** sale to **Al-Habib Transport**: Petrol, **100 L**,
   dated 2026-03-03. At 300.00 that is exactly **30,000.00**.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 100750 | 101075 | 300.00 |
| P1B | 200750 | 201075 | 300.00 |
| D2A | 300550 | 300775 | 310.00 |
| D2B | 400550 | 400775 | 310.00 |

Other sales: Mobil Super 4L × **6** · Open Engine Oil × **2** L

**Tank Dip** — Petrol **25844.0** (646.1) · Diesel **10446.0** (261.1)
**Cash In** — Opening cash **480200**
**Cash Out** — ⚠ The **30,000 Al-Habib invoice** and the **42,000 electricity** should both
already be listed. Add only: Credit **Mehran Logistics 25000** · tea **600** · meals **1400** ·
Deposit UBL Savings **120000**
**Review** — Closing cash **612300** → Post

### Expected
| | |
|---|---|
| Fuel sales | 334,500.00 |
| Credit total for the day | 55,000.00 |
| **Cash variance** | **0.00** |
| Owed to PSO Depot | 2,920,000.00 |

**What this day tests:** three different entry points feeding one close, and a close-entered
credit row (Mehran) sitting alongside an imported one (Al-Habib) without either being lost.

> **Watch the petrol dip variance.** Expected **−1.5 L** — the shrinkage, nothing more.
> A figure near **−101.5 L** means the standalone sale decremented stock a second time, on top
> of the meter reading. That is a real defect worth reporting precisely, not a bad dip.

---

## Day 4 — Wednesday 4 March · rate change, and banking done at the bank screen

**Before the close**, two things:

1. `/fuel/rates` — effective **2026-03-04**: Petrol sale **306.00**, Diesel sale **314.00**.
   Costs unchanged. The rate applies from 00:00, so the whole day sells at the new price.
2. `/banking/transactions/create` — **Deposit**, Cash Drawer → **HBL Current**, **200,000**,
   dated 2026-03-04.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 101075 | 101525 | **306.00** |
| P1B | 201075 | 201525 | **306.00** |
| D2A | 300775 | 301125 | **314.00** |
| D2B | 400775 | 401125 | **314.00** |

Other sales: Mobil Super 4L × **3** · Open Engine Oil × **4** L

**Tank Dip** — Petrol **24941.0** (623.5) · Diesel **9744.0** (243.6)
**Cash In** — Opening cash **612300**
**Cash Out** — ⚠ The **200,000 deposit** should already be listed. Add only:
Credit **Karachi Cement Haulage 60000** · tea **600** · meals **1400**
**Review** — Closing cash **857100** → Post

### Expected — the key check of the week
| | |
|---|---|
| Petrol 900 L × 306.00 | 275,400.00 |
| Diesel 700 L × 314.00 | 219,800.00 |
| Fuel sales | **495,200.00** |
| **Cash variance** | **0.00** |

**If fuel sales read 489,200.00** the rate change did not take effect and the day sold at
yesterday's price. **If the variance reads −200,000.00**, the bank screen's deposit was not
picked up; **+200,000.00** means it was counted twice.

---

## Day 5 — Thursday 5 March · deliberate shortage

Everything in the close, so the shortage has only one possible cause.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 101525 | 101900 | 306.00 |
| P1B | 201525 | 201900 | 306.00 |
| D2A | 301125 | 301400 | 314.00 |
| D2B | 401125 | 401400 | 314.00 |

Other sales: Mobil Super 4L × **5** · Open Engine Oil × **6** L

**Tank Dip** — Petrol **24189.0** (604.7) · Diesel **9192.5** (229.8)
**Cash In** — Opening cash **857100** · Amanat Karachi Cement Haulage **15000**
**Cash Out** — Credit: Sindh Goods Carriers **35000** · Credit: Indus Travel **20000** ·
tea **600** · meals **1400** · Deposit Meezan Current **150000**
**Review** — Closing cash **1085550** → Post

### Expected
| | |
|---|---|
| Expected cash | 1,085,900.00 |
| Counted cash | 1,085,550.00 |
| **Cash variance** | **−350.00** |
| Amanat held | 40,000.00 |

The close must **still post**, and the 350 must land in **6180 Cash Short/Over** — not be
silently absorbed. A register that cannot report a shortage cannot detect theft.

---

## Day 6 — Friday 6 March · second delivery, banking at the bank screen

**Before the close**, two things:

1. `/fuel/receipts/create` — PSO Depot — Korangi, **8,000 L Diesel @ 302.00** into Tank 2,
   dated 2026-03-06. Total **2,416,000.00** on credit.
2. `/banking/transactions/create` — **Deposit**, Cash Drawer → **UBL Savings**, **180,000**,
   dated 2026-03-06.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 101900 | 102325 | 306.00 |
| P1B | 201900 | 202325 | 306.00 |
| D2A | 301400 | 301725 | 314.00 |
| D2B | 401400 | 401725 | 314.00 |

Other sales: Mobil Super 4L × **1** · Open Engine Oil × **3** L

**Tank Dip** — Petrol **23336.5** (583.4) · Diesel **16540.5** (413.5)
**Cash In** — Opening cash **1085550**
**Cash Out** — ⚠ The **180,000 deposit** should already be listed. Add only:
Credit **Pak Freight Lines 45000** · tea **600** · meals **1400**
**Review** — Closing cash **1328450** → Post

### Expected
| | |
|---|---|
| Fuel sales | 464,200.00 |
| **Cash variance** | **0.00** |
| Diesel dip after delivery | 16,540.5 L |
| Owed to PSO Depot | 5,336,000.00 |

---

## Day 7 — Saturday 7 March · amanat repaid on its own page

**Before the close** — `/fuel/amanat` → Karachi Cement Haulage → **Withdraw 10,000**,
dated 2026-03-07, paid from the cash drawer.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 102325 | 102800 | 306.00 |
| P1B | 202325 | 202800 | 306.00 |
| D2A | 301725 | 302125 | 314.00 |
| D2B | 401725 | 402125 | 314.00 |

Other sales: Mobil Super 4L × **7** · Open Engine Oil × **5** L

**Tank Dip** — Petrol **22383.5** (559.6) · Diesel **15738.0** (393.4)
**Cash In** — Opening cash **1328450**
**Cash Out** — ⚠ The **10,000 amanat withdrawal** should already be listed. Add only:
Credit **Mehran Logistics 50000** · tea **600** · meals **1400** · Deposit HBL Current **190000**
**Review** — Closing cash **1640650** → Post

### Expected
| | |
|---|---|
| Fuel sales | 541,900.00 |
| **Cash variance** | **0.00** |
| Amanat held | 30,000.00 |

---

## End-of-week verification

### Daily close history — `/fuel/daily-close/history`
Seven closes, 1–7 March. **Only 5 March shows a variance**, of −350.00.

### Stock
| | Petrol | Diesel |
|---|---|---|
| Sold | 5,600.0 L | 4,250.0 L |
| Received | 10,000.0 L | 8,000.0 L |
| Shrinkage | −16.5 L | −12.0 L |
| **Closing dip** | **22,383.5 L** | **15,738.0 L** |

### Receivables aging — `/reports/receivables-aging`
Total **360,000.00**, all "not yet due" (15-day terms inside a 7-day week).

| Buyer | Owed |
|---|---|
| Sindh Goods Carriers | 90,000.00 |
| Mehran Logistics | 75,000.00 |
| Al-Habib Transport | 70,000.00 |
| Karachi Cement Haulage | 60,000.00 |
| Pak Freight Lines | 45,000.00 |
| Indus Travel | 20,000.00 |

### Supplier — `/vendors` → PSO Depot — Korangi
Closing balance **5,336,000.00**, two bills, no payments. Other two suppliers **0.00**.

### Profit & loss — `/reports/profit-loss`, 1–7 March
Fuel 3,029,000.00 · Lubricants 98,000.00 · Food & Tea 14,000.00 · Electricity 42,000.00 ·
Cash Short/Over 350.00

### Trial balance — `/reports/trial-balance` as at 2026-03-07
Must read **"The books balance."** Difference 0.00.

### Balance sheet — `/reports/balance-sheet` as at 2026-03-07
Must read **"Assets equal liabilities plus equity."** Difference 0.00.

### Amanat — `/fuel/amanat`
Karachi Cement Haulage holds **30,000.00** (25,000 + 15,000 − 10,000).

### Bank balances
HBL 2,000,000 + 150,000 + 200,000 + 190,000 = **2,540,000.00**
Meezan 160,000 + 150,000 = **310,000.00** · UBL 120,000 + 180,000 = **300,000.00**

---

## Week 2 — 8-14 March - payments in, payroll out

Week 1 never moved money **into** the drawer from anything but a sale, and never paid a wage.
Week 2 adds both, plus a payment to the supplier, and carries straight on from where week 1
closed.

**Do not reset between the weeks.** Week 2 opens on week 1's closing position, and that
continuity is itself worth testing:

| | |
|---|---|
| Cash | 1,640,650.00 |
| Petrol dip | 22,383.5 L |
| Diesel dip | 15,738.0 L |
| Meters | P1A 102800 - P1B 202800 - D2A 302125 - D2B 402125 |
| Receivables | 360,000.00 |
| Owed to PSO Depot | 5,336,000.00 |

Rates stay at 306.00 / 314.00 all week - week 1 already tested a revision.

**Staff** (seeded, `/employees`): Muhammad Ali and Ahmed Raza, Pump Attendants; Usman Khan,
Station Manager. Salaries 35,000 + 32,000 + 33,000 = **100,000**.

Every day: tea **600** and meals **1400**, entered in the close.

---

### Day 8 - Sunday 8 March - a buyer settles

**Before the close** - `/payments/create`: **Al-Habib Transport** pays **70,000**, cash,
dated 2026-03-08, against their open invoices. That clears their balance in full.

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 102800 | 103150 |
| P1B | 202800 | 203150 |
| D2A | 302125 | 302375 |
| D2B | 402125 | 402375 |

Lubricants **3** pk, **4** L - Dip Petrol **21681.5**, Diesel **15236.5**
Close: opening cash **1640650**, credit Indus Travel **25000**, deposit HBL **200000**,
closing cash **1866450**

| Expected | |
|---|---|
| Fuel sales | 371,200.00 |
| **Cash variance** | **0.00** |
| Al-Habib balance | **0.00** |

The 70,000 payment should already be listed in Cash In. A variance of **-70,000** means the
close never saw it; **+70,000** means it was counted twice.

---

### Day 9 - Monday 9 March

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 103150 | 103525 |
| P1B | 203150 | 203525 |
| D2A | 302375 | 302650 |
| D2B | 402375 | 402650 |

Lubricants **5** pk, **2** L - Dip Petrol **20929.5**, Diesel **14685.0**
Close: opening **1866450**, no credit sales, deposit Meezan **220000**, closing **2060850**

| Expected | |
|---|---|
| Fuel sales | 402,200.00 |
| **Cash variance** | **0.00** |

---

### Day 10 - Tuesday 10 March - part payment

**Before the close** - `/payments/create`: **Sindh Goods Carriers** pays **50,000**, cash.
They owed 90,000, so 40,000 remains.

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 103525 | 103925 |
| P1B | 203525 | 203925 |
| D2A | 302650 | 302950 |
| D2B | 402650 | 402950 |

Lubricants **2** pk, **6** L - Dip Petrol **20127.0**, Diesel **14083.5**
Close: opening **2060850**, credit Pak Freight Lines **40000**, deposit UBL **180000**,
closing **2333450**

| Expected | |
|---|---|
| Fuel sales | 433,200.00 |
| **Cash variance** | **0.00** |
| Sindh Goods balance | **40,000.00** |

---

### Day 11 - Wednesday 11 March - paying the supplier

**Before the close** - `/bill-payments/create`: pay **PSO Depot - Korangi** **2,000,000**
from **HBL Current** (bank, not the drawer), dated 2026-03-11, against the oldest bill.

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 103925 | 104350 |
| P1B | 203925 | 204350 |
| D2A | 302950 | 303275 |
| D2B | 402950 | 403275 |

Lubricants **6** pk, **3** L - Dip Petrol **19274.5**, Diesel **13431.5**
Close: opening **2333450**, no credit sales, deposit HBL **240000**, closing **2573350**

| Expected | |
|---|---|
| Fuel sales | 464,200.00 |
| **Cash variance** | **0.00** |
| Owed to PSO Depot | **3,336,000.00** |

This one is paid from the **bank**, so it must **not** touch the drawer. A cash variance of
-2,000,000 means the close treated a bank payment as cash.

---

### Day 12 - Thursday 12 March

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 104350 | 104800 |
| P1B | 204350 | 204800 |
| D2A | 303275 | 303625 |
| D2B | 403275 | 403625 |

Lubricants **4** pk, **5** L - Dip Petrol **18371.5**, Diesel **12729.5**
Close: opening **2573350**, credit Karachi Cement Haulage **30000**, deposit Meezan **200000**,
closing **2851650**

| Expected | |
|---|---|
| Fuel sales | 495,200.00 |
| **Cash variance** | **0.00** |

---

### Day 13 - Friday 13 March

> **Payroll can be back-dated since 24 September, but this day still leaves it out.** The
> figures below were written without wages, so do not run payroll here - paying 100,000 from
> the drawer would put this day and every day after it out by exactly that. Payroll is checked
> on its own at the end of the script, and can now be run there for a past month: set the month
> and the *Paid on* date on `/payroll`, and the close for that date offers the wages. The close
> picks wages by the period's pay day; before, it picked them by the day they were approved.

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 104800 | 105275 |
| P1B | 204800 | 205275 |
| D2A | 303625 | 304000 |
| D2B | 403625 | 404000 |

Lubricants **3** pk, **2** L - Dip Petrol **17418.5**, Diesel **11977.0**
Close: opening **2851650**, no credit sales, deposit UBL **150000**, closing **3235250**

| Expected | |
|---|---|
| Fuel sales | 526,200.00 |
| **Cash variance** | **0.00** |

---

### Day 14 - Saturday 14 March

| Nozzle | Opening | Closing |
|---|---|---|
| P1A | 105275 | 105775 |
| P1B | 205275 | 205775 |
| D2A | 304000 | 304400 |
| D2B | 404000 | 404400 |

Lubricants **5** pk, **4** L - Dip Petrol **16415.5**, Diesel **11174.5**
Close: opening **3235250**, credit Al-Habib Transport **35000**, deposit HBL **260000**,
closing **3511850**

| Expected | |
|---|---|
| Fuel sales | 557,200.00 |
| **Cash variance** | **0.00** |

---

## End of week 2 verification

| | |
|---|---|
| Fuel sales, week 2 | 3,249,400.00 |
| Lubricant sales, week 2 | 95,800.00 |
| Payments received | 120,000.00 |
| Banked | 1,450,000.00 |
| Closing cash | 3,511,850.00 |
| Closing dip | Petrol 16,415.5 L, Diesel 11,174.5 L |

### Receivables after two weeks - `/reports/receivables-aging`

Week 1 added 360,000, week 2 added 130,000, buyers paid 120,000 - total **370,000.00**.

| Buyer | Owed |
|---|---|
| Karachi Cement Haulage | 90,000.00 |
| Pak Freight Lines | 85,000.00 |
| Mehran Logistics | 75,000.00 |
| Indus Travel | 45,000.00 |
| Sindh Goods Carriers | 40,000.00 |
| Al-Habib Transport | 35,000.00 |

All still **not yet due** - the oldest invoice (1 March, 15-day terms) falls due on 16 March.
To watch the aging buckets fill, post one more day on 17 March or later.

### Supplier - PSO Depot - Korangi
**3,336,000.00** - two bills totalling 5,336,000 less the 2,000,000 paid on day 11.

### Payroll - a standalone check, against today's date

Because payroll cannot be back-dated (see day 13), run it on its own, on whatever today is:

1. `/payroll` - run the monthly payroll. Three payslips: 35,000 + 32,000 + 33,000 = **100,000**.
2. Approve all three, then mark them paid **from the cash drawer**.
3. Open a daily close for **today's date**. The **100,000** must appear in Cash Out
   ready-made.

| Expected | |
|---|---|
| Payslips generated | 3 |
| Total net pay | 100,000.00 |
| Shown on today's close | 100,000.00, without being typed in |

A variance of **+100,000** means the close never saw the payslips; **-100,000** means it
counted them twice. Reset the scenario afterwards - this close is not part of the 14 days.

### Trial balance and balance sheet as at 2026-03-14
Both must still balance. Difference 0.00 on each.

---

## Reset

```powershell
php artisan db:seed --force --class='Database\Seeders\ScenarioFuelStationSeeder'
```

---

## The same week, automated

```powershell
php artisan test tests/Feature/FuelStation/SevenDayOperationsTest.php
```

Ten assertions, all passing. It drives the week through `DailyCloseService` and asserts the
figures above — but it posts **everything through the close**, so it proves the engine, not
the imports. The imports are what this manual script is for, and the three that matter each
have a unit test of their own:

- `BankTransactionsTest` — a deposit lowers the close's expected cash, without double counting
- `StandaloneExpensesTest` — a standalone expense reaches the close exactly once
- `StandaloneFuelSaleTest` — a credit sale is imported into the close for its business date

`tests-e2e/fuel-seven-day-scenario.spec.js` drives the browser but is **incomplete**: it fills
and saves meter sales and tank dips, then the close is refused with `0/4 sections saved`. The
remaining work is on the Cash In / Cash Out tabs, whose dynamic rows carry no test hooks.
