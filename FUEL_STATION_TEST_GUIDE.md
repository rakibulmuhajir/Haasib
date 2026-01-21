# Fuel Station E2E Testing Guide
## Haasib - Daily Operations Workflow Test

**Target:** Test complete fuel station daily operations for 3 consecutive days
**Application:** http://localhost:8000
**Credentials:** admin@haasib.com / password
**Company:** Naveed Filling Station

---

## Test Environment Status

✅ **Database Connected:** haasib_dev (app_user)
✅ **Schemas Verified:** auth, acct, fuel, inv
✅ **Company:** Naveed Filling Station (ID: 019b735a-c83c-709a-9194-905845772573)
✅ **Tanks Configured:** 4 tanks (Petrol, Diesel, Hi-Octane, Lubricant)
✅ **Pumps Configured:** 3 pumps
✅ **Items:** 5 fuel/lubricant items
✅ **Vendor:** Parco LTD

---

## Test Workflow Overview

### Day 1 Operations
1. **Create Fuel Purchase Bill** → Buy fuel from supplier
2. **Receive Goods** → Post to inventory
3. **Pay Bill** → Payment to supplier
4. **Fuel Receipt** → Receive fuel into tanks
5. **Pump Readings** → Opening/closing readings
6. **Tank Readings** → Dip measurements
7. **Daily Close** → End of day reconciliation

### Day 2 Operations
8. **Pump Readings** → Day 2 readings
9. **Process Sales** → Various sale types
10. **Tank Readings** → Day 2 measurements
11. **Daily Close** → Day 2 reconciliation

### Day 3 Operations
12. **Pump Readings** → Day 3 readings
13. **Process Sales** → Day 3 sales
14. **Tank Readings** → Day 3 measurements
15. **Daily Close** → Day 3 reconciliation

---

## Detailed Test Steps

### STEP 1: Login & Company Selection

**URL:** http://localhost:8000/login

**Actions:**
1. Enter email: `admin@haasib.com`
2. Enter password: `password`
3. Click "Sign in"
4. Verify company is selected: "Naveed Filling Station"
5. Navigate to Dashboard

**Expected Result:**
- ✅ Successfully logged in
- ✅ Dashboard shows fuel station metrics
- ✅ Sidebar shows Fuel Station module

**Potential Errors:**
- ❌ Login fails → Check credentials
- ❌ Company not visible → Run onboarding seeder
- ❌ Dashboard empty → Check permissions

---

### STEP 2: Create Fuel Purchase Bill

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/bills/create

**Actions:**
1. Click "Bills" in sidebar (Accounting → Purchases → Bills)
2. Click "Create Bill" button
3. Fill in bill details:
   - **Vendor:** Parco LTD
   - **Bill Date:** Today's date
   - **Due Date:** Today's date + 30 days
   - **Bill Number:** Auto-generated or manual
4. Add line items:
   - **Item:** Petrol
   - **Quantity:** 1000 liters
   - **Rate:** 300 PKR/liter
   - **Amount:** 300,000 PKR
   - **Tax:** Select applicable tax (if any)
5. Click "Save" or "Create"

**Expected Result:**
- ✅ Bill created successfully
- ✅ Redirected to bill show page
- ✅ Status shows as "Open"
- ✅ Total: 300,000 PKR

**Potential Errors:**
- ❌ Validation error on item → Check item setup
- ❌ Tax calculation error → Check tax settings
- ❌ Vendor not found → Verify vendor exists
- ❌ Account posting failed → Check COA setup

**Database Verification:**
```sql
-- Check bill was created
SELECT id, bill_number, total, status FROM acct.bills
WHERE company_id = '019b735a-c83c-709a-9194-905845772573'
ORDER BY created_at DESC LIMIT 1;

-- Check bill line items
SELECT * FROM acct.bill_line_items
WHERE bill_id = '<BILL_ID_FROM_ABOVE>';
```

---

### STEP 3: Receive Goods (Post to Inventory)

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/bills/{BILL_ID}

**Actions:**
1. On the bill show page
2. Click "Receive Goods" button
3. Confirm receipt:
   - **Warehouse:** Select appropriate tank (Petrol Tank 1)
   - **Quantity Received:** 1000 liters
   - **Received Date:** Today
4. Click "Confirm Receipt"

**Expected Result:**
- ✅ Goods received successfully
- ✅ Inventory posting created
- ✅ Stock increased in Petrol Tank 1
- ✅ Transaction posted to accounting

**Potential Errors:**
- ❌ Warehouse not available → Check tank setup
- ❌ Quantity mismatch → Validation error
- ❌ Posting failed → Check default accounts
- ❌ GL entry error → Check posting templates

**Database Verification:**
```sql
-- Check stock movement
SELECT * FROM inv.stock_movements
WHERE item_id = '019b739d-a4e2-7002-ab83-9ad28994af55' -- Petrol
ORDER BY created_at DESC LIMIT 5;

-- Check warehouse stock
SELECT w.name, sm.quantity
FROM inv.warehouses w
JOIN inv.stock_movements sm ON sm.to_warehouse_id = w.id
WHERE w.id = '019b739e-7161-70ff-8d7d-7727a0272e97' -- Petrol Tank 1
AND sm.item_id = '019b739d-a4e2-7002-ab83-9ad28994af55'
ORDER BY sm.created_at DESC LIMIT 1;

-- Check GL transactions
SELECT * FROM acct.transactions
WHERE company_id = '019b735a-c83c-709a-9194-905845772573'
ORDER BY created_at DESC LIMIT 5;
```

---

### STEP 4: Pay the Bill

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/bill-payments/create

**Actions:**
1. Navigate to Accounting → Purchases → Bill Payments
2. Click "Record Payment"
3. Fill payment details:
   - **Bill:** Select the bill created in Step 2
   - **Payment Date:** Today
   - **Payment Amount:** 300,000 PKR
   - **Payment Method:** Bank Transfer
   - **Bank Account:** Select bank account
   - **Reference:** Payment reference #001
4. Click "Save"

**Expected Result:**
- ✅ Payment recorded successfully
- ✅ Bill status updated to "Paid" or "Partially Paid"
- ✅ Vendor balance updated
- ✅ Bank posting created

**Potential Errors:**
- ❌ Bank account not found → Setup company bank account
- ❌ Payment exceeds bill amount → Validation error
- ❌ Posting failed → Check bank account setup in COA

**Database Verification:**
```sql
-- Check payment recorded
SELECT * FROM acct.bill_payments
WHERE company_id = '019b735a-c83c-709a-9194-905845772573'
ORDER BY created_at DESC LIMIT 1;

-- Check bill status
SELECT id, bill_number, total, status FROM acct.bills
WHERE id = '<BILL_ID>';

-- Check vendor balance
SELECT * FROM acct.vendors
WHERE id = '019b73af-2ad8-707a-812e-260db314e7eb'; -- Parco LTD
```

---

### STEP 5: Receive Fuel Stock into Tanks

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/fuel/receipts/create

**Actions:**
1. Navigate to Fuel Station → Fuel Receipts
2. Click "Create Fuel Receipt"
3. Fill receipt details:
   - **Date:** Today
   - **Supplier:** Parco LTD
   - **Tank:** Petrol Tank 1
   - **Item:** Petrol
   - **Opening Dip:** 500 liters (current level before delivery)
   - **Closing Dip:** 1500 liters (after delivery)
   - **Quantity Received:** 1000 liters
   - **Temperature:** 30°C
   - **Density:** 0.72
   - **Meter Reading:** Delivery tanker meter
4. Click "Save"

**Expected Result:**
- ✅ Fuel receipt created
- ✅ Tank stock updated
- ✅ Dip chart entry created (if configured)
- ✅ Transaction posted

**Potential Errors:**
- ❌ Tank not available → Check warehouse setup
- ❌ Invalid dip readings → Validation error
- ❌ Density calculation error → Check item setup
- ❌ Posting failed → Check fuel COA configuration

**Database Verification:**
```sql
-- Check fuel receipt
SELECT * FROM fuel.tank_readings
WHERE company_id = '019b735a-c83c-709a-9194-905845772573'
ORDER BY created_at DESC LIMIT 1;

-- Check tank current stock (should be 1500L)
SELECT w.name,
       (SELECT SUM(CASE WHEN sm.transaction_type = 'in' THEN sm.quantity ELSE -sm.quantity END)
        FROM inv.stock_movements sm
        WHERE sm.to_warehouse_id = w.id
        AND sm.item_id = '019b739d-a4e2-7002-ab83-9ad28994af55') as current_liters
FROM inv.warehouses w
WHERE w.id = '019b739e-7161-70ff-8d7d-7727a0272e97' -- Petrol Tank 1;
```

---

### STEP 6: Record Pump Readings (Day 1 - Shift Start)

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/fuel/pump-readings/create

**Actions:**
1. Navigate to Fuel Station → Pump Readings
2. Click "Add Pump Reading"
3. For each pump (Pump 1, Pump 2, Pump 3):
   - **Pump:** Select pump
   - **Date:** Today
   - **Shift:** Morning
   - **Nozzle:** Select nozzle (linked to Petrol Tank 1)
   - **Opening Reading:** 1000 liters
   - **Closing Reading:** 1000 liters (shift start)
   - **Test:** 0
4. Click "Save"
5. Repeat for all pumps

**Expected Result:**
- ✅ Pump reading recorded
- ✅ Opening reading set
- ✅ Ready for sales

**Potential Errors:**
- ❌ Pump not linked to tank → Check pump configuration
- ❌ Nozzle not configured → Setup nozzles for pumps
- ❌ Invalid reading → Validation error

**Database Verification:**
```sql
-- Check pump readings
SELECT pr.*, p.name as pump_name, n.nozzle_number
FROM fuel.pump_readings pr
JOIN fuel.pumps p ON pr.pump_id = p.id
JOIN fuel.nozzles n ON pr.nozzle_id = n.id
WHERE pr.company_id = '019b735a-c83c-709a-9194-905845772573'
AND pr.date = CURRENT_DATE
ORDER BY pr.created_at DESC;
```

---

### STEP 7: Process Fuel Sales (Day 1)

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/fuel/sales

**Actions:**

**Sale 1 - Retail Sale:**
1. Navigate to Fuel Station → Sales
2. Fill sale form:
   - **Sale Type:** Retail
   - **Date/Time:** Today
   - **Pump:** Pump 1
   - **Nozzle:** Nozzle 1
   - **Item:** Petrol
   - **Quantity:** 50 liters
   - **Rate:** 350 PKR/liter
   - **Amount:** 17,500 PKR
   - **Payment Method:** Cash
3. Click "Save"

**Sale 2 - Bulk Sale:**
1. Create new sale:
   - **Sale Type:** Bulk
   - **Pump:** Pump 2
   - **Item:** Diesel
   - **Quantity:** 100 liters
   - **Rate:** 340 PKR/liter
   - **Discount:** 5%
   - **Final Rate:** 323 PKR/liter
   - **Amount:** 32,300 PKR
   - **Payment Method:** Bank Transfer
2. Click "Save"

**Expected Result:**
- ✅ Sales created successfully
- ✅ Stock deducted from tanks
- ✅ Revenue posted
- ✅ Payment recorded

**Potential Errors:**
- ❌ Insufficient stock → Check tank levels
- ❌ Rate not configured → Setup current rates
- ❌ Posting failed → Check revenue accounts
- ❌ Pump reading not updated → Check nozzle linkage

**Database Verification:**
```sql
-- Check sales
SELECT sm.*, s.sale_type, s.quantity, s.rate, s.amount
FROM fuel.sale_metadata sm
JOIN fuel.pump_readings pr ON sm.pump_reading_id = pr.id
WHERE sm.company_id = '019b735a-c83c-709a-9194-905845772573'
AND sm.created_at::date = CURRENT_DATE
ORDER BY sm.created_at DESC;

-- Check stock after sales
SELECT w.name, sm.quantity, sm.transaction_type
FROM inv.stock_movements sm
JOIN inv.warehouses w ON sm.from_warehouse_id = w.id
WHERE sm.item_id = '019b739d-a4e2-7002-ab83-9ad28994af55' -- Petrol
AND sm.created_at::date = CURRENT_DATE
ORDER BY sm.created_at DESC;
```

---

### STEP 8: Record Closing Pump Readings (Day 1 - Shift End)

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/fuel/pump-readings/create

**Actions:**
1. Navigate to Fuel Station → Pump Readings
2. For each pump, record closing readings:
   - **Pump:** Pump 1
   - **Date:** Today
   - **Shift:** Morning
   - **Nozzle:** Nozzle 1
   - **Opening Reading:** 1000 liters
   - **Closing Reading:** 1050 liters (50L sold)
   - **Test:** 0
   - **Liters Dispensed:** 50 liters
3. Repeat for Pump 2 (100L sold)

**Expected Result:**
- ✅ Closing readings recorded
- ✅ Liters dispensed calculated
- ✅ Ready for daily close

**Database Verification:**
```sql
-- Verify pump readings for day
SELECT pr.id, p.name as pump_name,
       pr.opening_reading, pr.closing_reading,
       (pr.closing_reading - pr.opening_reading) as liters_dispensed
FROM fuel.pump_readings pr
JOIN fuel.pumps p ON pr.pump_id = p.id
WHERE pr.company_id = '019b735a-c83c-709a-9194-905845772573'
AND pr.date = CURRENT_DATE;
```

---

### STEP 9: Record Tank Readings (Day 1 - End of Day)

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/fuel/tank-readings/create

**Actions:**
1. Navigate to Fuel Station → Tank Readings
2. Click "Add Tank Reading"
3. For Petrol Tank 1:
   - **Date:** Today
   - **Tank:** Petrol Tank 1
   - **Item:** Petrol
   - **Opening Dip:** 1500 liters (from fuel receipt)
   - **Closing Dip:** 1450 liters (physical measurement)
   - **Calculated Volume:** Use dip chart if available
   - **Opening Book:** 1500 liters
   - **Closing Book:** 1450 liters (1500 - 50 sold)
   - **Variance:** 0 liters (perfect match)
4. Click "Save as Draft"
5. Review and click "Confirm"

**Expected Result:**
- ✅ Tank reading created (Draft → Confirmed)
- ✅ Variance calculated
- ✅ Ready for daily close

**Potential Errors:**
- ❌ Variance too high → Warning message
- ❌ Dip chart not found → Check tank configuration
- ❌ Book calculation error → Check pump readings

**Database Verification:**
```sql
-- Check tank readings
SELECT tr.*, w.name as tank_name, i.name as item_name,
       tr.opening_dip, tr.closing_dip,
       tr.variance_liters, tr.variance_percentage
FROM fuel.tank_readings tr
JOIN inv.warehouses w ON tr.warehouse_id = w.id
JOIN inv.items i ON tr.item_id = i.id
WHERE tr.company_id = '019b735a-c83c-709a-9194-905845772573'
AND tr.reading_date = CURRENT_DATE
ORDER BY tr.created_at DESC;
```

---

### STEP 10: Daily Close (Day 1)

**URL:** http://localhost:8000/019b735a-c83c-709a-9194-905845772573/fuel/daily-close/create

**Actions:**
1. Navigate to Fuel Station → Daily Close
2. Click "Create Daily Close"
3. Fill daily close details:
   - **Date:** Today
   - **Shift:** Morning/Full Day

   **Cash Summary:**
   - **Opening Cash:** 50,000 PKR
   - **Cash Sales:** 17,500 PKR (from retail sale)
   - **Other Cash In:** 0
   - **Total Cash In:** 67,500 PKR
   - **Cash Out:** 5,000 PKR (expenses)
   - **Closing Cash:** 62,500 PKR

   **Payment Breakdown:**
   - **Cash:** 17,500 PKR
   - **EasyPaisa:** 0
   - **JazzCash:** 0
   - **Bank Transfer:** 32,300 PKR (bulk sale)
   - **Card:** 0
   - **Vendor Card:** 0

   **Totals:**
   - **Total Sales:** 49,800 PKR (17,500 + 32,300)
   - **Total Sales Quantity:** 150 liters

4. Click "Save Draft"
5. Review all figures
6. Click "Post Daily Close"

**Expected Result:**
- ✅ Daily close created
- ✅ Status: Draft → Posted
- ✅ Variance posted (if any)
- ✅ Cash reconciliation done
- ✅ Day locked

**Potential Errors:**
- ❌ Tank readings not confirmed → Cannot close day
- ❌ Pump readings missing → Validation error
- ❌ Cash doesn't balance → Warning or error
- ❌ Posting failed → Check COA configuration

**Database Verification:**
```sql
-- Check daily close
SELECT * FROM fuel.daily_closes
WHERE company_id = '019b735a-c83c-709a-9194-905845772573'
AND close_date = CURRENT_DATE;

-- Check it's locked
SELECT is_locked, locked_at FROM fuel.daily_closes
WHERE id = '<DAILY_CLOSE_ID>';

-- Check variance posting
SELECT * FROM acct.transactions
WHERE transaction_date = CURRENT_DATE
AND description LIKE '%variance%'
ORDER BY created_at DESC;
```

---

### STEP 11: Day 2 Operations

Repeat Steps 6-10 with different values:

**Day 2 Data:**
- **Opening Reading:** 1050 (Pump 1), 1100 (Pump 2)
- **Sales:**
  - Retail: 80 liters Petrol @ 350 = 28,000 PKR
  - Credit: 200 liters Diesel @ 340 = 68,000 PKR
- **Closing Reading:** 1130 (Pump 1), 1300 (Pump 2)
- **Tank Reading:** 1370 liters (1450 - 80)

**URL for Day 2:**
- Pump Readings: `/fuel/pump-readings/create`
- Sales: `/fuel/sales`
- Tank Readings: `/fuel/tank-readings/create`
- Daily Close: `/fuel/daily-close/create`

**Expected Result:**
- ✅ Day 2 operations complete
- ✅ Day 2 locked

---

### STEP 12: Day 3 Operations

Repeat Steps 6-10 with different values:

**Day 3 Data:**
- **Opening Reading:** 1130 (Pump 1), 1300 (Pump 2)
- **Sales:**
  - Retail: 60 liters Petrol @ 350 = 21,000 PKR
  - Bulk: 150 liters Diesel @ 323 (5% discount) = 48,450 PKR
- **Closing Reading:** 1190 (Pump 1), 1450 (Pump 2)
- **Tank Reading:** 1310 liters (1370 - 60)

**Expected Result:**
- ✅ Day 3 operations complete
- ✅ Day 3 locked
- ✅ 3 days of data available for reporting

---

## Error Tracking Template

Copy this section to track errors found during testing:

```
### Error #1
**Step:** [Step number and name]
**URL:** [URL where error occurred]
**Action:** [What you were doing]
**Error Message:** [Exact error message]
**Screenshot:** [Attach screenshot if possible]
**Database State:** [Relevant data from database]
**Status:** [ ] Fixed [ ] Open [ ] Workaround

### Error #2
...
```

---

## Database Queries for Debugging

### Check All Daily Closes
```sql
SELECT close_date, status, is_locked,
       total_sales, total_cash, total_bank_transfer
FROM fuel.daily_closes
WHERE company_id = '019b735a-c83c-709a-9194-905845772573'
ORDER BY close_date DESC;
```

### Check Tank Stock Over Time
```sql
SELECT tr.reading_date,
       w.name as tank,
       tr.closing_dip as closing_liters,
       tr.variance_liters
FROM fuel.tank_readings tr
JOIN inv.warehouses w ON tr.warehouse_id = w.id
WHERE tr.company_id = '019b735a-c83c-709a-9194-905845772573'
ORDER BY tr.reading_date ASC;
```

### Check Sales Summary
```sql
SELECT DATE(sm.created_at) as sale_date,
       i.name as item,
       COUNT(*) as transactions,
       SUM(s.quantity) as total_liters,
       SUM(s.amount) as total_amount
FROM fuel.sale_metadata sm
JOIN fuel.pump_readings pr ON sm.pump_reading_id = pr.id
JOIN fuel.pumps p ON pr.pump_id = p.id
JOIN inv.items i ON sm.item_id = i.id
WHERE sm.company_id = '019b735a-c83c-709a-9194-905845772573'
GROUP BY DATE(sm.created_at), i.name
ORDER BY sale_date ASC, i.name;
```

### Check GL Transactions
```sql
SELECT t.transaction_date,
       t.transaction_type,
       t.description,
       SUM(tl.debit) as total_debit,
       SUM(tl.credit) as total_credit
FROM acct.transactions t
JOIN acct.transaction_lines tl ON t.id = tl.transaction_id
WHERE t.company_id = '019b735a-c83c-709a-9194-905845772573'
AND t.transaction_date >= CURRENT_DATE - INTERVAL '3 days'
GROUP BY t.transaction_date, t.transaction_type, t.description
ORDER BY t.transaction_date ASC, t.created_at ASC;
```

---

## Success Criteria

✅ **Day 1 Complete:**
- Bill created, received, and paid
- Fuel received into tank
- Pump readings recorded
- Sales processed
- Tank readings confirmed
- Daily close posted and locked

✅ **Day 2 Complete:**
- All operations completed
- Daily close posted and locked

✅ **Day 3 Complete:**
- All operations completed
- Daily close posted and locked

✅ **Data Integrity:**
- No orphaned records
- All transactions posted
- Stock levels accurate
- GL balanced

---

## Next Steps After Testing

1. **Document all errors** in the Error Tracking Template
2. **Prioritize errors** by severity (Critical / High / Medium / Low)
3. **Create bug reports** for development team
4. **Retest** after fixes are deployed
5. **Expand testing** to other modules based on findings

---

## Testing Checklist

Use this checklist as you go through each step:

**Step 1: Login** [ ]
**Step 2: Create Bill** [ ]
**Step 3: Receive Goods** [ ]
**Step 4: Pay Bill** [ ]
**Step 5: Fuel Receipt** [ ]
**Step 6: Pump Readings (Day 1)** [ ]
**Step 7: Sales (Day 1)** [ ]
**Step 8: Closing Pump Readings (Day 1)** [ ]
**Step 9: Tank Readings (Day 1)** [ ]
**Step 10: Daily Close (Day 1)** [ ]
**Step 11: Day 2 Operations** [ ]
**Step 12: Day 3 Operations** [ ]

**Overall Result:** [ ] PASS [ ] FAIL (with X errors)

---

**Created:** 2025-01-02
**Tester:** [Your name]
**Environment:** Local (localhost:8000)
**Database:** haasib_dev
