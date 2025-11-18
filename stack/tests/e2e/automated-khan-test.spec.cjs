const { test, expect } = require('@playwright/test');

test('Interactive Customer and Invoice Creation - Khan User', async ({ page }) => {
  console.log('🚀 Starting interactive session with Khan user...');
  
  // Step 1: Login with Khan credentials
  console.log('🔐 Logging in with username: Khan, password: yasirkhan');
  await page.goto('http://localhost:8000/login');
  await page.waitForLoadState('networkidle');
  
  // Take screenshot of login form first
  await page.screenshot({ path: 'test-results/01-login-form.png', fullPage: true });
  
  // Fill login form with correct username capitalization
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  
  // Screenshot before clicking submit
  await page.screenshot({ path: 'test-results/02-login-filled.png', fullPage: true });
  
  await page.click('button[type="submit"]');
  
  // Wait for login to complete - check if we get redirected
  try {
    await page.waitForURL('**/dashboard', { timeout: 10000 });
    console.log('✅ Login successful - redirected to dashboard!');
  } catch (error) {
    console.log('⚠️ Login may have failed or redirected elsewhere');
    console.log('Current URL after login attempt:', page.url());
  }
  
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  // Screenshot after login
  await page.screenshot({ path: 'test-results/03-after-login.png', fullPage: true });
  console.log('Current URL after login:', page.url());
  
  // Check if we're still on login page (login failed)
  if (page.url().includes('/login')) {
    console.log('❌ Login failed - still on login page');
    console.log('Checking for error messages...');
    
    const errorElements = await page.locator('.text-red-500, .error, .alert-danger, [data-testid="error"]').all();
    for (const error of errorElements) {
      const errorText = await error.textContent();
      if (errorText && errorText.trim()) {
        console.log('Error message:', errorText.trim());
      }
    }
    
    return; // Exit if login failed
  }
  
  // Step 2: Navigate to customers
  console.log('\n👤 Navigating to customers...');
  await page.goto('http://localhost:8000/customers');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('Customers page loaded:', page.url());
  await page.screenshot({ path: 'test-results/04-customers-page.png', fullPage: true });
  
  // Look for customer creation option
  const createButtons = await page.locator('button, a').all();
  console.log(`Found ${createButtons.length} buttons/links on customers page`);
  
  let createCustomerFound = false;
  for (const button of createButtons) {
    const text = await button.textContent();
    if (text && text.trim()) {
      const cleanText = text.trim().toLowerCase();
      if (cleanText.includes('create') || cleanText.includes('add') || cleanText.includes('new')) {
        console.log(`Found relevant button: "${text.trim()}"`);
        if (cleanText.includes('customer')) {
          console.log('Clicking customer creation button...');
          await button.click();
          createCustomerFound = true;
          break;
        }
      }
    }
  }
  
  if (!createCustomerFound) {
    console.log('No customer creation button found, trying direct navigation...');
    await page.goto('http://localhost:8000/customers/create');
    await page.waitForLoadState('networkidle');
  }
  
  await page.waitForTimeout(2000);
  console.log('Customer creation page:', page.url());
  await page.screenshot({ path: 'test-results/05-customer-creation-page.png', fullPage: true });
  
  // Check if we have a customer creation form
  const formFields = await page.locator('input, textarea, select').all();
  console.log(`Found ${formFields.length} form fields on customer creation page`);
  
  // Fill customer form interactively
  console.log('\n📝 FILLING CUSTOMER FORM AUTOMATICALLY...');
  
  try {
    // Customer Name (required)
    await page.fill('input[placeholder="Enter customer name"]', 'Test Customer - Khan User');
    console.log('✅ Filled customer name');
    
    // Wait for any dropdowns to be ready
    await page.waitForTimeout(1000);
    
    // Customer Type (required) - click dropdown first
    const customerTypeDropdown = page.locator('.p-dropdown').first();
    if (await customerTypeDropdown.isVisible()) {
      await customerTypeDropdown.click();
      await page.waitForTimeout(500);
      await page.click('li:has-text("Individual")');
      console.log('✅ Selected customer type: Individual');
    }
    
    // Contact Email
    await page.fill('input[placeholder="Email or phone number"]', 'test.khan@example.com');
    console.log('✅ Filled contact email');
    
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'test-results/06-customer-form-filled.png', fullPage: true });
    
    // Click Create Customer button
    const createButton = page.locator('button:has-text("Create Customer"), button:has-text("Save")').first();
    if (await createButton.isVisible()) {
      await createButton.click();
      console.log('✅ Clicked Create Customer button');
      
      // Wait for customer creation to complete
      await page.waitForTimeout(3000);
      
      console.log('After customer creation, current URL:', page.url());
      await page.screenshot({ path: 'test-results/07-after-customer-creation.png', fullPage: true });
      
      // Check for success message
      const successMessage = await page.locator('.p-toast-message-success, .success, .alert-success').first();
      if (await successMessage.isVisible()) {
        const messageText = await successMessage.textContent();
        console.log('✅ Success message:', messageText?.trim());
      }
      
    } else {
      console.log('❌ Create Customer button not found');
    }
    
  } catch (error) {
    console.log('⚠️ Error during customer creation:', error.message);
  }
  
  // Step 3: Navigate to invoice creation
  console.log('\n🧾 Creating invoice...');
  
  await page.goto('http://localhost:8000/invoices/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('Invoice creation page:', page.url());
  await page.screenshot({ path: 'test-results/08-invoice-creation-page.png', fullPage: true });
  
  // Fill invoice form automatically
  console.log('\n📝 FILLING INVOICE FORM AUTOMATICALLY...');
  
  try {
    // Customer selection (required)
    const customerDropdown = page.locator('.p-autocomplete, .p-dropdown').first();
    if (await customerDropdown.isVisible()) {
      await customerDropdown.click();
      await page.waitForTimeout(500);
      
      // Search for the customer we just created
      const searchInput = page.locator('input.p-autocomplete-input, input.p-dropdown-filter');
      if (await searchInput.isVisible()) {
        await searchInput.fill('Test Customer - Khan User');
        await page.waitForTimeout(1000);
      }
      
      // Click on the customer in the dropdown
      await page.click('li:has-text("Test Customer - Khan User")');
      console.log('✅ Selected customer for invoice');
    }
    
    // Invoice Number (required)
    const invoiceNumberField = page.locator('input[name="invoice_number"], input[placeholder*="invoice"], input[placeholder*="number"]').first();
    if (await invoiceNumberField.isVisible()) {
      const invoiceNumber = 'INV-KHAN-' + Date.now();
      await invoiceNumberField.fill(invoiceNumber);
      console.log('✅ Filled invoice number:', invoiceNumber);
    }
    
    // Dates (required)
    const today = new Date().toISOString().split('T')[0];
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    const dueDateStr = dueDate.toISOString().split('T')[0];
    
    const dateFields = await page.locator('input.p-inputtext[placeholder*="Date"], .p-calendar input').all();
    if (dateFields.length >= 1) {
      await dateFields[0].fill(today);
      console.log('✅ Set issue date:', today);
    }
    if (dateFields.length >= 2) {
      await dateFields[1].fill(dueDateStr);
      console.log('✅ Set due date:', dueDateStr);
    }
    
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'test-results/09-invoice-form-filled.png', fullPage: true });
    
    // Add line item (required)
    console.log('📦 Adding line items...');
    
    // Fill line item details
    const descFields = await page.locator('input[placeholder*="description"], textarea').all();
    if (descFields.length > 0) {
      await descFields[0].fill('Professional Services - Interactive Test');
      console.log('✅ Filled line item description');
    }
    
    const qtyFields = await page.locator('input[name*="quantity"], input.p-inputnumber input').all();
    if (qtyFields.length > 0) {
      await qtyFields[0].fill('5');
      console.log('✅ Set quantity: 5');
    }
    
    const priceFields = await page.locator('input[name*="price"], input[name*="unit_price"]').all();
    if (priceFields.length > 0) {
      await priceFields[0].fill('100.00');
      console.log('✅ Set unit price: $100.00');
    }
    
    await page.waitForTimeout(2000);
    
    // Submit invoice
    console.log('💾 Submitting invoice...');
    
    const saveButton = page.locator('button:has-text("Save as Draft")').first();
    if (await saveButton.isVisible()) {
      await saveButton.click();
      console.log('✅ Clicked Save as Draft button');
      
      // Wait for invoice creation to complete
      await page.waitForTimeout(5000);
      
      console.log('After invoice creation, current URL:', page.url());
      await page.screenshot({ path: 'test-results/10-after-invoice-creation.png', fullPage: true });
      
      // Check for success message
      const invoiceSuccess = await page.locator('.p-toast-message-success, .success, .alert-success').first();
      if (await invoiceSuccess.isVisible()) {
        const messageText = await invoiceSuccess.textContent();
        console.log('✅ Invoice success message:', messageText?.trim());
      }
      
    } else {
      console.log('❌ Save button not found');
    }
    
  } catch (error) {
    console.log('⚠️ Error during invoice creation:', error.message);
  }
  
  console.log('\n✅ AUTOMATED SESSION COMPLETE!');
  console.log('Screenshots saved in test-results/ directory');
  console.log('\n📊 Summary:');
  console.log('- Login: ✅ Khan user');
  console.log('- Customer creation: Attempted');
  console.log('- Invoice creation: Attempted');
  console.log('\n🔍 Check Laravel logs for any validation errors');
});