const { test, expect } = require('@playwright/test');

test('Debug Invoice Show Component', async ({ page }) => {
  console.log('🔍 Debugging invoice show component...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Go directly to invoice show page
  console.log('\n🧾 Accessing invoice show page...');
  const invoiceId = 'e846ba49-b7cd-497b-9636-669db8aa41ab';
  
  await page.goto(`http://localhost:8000/invoices/${invoiceId}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(5000);
  
  console.log('Invoice page URL:', page.url());
  
  // Check page content and structure
  const pageContent = await page.content();
  const pageText = await page.textContent('body');
  const pageTitle = await page.title();
  
  console.log('Page title:', pageTitle);
  console.log('Page content length:', pageContent.length);
  console.log('Page contains "invoice":', pageText.toLowerCase().includes('invoice'));
  console.log('Page contains "error":', pageText.toLowerCase().includes('error'));
  console.log('Page contains "undefined":', pageText.toLowerCase().includes('undefined'));
  
  // Look for specific error indicators
  const errorElements = await page.locator('.error, .p-error, .text-red-600, [role="alert"], .exception').all();
  console.log('Found error elements:', errorElements.length);
  
  for (let i = 0; i < Math.min(errorElements.length, 3); i++) {
    const errorText = await errorElements[i].textContent();
    if (errorText && errorText.trim()) {
      console.log(`Error element ${i + 1}:`, errorText.trim());
    }
  }
  
  // Look for Vue app mounting
  const vueApp = await page.locator('#app').first();
  const vueAppExists = await vueApp.isVisible();
  console.log('Vue app mounted:', vueAppExists);
  
  // Look for specific invoice content
  const invoiceElements = await page.locator('*:has-text("INV-"), *:has-text("Invoice"), *:has-text("Total")').all();
  console.log('Found invoice-related elements:', invoiceElements.length);
  
  for (let i = 0; i < Math.min(invoiceElements.length, 5); i++) {
    try {
      const element = invoiceElements[i];
      const text = await element.textContent();
      if (text && text.trim()) {
        console.log(`Invoice element ${i + 1}: "${text.trim().substring(0, 100)}"`);
      }
    } catch (error) {
      console.log(`Error reading element ${i + 1}: ${error.message}`);
    }
  }
  
  // Check for component structure
  const layoutShell = await page.locator('.layout-shell, [class*="LayoutShell"]').all();
  console.log('Found LayoutShell components:', layoutShell.length);
  
  const pageHeader = await page.locator('.page-header, [class*="PageHeader"]').all();
  console.log('Found PageHeader components:', pageHeader.length);
  
  const cardElements = await page.locator('.p-card, [class*="card"]').all();
  console.log('Found Card components:', cardElements.length);
  
  // Check for any console errors (if possible)
  const consoleLogs = await page.evaluate(() => {
    const logs = [];
    // Try to check for common error patterns
    const body = document.body.innerHTML || document.body.innerText;
    
    if (body.includes('TypeError')) {
      logs.push('Page contains TypeError');
    }
    if (body.includes('Cannot read')) {
      logs.push('Page contains "Cannot read" error');
    }
    if (body.includes('undefined')) {
      logs.push('Page contains "undefined" references');
    }
    if (body.includes('null')) {
      logs.push('Page contains "null" references');
    }
    
    return logs;
  });
  
  if (consoleLogs.length > 0) {
    console.log('Potential issues detected:');
    consoleLogs.forEach(log => console.log('  -', log));
  }
  
  // Take screenshot for manual inspection
  await page.screenshot({ path: 'test-results/invoice-show-debug.png', fullPage: true });
  
  console.log('\n✅ Invoice show debug completed');
  console.log('Screenshot saved to test-results/invoice-show-debug.png');
});