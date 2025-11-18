const { test, expect } = require('@playwright/test');

test('Debug Payment Form Submission', async ({ page }) => {
  console.log('🔍 Debugging payment form submission...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Go to payment creation page
  console.log('\n💳 Going to payment creation page...');
  await page.goto('http://localhost:8000/payments/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  // Fill the form with minimal required data
  console.log('Filling payment form...');
  
  // Select customer
  const customerDropdown = page.locator('[id="customer_id"], .p-dropdown').first();
  await customerDropdown.click();
  await page.waitForTimeout(1000);
  if (await page.locator('li').count() > 0) {
    await page.locator('li').first().click();
    console.log('✅ Customer selected');
  }
  
  // Fill amount
  const amountField = page.locator('input[id="amount"], .p-inputnumber input').first();
  await amountField.click();
  await amountField.fill('150.00');
  console.log('✅ Amount set to $150.00');
  
  // Select payment method
  const methodDropdown = page.locator('[id="payment_method"], .p-dropdown').last();
  await methodDropdown.click();
  await page.waitForTimeout(1000);
  if (await page.locator('li').count() > 0) {
    await page.locator('li').first().click();
    console.log('✅ Payment method selected');
  }
  
  // Set payment date
  const today = new Date().toISOString().split('T')[0];
  const dateField = page.locator('input[id="payment_date"], input[type="date"]').first();
  await dateField.fill(today);
  console.log('✅ Payment date set');
  
  // Check form data before submission
  console.log('Checking form data before submission...');
  
  // Get form action
  const form = page.locator('form').first();
  const formAction = await form.getAttribute('action');
  console.log('Form action:', formAction);
  
  // Get form method
  const formMethod = await form.getAttribute('method');
  console.log('Form method:', formMethod);
  
  // Take screenshot before submission
  await page.screenshot({ path: 'test-results/before-form-submission.png', fullPage: true });
  
  // Intercept network requests to see what happens when we submit
  console.log('Setting up network monitoring...');
  
  const responses = [];
  page.on('response', response => {
    if (response.url().includes('payments')) {
      responses.push({
        url: response.url(),
        status: response.status(),
        method: response.request().method(),
        postData: response.request().postData()
      });
      console.log(`Payment API Response: ${response.request().method()} ${response.url()} - ${response.status()}`);
    }
  });
  
  // Submit the form
  console.log('Submitting payment form...');
  const submitButton = page.locator('button:has-text("Create Payment"), button[type="submit"]').first();
  
  if (await submitButton.isVisible()) {
    await submitButton.click();
    console.log('✅ Submit button clicked');
    
    // Wait for network activity
    await page.waitForTimeout(5000);
    
    console.log(`Captured ${responses.length} payment-related responses:`);
    responses.forEach((response, index) => {
      console.log(`Response ${index + 1}:`);
      console.log(`  URL: ${response.url}`);
      console.log(`  Method: ${response.method}`);
      console.log(`  Status: ${response.status}`);
      console.log(`  Post Data: ${response.postData || 'None'}`);
    });
    
    console.log('Current URL after submission:', page.url());
    await page.screenshot({ path: 'test-results/after-form-submission.png', fullPage: true });
    
    // Check for any error messages
    const errorElements = await page.locator('.error, .p-error, .text-red-600, [role="alert"]').all();
    console.log(`Found ${errorElements.length} error elements`);
    
    for (let i = 0; i < Math.min(errorElements.length, 3); i++) {
      const errorText = await errorElements[i].textContent();
      if (errorText && errorText.trim()) {
        console.log(`Error ${i + 1}:`, errorText.trim());
      }
    }
    
    // Check page content for validation messages
    const pageContent = await page.content();
    if (pageContent.includes('required') || pageContent.includes('invalid')) {
      console.log('Page contains validation-related text');
    }
    
  } else {
    console.log('❌ Submit button not found');
  }
  
  console.log('\n✅ Payment form submission debugging completed');
});