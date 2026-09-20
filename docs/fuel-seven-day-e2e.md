# Seven-Day Fuel Station E2E — Controlled Test Script

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
