# Seven-Day Fuel Station E2E — Controlled Test Script

Every figure below is fixed in advance. Type exactly what is written; the expected result
after each day is stated, so a mismatch is a defect rather than a judgement call.

Nothing here is randomised and no value depends on today's date. Re-running the script
from a fresh seed must produce these same numbers every time.

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
| Daily close | `/scenario-mehran-fuel/fuel/daily-close` |

The seeder stops on the morning of 1 March 2026 with **no closes posted**.

### Fixed reference data

**Tanks** — Tank 1 Petrol (30,000 L), Tank 2 Diesel (25,000 L)
**Opening stock** — Petrol 18,000 L, Diesel 12,000 L, **cash 50,000.00**
**Opening bank** — 2,000,000.00 in HBL Current

**Nozzles** (meters start here)

| Nozzle | Fuel | Opening meter |
|---|---|---|
| P1A | Petrol | 100,000 |
| P1B | Petrol | 200,000 |
| D2A | Diesel | 300,000 |
| D2B | Diesel | 400,000 |

**Rates** — Petrol 300.00 / Diesel 310.00 until 3 March. From **4 March** 306.00 / 314.00.
**Cost** — Petrol 292.00, Diesel 302.00

**Lubricants** — Mobil Super 4L @ 2,400.00/unit · Open Engine Oil @ 1,100.00/litre.
Both open at zero stock.

**Banks** — HBL Current · Meezan Current · UBL Savings
**Buyers** — Al-Habib Transport · Sindh Goods Carriers · Mehran Logistics ·
Karachi Cement Haulage · Indus Travel · Pak Freight Lines (all 15-day terms, limit 200,000)
**Suppliers** — PSO Depot — Korangi · Mobil Distributor — Karachi · Al-Noor Maintenance Services
**Amanat holder** — Karachi Cement Haulage

### How the daily close screen works

Five tabs: **Meter Sales · Tank Dip · Cash In · Cash Out · Review & Post**.
Each has its own Save button, and **all four must be saved before Post is accepted** — the
Review tab shows `n/4 sections saved`. Set the business date first.

---

## Day 1 — Sunday 1 March 2026

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 100000 | 100350 | 300.00 |
| P1B | 200000 | 200350 | 300.00 |
| D2A | 300000 | 300250 | 310.00 |
| D2B | 400000 | 400250 | 310.00 |

Other sales: Mobil Super 4L × **4** · Open Engine Oil × **3** L

**Tank Dip** — Petrol **17298.0** L (stick 432.4) · Diesel **11498.5** L (stick 287.5)

**Cash In** — Opening cash **50000**

**Cash Out**
- Credit sale: Al-Habib Transport **40000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Bank deposit: HBL Current **150000**

**Review** — Closing cash **235900** → Post

### Expected after Day 1
| | |
|---|---|
| Fuel sales | 365,000.00 |
| Lubricants | 12,900.00 |
| Expected cash | 235,900.00 |
| **Cash variance** | **0.00** |
| Stock variance | Petrol −2.0 L · Diesel −1.5 L |

---

## Day 2 — Monday 2 March 2026

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 100350 | 100750 | 300.00 |
| P1B | 200350 | 200750 | 300.00 |
| D2A | 300250 | 300550 | 310.00 |
| D2B | 400250 | 400550 | 310.00 |

Other sales: Mobil Super 4L × **2** · Open Engine Oil × **5** L

**Tank Dip** — Petrol **16495.5** L (stick 412.4) · Diesel **10897.0** L (stick 272.4)

**Cash In**
- Opening cash **235900**
- Amanat deposit: Karachi Cement Haulage **25000**

**Cash Out**
- Credit sale: Sindh Goods Carriers **55000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Bank deposit: Meezan Current **160000**

**Review** — Closing cash **480200** → Post

### Expected after Day 2
| | |
|---|---|
| Fuel sales | 426,000.00 |
| Lubricants | 10,300.00 |
| Expected cash | 480,200.00 |
| **Cash variance** | **0.00** |
| Amanat held | 25,000.00 |

---

## Day 3 — Tuesday 3 March 2026

**Before closing the day**, record the tanker delivery:
`/scenario-mehran-fuel/fuel/receipts/create` — PSO Depot — Korangi,
**10,000 L Petrol @ 292.00** into Tank 1, dated 2026-03-03. Total **2,920,000.00** on credit.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 100750 | 101075 | 300.00 |
| P1B | 200750 | 201075 | 300.00 |
| D2A | 300550 | 300775 | 310.00 |
| D2B | 400550 | 400775 | 310.00 |

Other sales: Mobil Super 4L × **6** · Open Engine Oil × **2** L

**Tank Dip** — Petrol **25844.0** L (stick 646.1) · Diesel **10446.0** L (stick 261.1)

**Cash In** — Opening cash **480200**

**Cash Out**
- Credit sale: Al-Habib Transport **30000**
- Credit sale: Mehran Logistics **25000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Expense: Electricity — "Electricity bill — February" **42000**
- Bank deposit: UBL Savings **120000**

**Review** — Closing cash **612300** → Post

### Expected after Day 3
| | |
|---|---|
| Fuel sales | 334,500.00 |
| Lubricants | 16,600.00 |
| Expected cash | 612,300.00 |
| **Cash variance** | **0.00** |
| Petrol dip after delivery | 25,844.0 L |
| Owed to PSO Depot | 2,920,000.00 |

---

## Day 4 — Wednesday 4 March 2026 · RATE CHANGE

**Before closing the day**, enter the new pump rates:
`/scenario-mehran-fuel/fuel/rates` — effective **2026-03-04**:
Petrol sale **306.00**, Diesel sale **314.00** (costs unchanged: 292.00 / 302.00).

The rate takes effect at 00:00, so the whole of day 4 sells at the new price.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 101075 | 101525 | **306.00** |
| P1B | 201075 | 201525 | **306.00** |
| D2A | 300775 | 301125 | **314.00** |
| D2B | 400775 | 401125 | **314.00** |

Other sales: Mobil Super 4L × **3** · Open Engine Oil × **4** L

**Tank Dip** — Petrol **24941.0** L (stick 623.5) · Diesel **9744.0** L (stick 243.6)

**Cash In** — Opening cash **612300**

**Cash Out**
- Credit sale: Karachi Cement Haulage **60000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Bank deposit: HBL Current **200000**

**Review** — Closing cash **857100** → Post

### Expected after Day 4
| | |
|---|---|
| Petrol at new rate | 900 L × 306.00 = 275,400.00 |
| Diesel at new rate | 700 L × 314.00 = 219,800.00 |
| Fuel sales | 495,200.00 |
| Lubricants | 11,600.00 |
| Expected cash | 857,100.00 |
| **Cash variance** | **0.00** |

**This is the key check of the week.** If revenue posts at 300.00/310.00 the rate change did
not take effect, and fuel sales will read 489,200.00 instead.

---

## Day 5 — Thursday 5 March 2026 · DELIBERATE SHORTAGE

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 101525 | 101900 | 306.00 |
| P1B | 201525 | 201900 | 306.00 |
| D2A | 301125 | 301400 | 314.00 |
| D2B | 401125 | 401400 | 314.00 |

Other sales: Mobil Super 4L × **5** · Open Engine Oil × **6** L

**Tank Dip** — Petrol **24189.0** L (stick 604.7) · Diesel **9192.5** L (stick 229.8)

**Cash In**
- Opening cash **857100**
- Amanat deposit: Karachi Cement Haulage **15000**

**Cash Out**
- Credit sale: Sindh Goods Carriers **35000**
- Credit sale: Indus Travel **20000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Bank deposit: Meezan Current **150000**

**Review** — Closing cash **1085550** → Post

### Expected after Day 5
| | |
|---|---|
| Fuel sales | 402,200.00 |
| Lubricants | 18,600.00 |
| Expected cash | 1,085,900.00 |
| Counted cash | 1,085,550.00 |
| **Cash variance** | **−350.00** |
| Amanat held | 40,000.00 |

The drawer is deliberately 350 short. The close must **still post**, and the 350 must land in
**6180 Cash Short/Over** — not be silently absorbed.

---

## Day 6 — Friday 6 March 2026

**Before closing the day**, record the tanker delivery:
PSO Depot — Korangi, **8,000 L Diesel @ 302.00** into Tank 2, dated 2026-03-06.
Total **2,416,000.00** on credit.

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 101900 | 102325 | 306.00 |
| P1B | 201900 | 202325 | 306.00 |
| D2A | 301400 | 301725 | 314.00 |
| D2B | 401400 | 401725 | 314.00 |

Other sales: Mobil Super 4L × **1** · Open Engine Oil × **3** L

**Tank Dip** — Petrol **23336.5** L (stick 583.4) · Diesel **16540.5** L (stick 413.5)

**Cash In** — Opening cash **1085550**

**Cash Out**
- Credit sale: Pak Freight Lines **45000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Bank deposit: UBL Savings **180000**

**Review** — Closing cash **1328450** → Post

### Expected after Day 6
| | |
|---|---|
| Fuel sales | 464,200.00 |
| Lubricants | 5,700.00 |
| Expected cash | 1,328,450.00 |
| **Cash variance** | **0.00** |
| Diesel dip after delivery | 16,540.5 L |
| Owed to PSO Depot | 5,336,000.00 |

---

## Day 7 — Saturday 7 March 2026

**Meter Sales**

| Nozzle | Opening | Closing | Rate |
|---|---|---|---|
| P1A | 102325 | 102800 | 306.00 |
| P1B | 202325 | 202800 | 306.00 |
| D2A | 301725 | 302125 | 314.00 |
| D2B | 401725 | 402125 | 314.00 |

Other sales: Mobil Super 4L × **7** · Open Engine Oil × **5** L

**Tank Dip** — Petrol **22383.5** L (stick 559.6) · Diesel **15738.0** L (stick 393.4)

**Cash In** — Opening cash **1328450**

**Cash Out**
- Credit sale: Mehran Logistics **50000**
- Amanat withdrawal: Karachi Cement Haulage **10000**
- Expense: Food & Tea — "Staff tea" **600**
- Expense: Food & Tea — "Attendant meals" **1400**
- Bank deposit: HBL Current **190000**

**Review** — Closing cash **1640650** → Post

### Expected after Day 7
| | |
|---|---|
| Fuel sales | 541,900.00 |
| Lubricants | 22,300.00 |
| Expected cash | 1,640,650.00 |
| **Cash variance** | **0.00** |
| Amanat held | 30,000.00 |

---

## End-of-week verification

Check each of these. All figures are exact.

### Daily close history — `/fuel/daily-close/history`
Seven closes, 1–7 March, one per day. **Only 5 March shows a variance**, of −350.00.

### Stock
| | Petrol | Diesel |
|---|---|---|
| Sold | 5,600.0 L | 4,250.0 L |
| Received | 10,000.0 L | 8,000.0 L |
| Shrinkage | −16.5 L | −12.0 L |
| **Closing dip** | **22,383.5 L** | **15,738.0 L** |

Shrinkage is 0.29% of throughput — normal for evaporation and handling.

### Receivables aging — `/reports/receivables-aging`
Everything **not yet due** (15-day terms inside a 7-day week). Total **360,000.00**.

| Buyer | Owed |
|---|---|
| Sindh Goods Carriers | 90,000.00 |
| Mehran Logistics | 75,000.00 |
| Al-Habib Transport | 70,000.00 |
| Karachi Cement Haulage | 60,000.00 |
| Pak Freight Lines | 45,000.00 |
| Indus Travel | 20,000.00 |

### Supplier — `/vendors` → PSO Depot — Korangi
Closing balance **5,336,000.00**, two bills, no payments.
Mobil Distributor and Al-Noor Maintenance: **0.00** each.

### Profit & loss — `/reports/profit-loss` for 1–7 March
| | |
|---|---|
| Fuel sales | 3,029,000.00 |
| Lubricant sales | 98,000.00 |
| Food & Tea | 14,000.00 |
| Electricity | 42,000.00 |
| Cash Short/Over | 350.00 |

### Trial balance — `/reports/trial-balance` as at 2026-03-07
Must read **"The books balance."** Debits equal credits, difference 0.00.

### Balance sheet — `/reports/balance-sheet` as at 2026-03-07
Must read **"Assets equal liabilities plus equity."** Difference 0.00.

### Amanat — `/fuel/amanat`
Karachi Cement Haulage holds **30,000.00** (25,000 + 15,000 − 10,000).

---

## Reset

```powershell
php artisan db:seed --force --class='Database\Seeders\ScenarioFuelStationSeeder'
```

Purges the company and rebuilds it at the 1 March starting position.

---

## The same week, automated

```powershell
php artisan test tests/Feature/FuelStation/SevenDayOperationsTest.php
```

Ten assertions, all passing. It drives the same week through `DailyCloseService` — the
service the screen calls — and asserts the figures above. Use it to confirm the engine
before spending an hour clicking, and use this script to confirm the screens.

`tests-e2e/fuel-seven-day-scenario.spec.js` drives the browser. **Incomplete**: it fills and
saves meter sales and tank dips, but the close is refused with `0/4 sections saved`, so
nothing posts yet. The remaining work is on the Cash In / Cash Out tabs, whose dynamic rows
carry no test hooks.
