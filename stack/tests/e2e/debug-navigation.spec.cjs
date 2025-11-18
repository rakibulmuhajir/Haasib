const { test, expect } = require('@playwright/test');

test('debug navigation elements', async ({ page }) => {
  // Login
  await page.goto('/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  
  // Wait for navigation after login
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  // Take screenshot to see what's available
  await page.screenshot({ path: 'test-results/debug-navigation.png', fullPage: true });
  
  // Find all navigation links
  const allLinks = await page.locator('a').all();
  console.log(`Found ${allLinks.length} links on the page:`);
  
  for (let i = 0; i < Math.min(allLinks.length, 20); i++) {
    const link = allLinks[i];
    const text = await link.textContent();
    const href = await link.getAttribute('href');
    if (text && text.trim()) {
      console.log(`  - ${text.trim()} -> ${href}`);
    }
  }
  
  // Look for specific invoice-related elements
  const invoiceLinks = await page.locator('a[href*="invoice"], a:has-text("Invoice")').all();
  console.log(`\nFound ${invoiceLinks.length} invoice-related links:`);
  
  for (const link of invoiceLinks) {
    const text = await link.textContent();
    const href = await link.getAttribute('href');
    console.log(`  - ${text?.trim()} -> ${href}`);
  }
  
  // Try to directly navigate to invoices
  console.log('\nTrying to navigate directly to /invoices...');
  await page.goto('/invoices');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/debug-invoices-page.png', fullPage: true });
  
  console.log('Successfully navigated to invoices page');
});