# Manual Guide: Create Customer and Invoice for Khan User

## 📋 Prerequisites
- **Application**: Running on http://localhost:8000
- **User**: Khan (username: "Khan", password: "yasirkhan")

## 🔐 Step 1: Login
1. Open browser and go to: http://localhost:8000/login
2. Enter credentials:
   - **Username**: `Khan` (with capital K)
   - **Password**: `yasirkhan`
3. Click "Sign in" button
4. Should be redirected to dashboard

## 👤 Step 2: Create Customer

### Method 1: Via Navigation
1. Look for "Sales & Receivables" or "Customers" in the sidebar/navigation
2. Click on "Customers"
3. Click "Add Customer" or "Create Customer" button

### Method 2: Direct URL
1. Go directly to: http://localhost:8000/customers/create

### Fill Customer Form
Based on the screenshots, fill these fields:
1. **Customer Name** (required): Enter customer name (e.g., "Test Customer")
2. **Customer Type** (required): Select from dropdown (Individual, Small Business, etc.)
3. **Contact** (optional but recommended): Enter email or phone
4. **Address** (optional): Enter customer address
5. **Country** (optional): Select country from dropdown
6. **Credit Limit** (optional): Set credit limit

### Submit
1. Click "Create Customer" button
2. Wait for success message

## 🧾 Step 3: Create Invoice

### Navigate to Invoice Creation
1. Look for "Sales & Receivables" or "Invoices" in navigation
2. Click "Invoices"
3. Click "Create Invoice" or "Add Invoice" button

### Direct URL Alternative
1. Go to: http://localhost:8000/invoices/create

### Fill Invoice Form (Required Fields)
1. **Customer** (required): Select the customer you just created
   - Use the autocomplete search or dropdown
   - Type customer name to search
2. **Invoice Number** (required): Enter unique invoice number (e.g., "INV-001")
3. **Issue Date** (required): Select today's date
4. **Due Date** (required): Select due date (e.g., 30 days from now)
5. **Currency** (required): Should default to USD
6. **Line Items** (required - at least one):
   - **Description**: What the invoice is for (e.g., "Consulting Services")
   - **Quantity**: Number of units (e.g., 10)
   - **Unit Price**: Price per unit (e.g., 100.00)
   - Total will be calculated automatically

### Optional Fields
- **Notes**: Additional information for the customer
- **Terms**: Payment terms
- **Tax**: If applicable

### Submit Invoice
1. Click either:
   - "Save as Draft" (creates invoice but doesn't send)
   - "Save & Send" (creates and sends to customer)
2. Wait for success confirmation

## 📝 Validation Requirements
Based on the Laravel logs, the system requires:

### Customer Creation:
- Customer Name ✅
- Customer Type ✅

### Invoice Creation:
- customer_id (UUID of customer) ✅
- invoice_number (unique per company) ✅
- issue_date (valid date) ✅
- due_date (after issue_date) ✅
- currency (3-letter code, exists in currencies table) ✅
- line_items (array with at least one item) ✅
  - description (required)
  - quantity (positive number)
  - unit_price (positive number)

## 🔍 Debugging Tips

### If Login Fails:
1. Check username: should be exactly "Khan" (capital K)
2. Check password: "yasirkhan"
3. Try alternative: username "admin", password "password"

### If Pages Don't Load:
1. Verify Laravel application is running: http://localhost:8000
2. Check Laravel logs: `tail -f storage/logs/laravel.log`

### If Form Validation Fails:
1. Check required fields are filled
2. Check date format (YYYY-MM-DD)
3. Ensure customer exists before creating invoice
4. Check line items have all required data

## 📊 Expected Outcome
After successful completion:
- ✅ Customer created in system
- ✅ Invoice created with customer reference
- ✅ Invoice number generated
- ✅ Line items added with totals calculated
- ✅ Invoice status set (draft or sent)

## 🐛 Common Issues & Solutions

### Issue: "Customer is required" on invoice
**Solution**: Make sure to select a customer from the dropdown before submitting invoice

### Issue: "At least one line item is required"  
**Solution**: Add at least one line item with description, quantity, and unit price

### Issue: "Due date must be after issue date"
**Solution**: Ensure due date is later than issue date

### Issue: RLS Context Error
**Solution**: This was fixed in the middleware, should not occur anymore

---

**Reference**: This guide was created based on the analysis of Laravel logs, Vue component structure, and Playwright test execution results.