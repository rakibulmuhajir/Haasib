const { test, expect } = require('@playwright/test');

test('Interactive Customer and Invoice Creation', async ({ page }) => {
  console.log('🚀 Starting interactive session...');
  
  // Step 1: Login with specified credentials
  console.log('🔐 Logging in with username: khan, password: yasirkhan');
  await page.goto('/login');
  await page.waitForLoadState('networkidle');
  
  // Fill login form
  await page.fill('input[name="username"]', 'khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  
  // Wait for login to complete
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('✅ Login completed!');
  console.log('Current URL:', page.url());
  
  // Take screenshot of dashboard
  await page.screenshot({ path: 'test-results/dashboard-after-login.png', fullPage: true });
  
  // Pause for interactive exploration
  console.log('\n🛑 LOGIN COMPLETE - Now you can explore the system');
  console.log('Current page:', page.url());
  console.log('Available actions:');
  console.log('1. Navigate to customers');
  console.log('2. Navigate to invoices');
  console.log('3. Check navigation');
  console.log('\nProceeding to customer creation...');
  
  // Step 2: Navigate to customer creation
  console.log('\n👤 Creating customer...');
  
  // Try to find customers in navigation
  await page.goto('/customers');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('Customers page loaded:', page.url());
  await page.screenshot({ path: 'test-results/customers-page.png', fullPage: true });
  
  // Look for "Create Customer" or similar button
  const createButtons = await page.locator('button, a').all();
  let createCustomerFound = false;
  
  for (const button of createButtons) {
    const text = await button.textContent();
    if (text && (text.toLowerCase().includes('create') || text.toLowerCase().includes('add') || text.toLowerCase().includes('new'))) {
      console.log(`Found button: "${text.trim()}"`);
      if (text.toLowerCase().includes('customer') || text.toLowerCase().includes('add')) {
        await button.click();
        createCustomerFound = true;
        break;
      }
    }
  }
  
  if (!createCustomerFound) {
    console.log('Trying direct navigation to customer creation...');
    await page.goto('/customers/create');
    await page.waitForLoadState('networkidle');
  }
  
  await page.waitForTimeout(2000);
  console.log('Customer creation page:', page.url());
  await page.screenshot({ path: 'test-results/customer-creation-page.png', fullPage: true });
  
  // Step 3: Fill customer form interactively
  console.log('\n📝 Please fill in the customer form manually...');
  console.log('Fields to fill:');
  console.log('- Customer Name');
  console.log('- Customer Type');
  console.log('- Contact Email/Phone');
  console.log('- Address (optional)');
  console.log('- Country (optional)');
  console.log('\nWhen done, click "Create Customer" button');
  
  // Pause for manual customer creation
  await page.pause();
  
  // Wait for customer creation to complete
  await page.waitForTimeout(3000);
  
  // Check if customer was created successfully
  const currentUrl = page.url();
  console.log('After customer creation, current URL:', currentUrl);
  await page.screenshot({ path: 'test-results/after-customer-creation.png', fullPage: true });
  
  // Step 4: Navigate to invoice creation
  console.log('\n🧾 Creating invoice...');
  
  await page.goto('/invoices/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('Invoice creation page:', page.url());
  await page.screenshot({ path: 'test-results/invoice-creation-page.png', fullPage: true });
  
  // Step 5: Fill invoice form interactively
  console.log('\n📝 Please fill in the invoice form manually...');
  console.log('Required fields:');
  console.log('- Customer (select the customer you just created)');
  console.log('- Invoice Number');
  console.log('- Issue Date');
  console.log('- Due Date');
  console.log('- Currency');
  console.log('- Line Items (at least one):');
  console.log('  * Description');
  console.log('  * Quantity');
  console.log('  * Unit Price');
  console.log('\nWhen done, click "Save as Draft" or "Save & Send" button');
  
  // Pause for manual invoice creation
  await page.pause();
  
  // Wait for invoice creation to complete
  await page.waitForTimeout(5000);
  
  console.log('After invoice creation, current URL:', page.url());
  await page.screenshot({ path: 'test-results/after-invoice-creation.png', fullPage: true });
  
  console.log('\n✅ INTERACTIVE SESSION COMPLETE!');
  console.log('Screenshots saved in test-results/ directory');
});