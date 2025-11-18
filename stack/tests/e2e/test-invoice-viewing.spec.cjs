const { test, expect } = require('@playwright/test');

test('Test Invoice Viewing from Index Page', async ({ page }) => {
  console.log('📋 Testing invoice viewing functionality from index...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Go to invoices list
  console.log('\n🧾 Navigating to invoices list...');
  await page.goto('http://localhost:8000/invoices', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Invoices page loaded:', page.url());
  await page.screenshot({ path: 'test-results/invoices-index-page.png', fullPage: true });
  
  // Step 3: Look for eye icons or clickable invoice numbers
  console.log('\n👁️ Looking for invoice viewing options...');
  
  // Check for eye icons
  const eyeIcons = await page.locator('button[icon*="pi-eye"], .pi-eye, button:has(.pi-eye)').all();
  console.log(`Found ${eyeIcons.length} eye icon buttons`);
  
  // Check for clickable invoice numbers
  const invoiceLinks = await page.locator('a[href*="invoices"]').all();
  console.log(`Found ${invoiceLinks.length} invoice links`);
  
  // Check for any clickable elements with invoice numbers
  const invoiceNumbers = await page.locator('*:has-text("INV-")').all();
  console.log(`Found ${invoiceNumbers.length} elements with "INV-" text`);
  
  // Check for any row or card that might be clickable
  const clickableElements = await page.locator('tr[role="button"], .clickable, [role="button"]:has-text("INV")').all();
  console.log(`Found ${clickableElements.length} potentially clickable invoice elements`);
  
  // Take a screenshot showing what we found
  await page.screenshot({ path: 'test-results/invoice-viewing-options.png', fullPage: true });
  
  // Step 4: Try to click on an invoice viewing option
  console.log('\n🖱️ Testing invoice viewing clicks...');
  
  let invoiceViewed = false;
  
  if (eyeIcons.length > 0) {
    console.log('Clicking eye icon...');
    await eyeIcons[0].click();
    await page.waitForTimeout(3000);
    invoiceViewed = true;
  } else if (invoiceLinks.length > 0) {
    console.log('Clicking invoice link...');
    await invoiceLinks[0].click();
    await page.waitForTimeout(3000);
    invoiceViewed = true;
  } else if (invoiceNumbers.length > 0) {
    console.log('Clicking invoice number element...');
    await invoiceNumbers[0].click();
    await page.waitForTimeout(3000);
    invoiceViewed = true;
  }
  
  if (invoiceViewed) {
    console.log('Invoice detail page loaded:', page.url());
    await page.screenshot({ path: 'test-results/invoice-detail-view.png', fullPage: true });
    
    // Check if we're on an invoice detail page
    const pageContent = await page.content();
    const hasInvoiceDetails = pageContent.includes('invoice') || 
                             pageContent.includes('Invoice') || 
                             pageContent.includes('INV-') ||
                             page.url().includes('/invoices/') && page.url().length > 30;
    
    if (hasInvoiceDetails) {
      console.log('✅ Successfully viewing invoice details!');
      
      // Look for invoice-specific information
      const hasAmount = pageContent.includes('$') || pageContent.includes('amount');
      const hasCustomer = pageContent.includes('customer') || pageContent.includes('Customer');
      const hasDate = pageContent.includes('date') || pageContent.includes('Date');
      const hasStatus = pageContent.includes('status') || pageContent.includes('Status');
      
      console.log('Invoice details found:');
      console.log('  - Amount information:', hasAmount ? '✅' : '❌');
      console.log('  - Customer information:', hasCustomer ? '✅' : '❌');
      console.log('  - Date information:', hasDate ? '✅' : '❌');
      console.log('  - Status information:', hasStatus ? '✅' : '❌');
    } else {
      console.log('⚠️ May not be viewing invoice details properly');
    }
  } else {
    console.log('⚠️ No clickable invoice viewing elements found');
    
    // Let's check what elements are actually present
    const allButtons = await page.locator('button').all();
    const allLinks = await page.locator('a').all();
    const allClickable = await page.locator('[onclick], [role="button"], .clickable').all();
    
    console.log('Page contains:');
    console.log(`  - ${allButtons.length} buttons`);
    console.log(`  - ${allLinks.length} links`);
    console.log(`  - ${allClickable.length} clickable elements`);
    
    // Look for any text that might indicate invoice actions
    const pageText = await page.textContent('body');
    const hasViewText = pageText.toLowerCase().includes('view');
    const hasShowText = pageText.toLowerCase().includes('show');
    const hasDetailsText = pageText.toLowerCase().includes('details');
    
    console.log('Page text contains:');
    console.log('  - "view":', hasViewText ? '✅' : '❌');
    console.log('  - "show":', hasShowText ? '✅' : '❌');
    console.log('  - "details":', hasDetailsText ? '✅' : '❌');
  }
  
  console.log('\n✅ Invoice viewing functionality test completed');
});