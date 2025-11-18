const { test, expect } = require('@playwright/test');

test('Debug Payment Creation Page', async ({ page }) => {
  console.log('🔍 Debug payment creation page...');
  
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
  
  // Take screenshot first
  await page.screenshot({ path: 'test-results/debug-payment-creation.png', fullPage: true });
  
  // Check page content
  const pageText = await page.textContent('body');
  console.log('Page contains "Create Payment":', pageText.includes('Create Payment'));
  console.log('Page contains "Payment Details":', pageText.includes('Payment Details'));
  console.log('Page contains "Customer":', pageText.includes('Customer'));
  console.log('Page contains "Amount":', pageText.includes('Amount'));
  
  // Check for any error messages
  const errorElements = await page.locator('.error, .p-error, .text-red-600, [role="alert"]').all();
  console.log('Found error elements:', errorElements.length);
  
  for (let i = 0; i < Math.min(errorElements.length, 3); i++) {
    const errorText = await errorElements[i].textContent();
    if (errorText && errorText.trim()) {
      console.log(`Error ${i + 1}:`, errorText.trim());
    }
  }
  
  // Check for form elements with different selectors
  const allInputs = await page.locator('input, select, textarea, button').all();
  console.log('Total form elements found:', allInputs.length);
  
  // Look for PrimeVue components specifically
  const dropdowns = await page.locator('.p-dropdown').all();
  const inputTexts = await page.locator('.p-inputtext').all();
  const buttons = await page.locator('.p-button').all();
  
  console.log('PrimeVue dropdowns found:', dropdowns.length);
  console.log('PrimeVue input texts found:', inputTexts.length);
  console.log('PrimeVue buttons found:', buttons.length);
  
  // Check page title
  const title = await page.title();
  console.log('Page title:', title);
  
  // Check if we have any Vue content
  const hasVueContent = await page.evaluate(() => {
    return !!document.querySelector('#app');
  });
  console.log('Vue app mounted:', hasVueContent);
  
  console.log('\n✅ Debug completed');
});