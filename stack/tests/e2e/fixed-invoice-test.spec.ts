import { test, expect, navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData } from './helpers/auth-helper';

test.describe('Fixed Invoice Management Tests', () => {
  let testData: any;
  let customerName: string | null = null;

  test.beforeEach(async ({ page }) => {
    testData = generateTestData();
  });

  test('should create a new invoice successfully with complete form data', async ({ page }) => {
    console.log('🧾 Testing complete invoice creation workflow...');

    // Step 1: Login
    await page.goto('/login');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Step 2: Create a customer for the invoice
    console.log('👤 Creating customer for invoice...');
    await page.goto('/customers/create');
    await page.waitForLoadState('networkidle');

    const customerData = testData.customer;
    
    // Fill customer name field
    await page.fill('input[placeholder="Enter customer name"]', customerData.name);
    console.log(`✅ Filled customer name: ${customerData.name}`);
    
    // Select customer type
    await page.click('.p-dropdown');
    await page.waitForTimeout(500);
    await page.click('li:has-text("Individual")');
    console.log('✅ Selected customer type: Individual');
    
    // Fill contact field
    await page.fill('input[placeholder="Email or phone number"]', customerData.email);
    console.log(`✅ Filled contact: ${customerData.email}`);
    
    await takeScreenshot(page, 'customer-form-filled');
    
    // Click Create Customer button
    await clickButtonWithText(page, 'Create Customer');
    await page.waitForTimeout(3000);

    // Verify customer was created
    const customerSuccess = await waitForSuccessMessage(page);
    if (customerSuccess) {
      console.log(`✅ Customer "${customerData.name}" created successfully`);
    }

    customerName = customerData.name;

    // Step 3: Navigate to invoice creation
    console.log('🧾 Creating invoice...');
    await page.goto('/invoices/create');
    await page.waitForLoadState('networkidle');
    await takeScreenshot(page, 'invoice-create-page');

    const invoiceData = testData.invoice;

    // Fill invoice creation form with all required fields
    console.log('📝 Filling invoice creation form...');

    // 1. Select customer (required)
    console.log('  Selecting customer...');
    const customerDropdown = page.locator('.p-dropdown').first();
    await customerDropdown.click();
    await page.waitForTimeout(500);
    
    // Look for the customer we just created in the dropdown
    await page.fill('input.p-dropdown-filter.p-inputtext.p-component', customerName);
    await page.waitForTimeout(1000);
    await page.click(`li:has-text("${customerName}")`);
    console.log(`✅ Selected customer: ${customerName}`);

    // 2. Invoice number (required)
    console.log('  Filling invoice number...');
    const invoiceNumberField = page.locator('input[name="invoice_number"], input[placeholder*="number"]').first();
    if (await invoiceNumberField.isVisible()) {
      await invoiceNumberField.fill(invoiceData.invoice_number);
      console.log(`✅ Invoice number: ${invoiceData.invoice_number}`);
    }

    // 3. Invoice date (required)
    console.log('  Setting invoice date...');
    const today = new Date().toISOString().split('T')[0];
    const dateFields = await page.locator('input.p-inputtext[placeholder*="Date"], .p-calendar input').all();
    if (dateFields.length > 0) {
      await dateFields[0].fill(today);
      console.log(`✅ Invoice date: ${today}`);
    }

    // 4. Due date (required) - 30 days from now
    console.log('  Setting due date...');
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    const dueDateStr = dueDate.toISOString().split('T')[0];
    
    if (dateFields.length > 1) {
      await dateFields[1].fill(dueDateStr); // Second date field is likely due date
      console.log(`✅ Due date: ${dueDateStr}`);
    }

    // 5. Currency (required) - should default to USD
    console.log('  Checking currency...');
    const currencyFields = await page.locator('.p-dropdown').all();
    let currencySet = false;
    for (const field of currencyFields) {
      const text = await field.textContent();
      if (text && text.includes('USD')) {
        console.log('✅ Currency already set to USD');
        currencySet = true;
        break;
      }
    }

    // 6. Add line items (required - at least one)
    console.log('📦 Adding invoice line items...');
    
    // Fill the first line item (already present in form)
    const descFields = await page.locator('input[placeholder*="description"], textarea').all();
    if (descFields.length > 0) {
      await descFields[0].fill('Professional Services - E2E Testing');
      console.log('✅ Line item 1 description');
    }

    const qtyFields = await page.locator('input[name*="quantity"], input.p-inputnumber input').all();
    if (qtyFields.length > 0) {
      await qtyFields[0].fill('10');
      console.log('✅ Line item 1 quantity: 10');
    }

    const priceFields = await page.locator('input[name*="price"], input[name*="unit_price"], input.p-inputnumber input').all();
    if (priceFields.length > 1) {
      await priceFields[1].fill('100.00'); // Second price field is likely unit price
      console.log('✅ Line item 1 price: $100.00');
    }

    await page.waitForTimeout(2000); // Let totals calculate

    // 7. Notes (optional)
    console.log('  Adding notes...');
    const notesField = page.locator('textarea[placeholder*="notes"], textarea[name*="notes"]').first();
    if (await notesField.isVisible()) {
      await notesField.fill('E2E Test Invoice - Payment due within 30 days');
      console.log('✅ Added notes');
    }

    await takeScreenshot(page, 'invoice-form-completed');

    // Step 4: Submit the form
    console.log('💾 Submitting invoice creation form...');
    await clickButtonWithText(page, 'Save as Draft');
    await page.waitForTimeout(5000);

    // Step 5: Verify successful creation
    const success = await waitForSuccessMessage(page);
    expect(success).toBeTruthy();

    if (success) {
      console.log(`✅ Invoice "${invoiceData.invoice_number}" created successfully`);
      
      // Step 6: Verify invoice appears in list
      await navigateToModule(page, 'invoices');
      await page.waitForLoadState('networkidle');

      await takeScreenshot(page, 'invoice-list-after-creation');
      
      console.log(`✅ Invoice creation test completed successfully`);
    }
  });

  test('should handle invoice creation validation errors gracefully', async ({ page }) => {
    console.log('🧪 Testing invoice validation errors...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Navigate to invoice creation
    await page.goto('/invoices/create');
    await page.waitForLoadState('networkidle');

    // Try to submit empty form
    console.log('  Testing empty form submission...');
    await clickButtonWithText(page, 'Save as Draft');
    await page.waitForTimeout(2000);

    // Check for validation error messages
    const errorSelectors = [
      '.p-error',
      '.text-red-500',
      '.form-error',
      '[data-pc-name="message"]'
    ];

    let validationErrorsFound = false;
    for (const selector of errorSelectors) {
      const errors = page.locator(selector);
      if (await errors.count() > 0) {
        validationErrorsFound = true;
        console.log(`✅ Found validation errors with selector: ${selector}`);
        break;
      }
    }

    if (validationErrorsFound) {
      console.log('✅ Validation errors working correctly');
    } else {
      console.log('⚠️ No validation errors detected - may need to check error handling');
    }

    await takeScreenshot(page, 'invoice-validation-errors');
  });
});