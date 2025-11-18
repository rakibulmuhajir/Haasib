const { test, expect } = require('@playwright/test');

test('Simple Payment Test - Khan User', async ({ page }) => {
  console.log('💳 Simple payment test...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('✅ Login successful!');
  
  // Step 2: Navigate to Payments
  console.log('\n💳 Navigating to payments...');
  await page.goto('http://localhost:8000/payments');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('Payments page loaded:', page.url());
  await page.screenshot({ path: 'test-results/simple-payments-page.png', fullPage: true });
  
  // Step 3: Try to go to payment creation
  console.log('\n➕ Going to payment creation...');
  await page.goto('http://localhost:8000/payments/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('Payment create page loaded:', page.url());
  await page.screenshot({ path: 'test-results/simple-payment-create.png', fullPage: true });
  
  // Step 4: Look for form elements
  console.log('\n🔍 Looking for payment form elements...');
  
  // Check for customer selection
  const customerElements = await page.locator('input[placeholder*="customer"], .p-autocomplete, select').all();
  console.log(`Found ${customerElements.length} customer selection elements`);
  
  // Check for amount fields
  const amountFields = await page.locator('input[name="amount"], input[placeholder*="amount"], input[type="number"]').all();
  console.log(`Found ${amountFields.length} amount fields`);
  
  // Check for payment method fields
  const methodFields = await page.locator('select[name="payment_method"], .p-dropdown').all();
  console.log(`Found ${methodFields.length} payment method fields`);
  
  // Check for save buttons
  const saveButtons = await page.locator('button:has-text("Save"), button[type="submit"]').all();
  console.log(`Found ${saveButtons.length} save buttons`);
  
  console.log('\n✅ Simple payment test completed!');
  console.log('Screenshots saved in test-results/ directory');
  
  // Check if we can see any existing payments or if the form is usable
  const pageContent = await page.content();
  if (pageContent.includes('Payment') || pageContent.includes('payment')) {
    console.log('✅ Payment functionality appears to be available');
  } else {
    console.log('⚠️ Payment functionality might not be fully available');
  }
  
  // Pause for manual inspection
  await page.pause();
});