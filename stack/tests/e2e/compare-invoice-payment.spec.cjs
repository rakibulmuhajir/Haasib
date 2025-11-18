const { test, expect } = require('@playwright/test');

test('Compare with Invoice Creation', async ({ page }) => {
  console.log('📋 Comparing with invoice creation page...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Try invoice creation first (which we know works)
  console.log('\n🧾 Testing invoice creation page...');
  await page.goto('http://localhost:8000/invoices/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Invoice creation page loaded:', page.url());
  await page.screenshot({ path: 'test-results/invoice-creation-comparison.png', fullPage: true });
  
  const invoicePageText = await page.textContent('body');
  console.log('Invoice page contains "Customer":', invoicePageText.includes('Customer'));
  console.log('Invoice page contains "Amount":', invoicePageText.includes('Amount'));
  console.log('Invoice page contains "Invoice":', invoicePageText.includes('Invoice'));
  
  const invoiceVueApp = await page.evaluate(() => {
    return !!document.querySelector('#app');
  });
  console.log('Invoice page Vue app mounted:', invoiceVueApp);
  
  // Step 3: Try payment creation
  console.log('\n💳 Testing payment creation page...');
  await page.goto('http://localhost:8000/payments/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Payment creation page loaded:', page.url());
  await page.screenshot({ path: 'test-results/payment-creation-comparison.png', fullPage: true });
  
  const paymentPageText = await page.textContent('body');
  console.log('Payment page contains "Create Payment":', paymentPageText.includes('Create Payment'));
  console.log('Payment page contains "Customer":', paymentPageText.includes('Customer'));
  
  const paymentVueApp = await page.evaluate(() => {
    return !!document.querySelector('#app');
  });
  console.log('Payment page Vue app mounted:', paymentVueApp);
  
  console.log('\n✅ Comparison completed');
});