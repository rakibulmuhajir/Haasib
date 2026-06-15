# Fuel Station Onboarding Guide
**Complete Start-to-Finish Setup for Pakistani Fuel Station Owners**

---

## Table of Contents

1. [Before You Begin](#1-before-you-begin)
2. [Step 1: Create Your Account & Company](#2-step-1-create-your-account--company)
3. [Step 2: Station Settings & Identity](#3-step-2-station-settings--identity)
4. [Step 3: Set Up Your Chart of Accounts](#4-step-3-set-up-your-chart-of-accounts)
5. [Step 4: Define Your Fuel Products](#5-step-4-define-your-fuel-products)
6. [Step 5: Configure Your Storage Tanks](#6-step-5-configure-your-storage-tanks)
7. [Step 6: Set Up Your Fuel Pumps](#7-step-6-set-up-your-fuel-pumps)
8. [Step 7: Enter Current Fuel Rates](#8-step-7-enter-current-fuel-rates)
9. [Step 8: Record Initial Tank Readings](#9-step-8-record-initial-tank-readings)
10. [Step 9: Set Up Lubricants & Shop Products (Optional)](#10-step-9-set-up-lubricants--shop-products-optional)
11. [Step 10: Add Employees & Attendants](#11-step-10-add-employees--attendants)
12. [Step 11: Set Up Investors (Optional)](#12-step-11-set-up-investors-optional)
13. [Step 12: Configure Amanat / Trust Deposits (Optional)](#13-step-12-configure-amanat--trust-deposits-optional)
14. [Step 13: Set Up Credit Customers (Optional)](#14-step-13-set-up-credit-customers-optional)
15. [Step 14: Configure Vendor Card Settlement (Optional)](#15-step-14-configure-vendor-card-settlement-optional)
16. [Step 15: Record Opening Cash Balance](#16-step-15-record-opening-cash-balance)
17. [Step 16: Your First Daily Close](#17-step-16-your-first-daily-close)
18. [Daily Operations Quick Reference](#18-daily-operations-quick-reference)
19. [Troubleshooting Common Issues](#19-troubleshooting-common-issues)
20. [Glossary of Terms](#20-glossary-of-terms)

---

## 1. Before You Begin

### What You'll Need

| Item | Details |
|------|---------|
| **Business Information** | Company name, NTN number, business address |
| **Bank Account Details** | At least one operating bank account |
| **Fuel Products You Sell** | Petrol, Hi-Octane, Diesel, CNG, etc. |
| **Tank Information** | Number of tanks, capacity in liters, what fuel each tank holds |
| **Pump Information** | Number of dispensers, which tank each pump draws from |
| **Current Fuel Rates** | Your current purchase (cost) and sale (retail) rates per liter |
| **Employee List** | Names of pump attendants, managers, and their roles |
| **Opening Cash** | How much cash is in the drawer/safe right now |

### Estimated Time

- **Full setup:** 30-45 minutes (first time)
- **Daily operations:** 5-10 minutes per day
- **Monthly close:** 15-20 minutes

### System Requirements

- A computer or tablet with internet access
- Modern web browser (Chrome, Edge, or Firefox recommended)
- Your login credentials (email + password)

---

## 2. Step 1: Create Your Account & Company

### 2.1 Register Your Account

1. Go to your Haasib login page
2. Click **"Register"** or **"Sign Up"**
3. Enter:
   - Your full name
   - Email address
   - Phone number
   - Password (minimum 8 characters)
4. Click **"Create Account"**
5. Check your email for a verification link and click it

### 2.2 Create Your Company

After logging in for the first time, you'll see the **Welcome Screen** with two options:

#### Option A: Guided Setup (Recommended for most users)
1. Click **"Start Guided Setup"**
2. Enter your station's details:
   - **Company Name:** e.g., "Al-Rashid Petrol Pump"
   - **Trade Name:** Your business's registered name (if different)
   - **Industry:** Select **"Fuel Station"** from the dropdown
   - **NTN Number:** Your National Tax Number (optional but recommended)
   - **Phone:** Station contact number
   - **Address:** Station location
3. Click **"Create Company"**

#### Option B: Manual Setup (For advanced users)
1. Click **"Set Up Manually"**
2. Enter the same basic company information
3. You'll configure everything yourself later

### ✅ What's been accomplished:
- Your user account is created
- Your company (fuel station) is registered in the system
- The system knows you're a fuel station business

---

## 3. Step 2: Station Settings & Identity

After creating your company, you'll be taken to the **Fuel Station Onboarding Wizard**. This is a step-by-step guide that walks you through everything.

### 3.1 Station Information

1. **Station Name:** Confirm or edit your station's display name
2. **Station Code:** A short code for internal use (e.g., "ARP-01")
3. **Currency:** Set to **PKR** (Pakistani Rupee)
4. **Time Zone:** Select your local time zone (e.g., Asia/Karachi)
5. **Fiscal Year Start:** Choose when your financial year starts (usually July 1st or January 1st)

### 3.2 Operating Hours & Shifts

1. **Number of Shifts per Day:** Typically 2 (Day/Night) or 3 (Morning/Evening/Night)
2. **Shift Timings:** Set the start and end times for each shift
3. **Weekend/Holiday Settings:** Optional - mark which days are holidays

### 3.3 Default Payment Methods

Select which payment methods your station accepts:
- ✅ **Cash** (always enabled)
- ✅ **Credit/Debit Card** (if you have a card machine)
- ✅ **Mobile Wallet** (Easypaisa, JazzCash)
- ✅ **Vendor Fleet Card** (if you accept PSO, Shell, Total, etc.)
- ✅ **Bank Transfer**
- ✅ **Credit Account** (for regular customers who pay later)

### ✅ What's been accomplished:
- Your station's basic identity is configured
- Shifts and operating hours are set
- Payment methods are defined

---

## 4. Step 3: Set Up Your Chart of Accounts

The system will **automatically create** the accounts your fuel station needs. You don't need to be an accountant!

### 4.1 Auto-Created Accounts

The following accounts are created for you:

| Code | Account Name | Type | What It Tracks |
|------|-------------|------|----------------|
| 1000 | Operating Bank Account | Asset | Money in your bank |
| 1030 | Vendor Card Clearing | Asset | Fleet card sales waiting to be paid |
| 1040 | Card Receipts Clearing | Asset | Card machine sales waiting to settle |
| 1050 | Cash on Hand | Asset | Physical cash in your station |
| 1100 | Accounts Receivable | Asset | Money owed by credit customers |
| 1200 | Fuel Inventory | Asset | Value of fuel in your tanks |
| 1210 | Lubricants Inventory | Asset | Value of oils and lubricants |
| 2100 | Accounts Payable – Fuel Supplier | Liability | Money you owe to fuel suppliers |
| 2200 | Amanat Deposits | Liability | Customer trust deposits |
| 2210 | Investor Deposits | Liability | Capital from investors |
| 3100 | Retained Earnings | Equity | Accumulated profit |
| 4100 | Fuel Sales | Revenue | Income from fuel sales |
| 5100 | Cost of Goods – Fuel | COGS | Cost of fuel you sold |
| 5900 | Fuel Shrinkage Loss | COGS | Loss from evaporation/theft |
| 6100 | Investor Commission Expense | Expense | Commission paid to investors |
| 6180 | Cash Short/Over | Expense | Daily cash differences |
| 6200 | Card Processing Fees | Expense | Bank/vendor card fees |
| 6500 | General Expenses | Expense | Miscellaneous costs |

### 4.2 Review & Customize (Optional)

1. You can **add more accounts** later if needed (e.g., separate utility accounts)
2. You can **rename accounts** to match your existing chart
3. The system sets default mappings (e.g., which account is your "Revenue" account)

### ✅ What's been accomplished:
- All required accounts exist
- System knows which account to use for sales, expenses, etc.
- Your books will be accurate from day one

---

## 5. Step 4: Define Your Fuel Products

Now we'll set up the fuel types you sell.

### 5.1 Select Your Fuel Types

Check the fuel types your station sells:

| Fuel Type | Common Names | Typical Use |
|-----------|-------------|-------------|
| ☐ **Petrol** | Petrol, Motor Gasoline, MS | Cars, motorcycles, rickshaws |
| ☐ **Hi-Octane** | Hi-Octane, Super, RON 95+ | Premium cars, performance vehicles |
| ☐ **Diesel** | Diesel, HSD (High Speed Diesel) | Trucks, buses, tractors, generators |
| ☐ **CNG** | Compressed Natural Gas | CNG-converted vehicles |

### 5.2 Configure Each Fuel Product

For each fuel type you selected, provide:

1. **Product Name:** e.g., "Petrol (MS)"
2. **SKU Code:** Auto-generated (e.g., FUEL-PET, FUEL-DSL)
3. **Unit of Measure:** Liters (default)
4. **Track Inventory:** ✅ Yes (always for fuel)
5. **Fuel Category:** Auto-selected based on fuel type

### 5.3 Add Non-Fuel Products (Optional)

You can also add products you sell at your shop:
- Engine oils (e.g., "Shell Helix 20W-50")
- Lubricants and greases
- Coolants and brake fluids
- Snacks and beverages (if you have a shop)

### ✅ What's been accomplished:
- Fuel products are created in the system
- Each product has a unique SKU for tracking
- The system knows what you sell

---

## 6. Step 5: Configure Your Storage Tanks

Tanks are where your fuel is stored. Each tank needs to be set up in the system.

### 6.1 Add Each Tank

For every physical tank at your station, enter:

| Field | Example | Description |
|-------|---------|-------------|
| **Tank Name** | "Petrol Tank 1" | A descriptive name |
| **Tank Code** | "TNK-01" | Short identifier |
| **Fuel Product** | Petrol | Which fuel this tank holds |
| **Capacity (Liters)** | 20,000 | Maximum capacity |
| **Current Stock (Liters)** | 12,500 | Estimated current fuel level |
| **Location** | Underground - East Side | Where the tank is located |

### 6.2 Tank Configuration Tips

- **One tank per fuel type:** If you have two petrol tanks, create two entries
- **Capacity accuracy:** Use the manufacturer's rated capacity
- **Current stock:** This is your opening balance - be as accurate as possible (use your dip stick reading)

### ✅ What's been accomplished:
- All your storage tanks are registered
- Each tank is linked to its fuel product
- Opening stock levels are recorded

---

## 7. Step 6: Set Up Your Fuel Pumps

Pumps (dispensers) are what customers use to get fuel. Each pump draws from a specific tank.

### 7.1 Add Each Pump

For every dispenser at your station:

| Field | Example | Description |
|-------|---------|-------------|
| **Pump Name** | "Pump 1" | Display name |
| **Pump Code** | "PMP-01" | Short identifier |
| **Connected Tank** | Petrol Tank 1 | Which tank it draws from |
| **Current Meter Reading** | 1,245,678.90 | The number on the pump's totalizer |
| **Status** | Active | Active or Inactive |

### 7.2 Understanding Meter Readings

The **current meter reading** is the total liters dispensed by this pump since it was installed. You can find this number on the pump's display (the totalizer/odometer reading, not a trip reset).

- **Where to find it:** Look at the pump's main display for a number labeled "Total" or "Grand Total"
- **Why it matters:** This is how the system tracks how much fuel was dispensed
- **Example:** If the pump shows 1,245,678.9 liters total, enter that number

### 7.3 Configure Nozzles (If Applicable)

Some pumps have multiple nozzles (e.g., one for Petrol, one for Diesel on the same dispenser). For each nozzle:

1. **Nozzle Name:** e.g., "Nozzle 1 - Petrol"
2. **Fuel Product:** Which fuel this nozzle dispenses
3. **Sort Order:** Physical position (left to right)

### ✅ What's been accomplished:
- All dispensers are registered
- Each pump is linked to its tank
- Opening meter readings are recorded
- Nozzles are configured (if applicable)

---

## 8. Step 7: Enter Current Fuel Rates

Fuel prices change frequently in Pakistan (set by OGRA). Enter your current rates.

### 8.1 Set Rates for Each Fuel Product

For each fuel type you sell:

| Field | Example | Description |
|-------|---------|-------------|
| **Fuel Product** | Petrol | Which fuel |
| **Purchase Rate (per liter)** | PKR 248.50 | What you pay the supplier |
| **Sale Rate (per liter)** | PKR 252.10 | What customers pay |
| **Effective Date** | 01-Jan-2025 | When these rates take effect |

### 8.2 Understanding Margin

Your **margin** is calculated automatically:
```
Margin = Sale Rate - Purchase Rate
Example: 252.10 - 248.50 = PKR 3.60 per liter
```

### 8.3 Rate Change History

The system keeps a **complete history** of all rate changes. This is important for:
- **Investor calculations:** Each investment lot locks in the rate at that time
- **Audit trail:** You can see what rates were on any given date
- **Profit analysis:** Track how margin changes over time

### ✅ What's been accomplished:
- Current purchase and sale rates are set
- Rate history is started
- System can calculate margins automatically

---

## 9. Step 8: Record Initial Tank Readings

This establishes your baseline for tracking fuel variance (loss/gain).

### 9.1 Take Physical Dip Measurements

For each tank, use your **dip stick** or **automatic tank gauge** to measure:

1. **Physical Dip Reading:** The actual measurement in inches/cm
2. **Calculated Volume:** Convert dip reading to liters (using tank's calibration chart)
3. **Temperature:** Fuel temperature (affects volume)

### 9.2 Enter the Reading in System

1. Go to **Tank Readings** section
2. Click **"Record New Reading"**
3. Select the tank
4. Enter:
   - **Dip Reading:** The physical measurement
   - **Calculated Volume:** Liters in tank
   - **Temperature:** Optional but recommended
5. Click **"Save as Draft"**

### 9.3 Confirm & Post

1. Review the reading for accuracy
2. Click **"Confirm"** (manager approval)
3. Click **"Post"** to record it in the system

> **Note:** The first reading establishes your baseline. Future readings will be compared to this to calculate variance.

### ✅ What's been accomplished:
- Baseline tank levels are recorded
- Variance tracking is initialized
- You can now detect losses (evaporation, theft) or gains

---

## 10. Step 9: Set Up Lubricants & Shop Products (Optional)

If you sell engine oils, lubricants, or other products, set them up here.

### 10.1 Add Lubricant Products

1. Go to **Products** section
2. Click **"Add Product"**
3. Enter:
   - **Product Name:** e.g., "Shell Helix HX3 20W-50"
   - **SKU:** e.g., "LUB-SH-HX3"
   - **Category:** Lubricant
   - **Unit of Measure:** Liters or Pieces
   - **Purchase Price:** What you pay
   - **Sale Price:** What customers pay
   - **Track Inventory:** ✅ Yes

### 10.2 Add Shop Items (Optional)

For convenience store items:
- Snacks, beverages, cigarettes
- Spare parts (filters, spark plugs)
- Any other merchandise

### ✅ What's been accomplished:
- Non-fuel products are in the system
- You can track lubricant inventory and sales

---

## 11. Step 10: Add Employees & Attendants

Add your staff so you can track who handles what.

### 11.1 Add Each Employee

| Field | Example | Description |
|-------|---------|-------------|
| **Full Name** | "Ahmed Khan" | Employee's name |
| **Employee ID** | "EMP-001" | Internal ID |
| **Role** | Pump Attendant | Job title |
| **Phone** | 0300-1234567 | Contact number |
| **Email** | ahmed@example.com | Optional |
| **Salary** | PKR 35,000 | Monthly salary |
| **Hire Date** | 01-Jan-2024 | When they started |

### 11.2 Common Roles at a Fuel Station

| Role | Responsibilities |
|------|-----------------|
| **Station Manager** | Overall operations, daily close, approvals |
| **Pump Attendant** | Operating pumps, collecting cash |
| **Cashier** | Handling cash, reconciling payments |
| **Accountant** | Books, tax filings, reporting |
| **Shift Supervisor** | Overseeing a shift, handover approvals |

### ✅ What's been accomplished:
- All staff are registered in the system
- You can assign handovers and track cash collection per attendant

---

## 12. Step 11: Set Up Investors (Optional)

If your station operates with investor capital, set up your investors here.

### 12.1 How Investor Model Works

```
Investor gives PKR 1,000,000
    ↓
Rate at that time: Purchase = PKR 248.50/liter
    ↓
Entitled units = 1,000,000 / 248.50 = 4,024.14 liters
    ↓
When fuel is sold: Commission = liters consumed × (Sale Rate - Purchase Rate)
    ↓
Rate changes DON'T affect existing lots (locked at creation)
```

### 12.2 Add an Investor

1. Go to **Investors** section
2. Click **"Add Investor"**
3. Enter:
   - **Investor Name:** e.g., "Haji Muhammad Ali"
   - **Phone:** Contact number
   - **Address:** Optional
   - **Notes:** Any additional info

### 12.3 Add an Investment Lot

1. Click on the investor's name
2. Click **"Add Investment Lot"**
3. Enter:
   - **Investment Amount:** e.g., PKR 1,000,000
   - **Purchase Rate at Time:** The fuel purchase rate on that date
   - **Investment Date:** When the money was received
   - **Notes:** Optional

The system automatically calculates:
- **Entitled Units:** Investment ÷ Purchase Rate
- **Current Commission:** Based on fuel consumed from this lot

### 12.4 Pay Commission

1. Go to the investor's detail page
2. Click **"Pay Commission"**
3. The system shows outstanding commission
4. Enter payment amount and date
5. Click **"Confirm Payment"**

### ✅ What's been accomplished:
- Investors are registered
- Investment lots are recorded with locked rates
- Commission tracking is active

---

## 13. Step 12: Configure Amanat / Trust Deposits (Optional)

Amanat allows customers to deposit money and draw fuel against it later.

### 13.1 How Amanat Works

```
Customer deposits PKR 10,000
    ↓
Balance = PKR 10,000
    ↓
Customer buys PKR 2,000 of fuel → Balance = PKR 8,000
    ↓
Customer buys PKR 5,000 of fuel → Balance = PKR 3,000
    ↓
Customer can withdraw remaining PKR 3,000
```

### 13.2 Record a Deposit

1. Go to **Amanat** section
2. Click on the customer (or add a new customer first)
3. Click **"Record Deposit"**
4. Enter:
   - **Amount:** e.g., PKR 10,000
   - **Payment Method:** Cash, Bank Transfer, etc.
   - **Date:** When the deposit was made
   - **Notes:** Optional reference

### 13.3 Process a Withdrawal

1. Go to the customer's Amanat page
2. Click **"Withdraw"**
3. Enter the amount (cannot exceed balance)
4. Confirm

### ✅ What's been accomplished:
- Amanat customers are set up
- Deposits and withdrawals can be tracked
- Balance is always visible

---

## 14. Step 13: Set Up Credit Customers (Optional)

For customers who buy fuel on credit and pay later.

### 14.1 Add a Credit Customer

1. Go to **Credit Customers** section
2. Click **"Add Credit Customer"**
3. Enter:
   - **Customer Name:** e.g., "Al-Rashid Transport"
   - **Phone:** Contact number
   - **Credit Limit:** e.g., PKR 500,000
   - **Payment Terms:** e.g., "Net 30" (due in 30 days)
   - **NTN Number:** Optional

### 14.2 Managing Credit Sales

When a credit customer buys fuel:
1. Record the sale as a **Credit Sale** (not cash)
2. The amount is added to their outstanding balance
3. When they pay, record a **Collection**

### 14.3 Block/Unblock Customers

If a customer exceeds their credit limit or doesn't pay:
1. Go to the customer's detail page
2. Click **"Block Customer"**
3. They won't be able to buy on credit until unblocked

### ✅ What's been accomplished:
- Credit customers are registered
- Credit limits are set
- You can track who owes you money

---

## 15. Step 14: Configure Vendor Card Settlement (Optional)

If you accept fleet cards (PSO, Shell, Total Parco, etc.), set up vendor card settlement.

### 15.1 How Vendor Cards Work

```
Customer fills fuel using fleet card
    ↓
Sale is recorded as "Vendor Card" type
    ↓
Amount goes to "Vendor Card Clearing" (receivable)
    ↓
Weekly/monthly: Vendor sends payment minus fees
    ↓
You record the settlement
    ↓
Receivable is cleared
```

### 15.2 Record a Settlement

1. Go to **Vendor Cards** section
2. Click **"Settle"**
3. The system shows pending vendor card receivables
4. Enter:
   - **Amount Received:** What the vendor actually paid
   - **Fee Deducted:** Any processing fees
   - **Settlement Date:** When you received the payment
5. Click **"Confirm Settlement"**

### ✅ What's been accomplished:
- Vendor card receivables can be tracked
- Settlement process is configured
- Fees are properly recorded

---

## 16. Step 15: Record Opening Cash Balance

Before you start daily operations, record how much cash is in your station.

### 16.1 Record Opening Cash

1. Go to **Settings** → **Opening Balance**
2. Enter:
   - **Cash in Hand:** Physical cash in the drawer/safe
   - **Bank Balance:** Current balance in your operating bank account
   - **Date:** Today's date

### ✅ What's been accomplished:
- Opening cash position is recorded
- Daily close will compare against this

---

## 17. Step 16: Your First Daily Close

The daily close is the most important daily operation. It captures everything that happened in a day.

### 17.1 What Daily Close Captures

| Section | What You Enter |
|---------|---------------|
| **Pump Readings** | Closing meter readings for each pump |
| **Tank Readings** | Physical dip measurements |
| **Sales Summary** | Total sales by fuel type and payment method |
| **Cash Collected** | Cash received from sales |
| **Expenses** | Any cash paid out (e.g., electricity bill) |
| **Bank Deposits** | Amount deposited in bank |
| **Handovers** | Cash handed over by attendants |

### 17.2 Performing Daily Close

1. Go to **Daily Close** section
2. Click **"New Daily Close"**
3. The system shows today's date and shift

#### Step A: Enter Pump Readings
For each pump, enter the **closing meter reading**:
- The system shows the opening reading
- You enter the closing reading
- System calculates: `Liters Dispensed = Closing - Opening`

#### Step B: Enter Tank Readings
For each tank, enter the **physical dip measurement**:
- The system shows the expected level (based on sales)
- You enter the actual dip reading
- System calculates variance

#### Step C: Enter Sales Summary
Break down sales by:
- **Fuel Type:** Petrol, Diesel, Hi-Octane
- **Payment Method:** Cash, Card, Mobile Wallet, Vendor Card, Credit
- **Total Amount:** Should match pump readings × rate

#### Step D: Enter Cash Movements
- **Opening Cash:** What you started with
- **Cash Sales:** Cash received from customers
- **Cash Expenses:** Money paid out
- **Bank Deposits:** Cash deposited in bank
- **Closing Cash:** What should be in the drawer

#### Step E: Review & Submit
1. The system shows a **summary** of everything
2. It calculates **expected cash vs actual cash**
3. If there's a variance, it's recorded as "Cash Short/Over"
4. Click **"Submit Daily Close"**

### 17.3 After Daily Close

Once submitted:
- The daily close is saved and can be viewed later
- Pump readings are updated for the next day
- Tank variance is calculated
- Financial transactions are posted to the general ledger

### 17.4 Locking Daily Close

After review, the manager can **lock** the daily close:
- Locked closes cannot be edited
- If changes are needed, an **amendment** is created (audit trail preserved)
- This prevents fraud and ensures data integrity

### ✅ What's been accomplished:
- Your first full day of operations is recorded
- All sales, cash, and inventory are reconciled
- The system has accurate financial data

---

## 18. Daily Operations Quick Reference

### Morning (Start of Shift)

| Task | Where | Time |
|------|-------|------|
| Record opening pump readings | Pump Readings | 2 min |
| Check tank levels | Tank Readings | 5 min |
| Verify cash in drawer | Cash Count | 2 min |

### During the Day

| Task | Where | Notes |
|------|-------|-------|
| Record fuel sales | Sales | Can be done in bulk at end of day |
| Record Amanat deposits | Amanat | When customers deposit |
| Record credit sales | Credit Sales | For credit customers |
| Record handovers | Handovers | When attendants change shifts |

### End of Day (Close of Shift)

| Task | Where | Time |
|------|-------|------|
| Record closing pump readings | Pump Readings | 2 min |
| Take tank dip readings | Tank Readings | 5 min |
| Count cash in drawer | Physical | 5 min |
| Perform daily close | Daily Close | 10 min |
| Lock daily close | Daily Close | 1 min |

### Weekly Tasks

| Task | Frequency | Where |
|------|-----------|-------|
| Vendor card settlement | Weekly | Vendor Cards |
| Pay investor commission | As needed | Investors |
| Review AR aging | Weekly | Credit Customers |
| Bank reconciliation | Weekly | Banking |

### Monthly Tasks

| Task | Where |
|------|-------|
| Generate sales report | Reports → Sales |
| Generate shrinkage report | Reports → Shrinkage |
| Review profit & loss | Reports |
| Lock month (prevent edits) | Daily Close → Lock Month |
| Pay salaries | Payroll |

---

## 19. Troubleshooting Common Issues

### Issue: Pump reading seems wrong

**Possible causes:**
- You entered the wrong number (check the pump display again)
- The pump was reset or replaced
- Multiple nozzles on one pump

**Solution:**
1. Verify the reading on the pump's physical display
2. Check if the pump was serviced (meter may have been reset)
3. For multi-nozzle pumps, ensure you're reading the correct totalizer

### Issue: Tank variance is too high

**Possible causes:**
- Dip reading was inaccurate
- Temperature variation (fuel expands/contracts)
- Actual theft or leak
- Pump meters are inaccurate

**Solution:**
1. Take another dip reading
2. Check for visible leaks
3. Compare with pump readings (total dispensed should match reduction in tank)
4. If consistent, pump calibration may be needed

### Issue: Cash doesn't match expected

**Possible causes:**
- A sale was recorded incorrectly
- Cash was misplaced or stolen
- Change was given incorrectly
- An expense wasn't recorded

**Solution:**
1. Double-check all sales entries
2. Verify handover amounts
3. Check if any cash was used for expenses
4. Record the difference as "Cash Short/Over" (investigate later)

### Issue: Can't submit daily close

**Possible causes:**
- A required field is missing
- A previous daily close hasn't been locked
- The period is closed

**Solution:**
1. Check all required fields (marked with *)
2. Ensure previous day's close is locked
3. If period is closed, contact your manager

### Issue: Investor commission seems wrong

**Possible causes:**
- Rate was entered incorrectly for the lot
- Fuel consumption wasn't tracked properly
- Multiple lots are being consumed

**Solution:**
1. Check the lot's locked purchase rate
2. Verify fuel consumption records
3. Review the FIFO consumption order

---

## 20. Glossary of Terms

| Term | Meaning |
|------|---------|
| **Amanat** | Trust deposit - customer pre-pays for fuel |
| **AR** | Accounts Receivable - money owed to you |
| **AP** | Accounts Payable - money you owe |
| **Chart of Accounts** | The list of all financial accounts (categories) |
| **COGS** | Cost of Goods Sold - what you paid for the fuel you sold |
| **Daily Close** | End-of-day reconciliation of all transactions |
| **Dip Reading** | Physical measurement of fuel in a tank using a dip stick |
| **FIFO** | First In, First Out - method of consuming investor lots |
| **GL** | General Ledger - the master record of all financial transactions |
| **Handover** | Transfer of cash responsibility between attendants |
| **Investor Lot** | A specific investment with a locked rate |
| **Margin** | Difference between sale price and purchase price |
| **Nozzle** | The hose and nozzle on a pump (some pumps have multiple) |
| **NTN** | National Tax Number (Pakistan) |
| **OGRA** | Oil and Gas Regulatory Authority (sets fuel prices in Pakistan) |
| **PKR** | Pakistani Rupee |
| **Purchase Rate** | What you pay the supplier per liter |
| **Sale Rate** | What customers pay per liter |
| **Shrinkage** | Loss of fuel due to evaporation, theft, or measurement error |
| **SKU** | Stock Keeping Unit - unique product identifier |
| **Totalizer** | The cumulative meter reading on a pump |
| **Variance** | Difference between expected and actual (tank levels, cash) |
| **Vendor Card** | Fleet card (PSO, Shell, Total Parco, etc.) |

---

## Appendix A: Onboarding Checklist

Use this checklist to track your setup progress:

- [ ] **Step 1:** Account created & company registered
- [ ] **Step 2:** Station settings configured (shifts, payment methods)
- [ ] **Step 3:** Chart of accounts created
- [ ] **Step 4:** Fuel products defined
- [ ] **Step 5:** Storage tanks configured
- [ ] **Step 6:** Fuel pumps set up with meter readings
- [ ] **Step 7:** Current fuel rates entered
- [ ] **Step 8:** Initial tank readings recorded
- [ ] **Step 9:** Lubricants & shop products (optional)
- [ ] **Step 10:** Employees & attendants added
- [ ] **Step 11:** Investors set up (optional)
- [ ] **Step 12:** Amanat configured (optional)
- [ ] **Step 13:** Credit customers set up (optional)
- [ ] **Step 14:** Vendor card settlement configured (optional)
- [ ] **Step 15:** Opening cash balance recorded
- [ ] **Step 16:** First daily close completed ✅

---

## Appendix B: Key Reports

### Daily Reports
| Report | What It Shows | Where |
|--------|--------------|-------|
| Daily Close Summary | All transactions for the day | Daily Close |
| Sales by Type | Fuel sales broken down by type | Reports → Sales |
| Cash Position | Opening/closing cash, deposits | Daily Close |

### Weekly Reports
| Report | What It Shows | Where |
|--------|--------------|-------|
| Vendor Card Status | Pending settlements | Vendor Cards |
| Credit Customer Aging | Who owes what | Credit Customers |
| Handover Summary | Attendant cash collections | Handovers |

### Monthly Reports
| Report | What It Shows | Where |
|--------|--------------|-------|
| Sales Report | Monthly sales by fuel type & payment method | Reports → Sales |
| Shrinkage Report | Tank variance analysis | Reports → Shrinkage |
| Profit & Loss | Revenue - COGS - Expenses | Reports |
| Investor Commission | Commission earned per investor | Investors |

---

## Appendix C: Quick Tips

### 🏆 Best Practices

1. **Do daily close every day** - Don't skip days. It's much harder to catch up.
2. **Take dip readings seriously** - They're your best defense against theft.
3. **Lock daily closes** - Prevent unauthorized changes.
4. **Record handovers promptly** - When an attendant finishes their shift.
5. **Reconcile bank accounts weekly** - Catch discrepancies early.

### ⚡ Shortcuts

- **Dashboard** shows real-time tank levels, today's sales, and pending tasks
- **Quick Sale** lets you record a sale in seconds
- **Bulk Entry** for entering multiple pump readings at once

### ❌ Common Mistakes to Avoid

- Entering pump readings in the wrong order (opening vs closing)
- Forgetting to record cash expenses before daily close
- Not linking tanks to the correct fuel product
- Entering rates without the correct effective date

---

*This guide covers the complete setup and daily operation of your fuel station in Haasib. For additional help, contact support or refer to the in-app help documentation.*

**Last Updated:** June 2025
