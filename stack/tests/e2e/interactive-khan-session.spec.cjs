const { test, expect } = require('@playwright/test');

test('Interactive Customer and Invoice Creation - Khan User', async ({ page }) => {
  console.log('🚀 Starting interactive session with Khan user...');
  
  // Step 1: Login with Khan credentials
  console.log('🔐 Logging in with username: Khan, password: yasirkhan');
  await page.goto('/login');
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
    
    console.log('\n🔄 Trying different approach - checking existing users...');
    
    // Let's try with admin user instead to demonstrate the flow
    console.log('🔐 Trying with admin user instead...');
    await page.goto('/login');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    
    console.log('URL after admin login attempt:', page.url());
    await page.screenshot({ path: 'test-results/04-admin-login.png', fullPage: true });
  }
  
  // Step 2: Navigate to customers
  console.log('\n👤 Navigating to customers...');
  await page.goto('/customers');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('Customers page loaded:', page.url());
  await page.screenshot({ path: 'test-results/05-customers-page.png', fullPage: true });
  
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
    await page.goto('/customers/create');
    await page.waitForLoadState('networkidle');
  }
  
  await page.waitForTimeout(2000);
  console.log('Customer creation page:', page.url());
  await page.screenshot({ path: 'test-results/06-customer-creation-page.png', fullPage: true });
  
  // Check if we have a customer creation form
  const formFields = await page.locator('input, textarea, select').all();
  console.log(`Found ${formFields.length} form fields on customer creation page`);
  
  for (let i = 0; i < Math.min(formFields.length, 10); i++) {
    const field = formFields[i];
    const placeholder = await field.getAttribute('placeholder');
    const name = await field.getAttribute('name');
    const id = await field.getAttribute('id');
    console.log(`Field ${i+1}: placeholder="${placeholder}", name="${name}", id="${id}"`);
  }
  
  // Step 3: Interactive customer creation
  console.log('\n📝 CUSTOMER CREATION - Interactive Mode');
  console.log('Please fill in the customer form:');
  console.log('1. Customer Name (required)');
  console.log('2. Customer Type (required) - select from dropdown');
  console.log('3. Contact Info - email or phone');
  console.log('4. Address (optional)');
  console.log('5. Country (optional)');
  console.log('\nThen click "Create Customer" button');
  
  // Pause for manual customer creation
  await page.pause();
  
  await page.waitForTimeout(3000);
  console.log('After customer creation, current URL:', page.url());
  await page.screenshot({ path: 'test-results/07-after-customer-creation.png', fullPage: true });
  
  // Step 4: Navigate to invoice creation
  console.log('\n🧾 Navigating to invoice creation...');
  
  await page.goto('/invoices/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('Invoice creation page:', page.url());
  await page.screenshot({ path: 'test-results/08-invoice-creation-page.png', fullPage: true });
  
  // Check invoice form fields
  const invoiceFields = await page.locator('input, textarea, select').all();
  console.log(`Found ${invoiceFields.length} form fields on invoice creation page`);
  
  for (let i = 0; i < Math.min(invoiceFields.length, 15); i++) {
    const field = invoiceFields[i];
    const placeholder = await field.getAttribute('placeholder');
    const name = await field.getAttribute('name');
    console.log(`Field ${i+1}: placeholder="${placeholder}", name="${name}"`);
  }
  
  // Step 5: Interactive invoice creation
  console.log('\n📝 INVOICE CREATION - Interactive Mode');
  console.log('Please fill in the invoice form:');
  console.log('1. Customer (select the customer you just created)');
  console.log('2. Invoice Number');
  console.log('3. Issue Date');
  console.log('4. Due Date');
  console.log('5. Currency (should default to USD)');
  console.log('6. Line Items (add at least one):');
  console.log('   - Description');
  console.log('   - Quantity');
  console.log('   - Unit Price');
  console.log('7. Notes (optional)');
  console.log('\nThen click "Save as Draft" or "Save & Send" button');
  
  // Final pause for manual invoice creation
  await page.pause();
  
  await page.waitForTimeout(5000);
  console.log('Final URL after invoice creation:', page.url());
  await page.screenshot({ path: 'test-results/09-after-invoice-creation.png', fullPage: true });
  
  console.log('\n✅ INTERACTIVE SESSION COMPLETE!');
  console.log('All screenshots saved in test-results/ directory');
  console.log('Check the Laravel logs for any validation errors or issues');
});