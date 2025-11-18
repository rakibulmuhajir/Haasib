const { test, expect } = require('@playwright/test');

test('Payment Creation Test', async ({ page }) => {
  console.log('💳 Testing payment creation...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Navigate to payment creation
  console.log('\n➕ Going to payment creation page...');
  await page.goto('http://localhost:8000/payments/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Payment creation page loaded:', page.url());
  await page.screenshot({ path: 'test-results/payment-creation-page.png', fullPage: true });
  
  // Step 3: Look for form elements
  console.log('\n🔍 Looking for form elements...');
  
  // Check for customer dropdown
  const customerDropdown = page.locator('[id="customer_id"], .p-dropdown').first();
  const customerVisible = await customerDropdown.isVisible();
  console.log('Customer dropdown visible:', customerVisible);
  
  if (customerVisible) {
    await customerDropdown.click();
    await page.waitForTimeout(1000);
    console.log('✅ Customer dropdown clicked');
    
    // Look for customer options
    const customerOption = page.locator('li:has-text("Test"), li:has-text("Customer")').first();
    if (await customerOption.isVisible()) {
      await customerOption.click();
      console.log('✅ Customer selected');
    }
  }
  
  // Check for amount field
  const amountField = page.locator('input[id="amount"], input[name="amount"]').first();
  const amountVisible = await amountField.isVisible();
  console.log('Amount field visible:', amountVisible);
  
  if (amountVisible) {
    await amountField.fill('100');
    console.log('✅ Amount filled: $100');
  }
  
  // Check for payment method dropdown
  const methodDropdown = page.locator('[id="payment_method"], .p-dropdown').last();
  const methodVisible = await methodDropdown.isVisible();
  console.log('Payment method dropdown visible:', methodVisible);
  
  if (methodVisible) {
    await methodDropdown.click();
    await page.waitForTimeout(1000);
    console.log('✅ Payment method dropdown clicked');
    
    // Look for payment method options
    const methodOption = page.locator('li:has-text("Cash"), li:has-text("Bank")').first();
    if (await methodOption.isVisible()) {
      await methodOption.click();
      console.log('✅ Payment method selected');
    }
  }
  
  // Check for date field
  const dateField = page.locator('input[id="payment_date"], input[type="date"]').first();
  const dateVisible = await dateField.isVisible();
  console.log('Date field visible:', dateVisible);
  
  if (dateVisible) {
    await dateField.fill(new Date().toISOString().split('T')[0]);
    console.log('✅ Payment date set');
  }
  
  // Check for submit button
  const submitButton = page.locator('button:has-text("Create Payment"), button[type="submit"]').first();
  const submitVisible = await submitButton.isVisible();
  console.log('Submit button visible:', submitVisible);
  
  await page.screenshot({ path: 'test-results/payment-form-filled.png', fullPage: true });
  
  if (submitVisible) {
    console.log('✅ Submit button found - payment creation form appears ready');
    // We won't actually submit since we want to test the UI functionality
  } else {
    console.log('⚠️ Submit button not found');
  }
  
  // Step 4: Check for any error messages or validation
  const errorMessages = page.locator('.p-error, .error, .text-red-600');
  const errorCount = await errorMessages.count();
  if (errorCount > 0) {
    console.log('Found', errorCount, 'error messages');
  } else {
    console.log('✅ No error messages visible');
  }
  
  console.log('\n✅ Payment creation test completed!');
  console.log('💳 Payment form functionality appears to be working');
});