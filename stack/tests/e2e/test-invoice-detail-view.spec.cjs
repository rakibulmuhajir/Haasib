const { test, expect } = require('@playwright/test');

test('Test Existing Invoice Detail View', async ({ page }) => {
  console.log('🧾 Testing existing invoice detail viewing...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Check if we have existing invoices by going directly to a known invoice
  console.log('\n🔍 Testing direct invoice access...');
  
  // Try to access the invoice we created earlier in previous tests
  const knownInvoiceId = 'e846ba49-b7cd-497b-9636-669db8aa41ab'; // From our earlier tests
  
  await page.goto(`http://localhost:8000/invoices/${knownInvoiceId}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Direct invoice page loaded:', page.url());
  await page.screenshot({ path: 'test-results/direct-invoice-view.png', fullPage: true });
  
  // Check if this is actually an invoice detail page
  const pageContent = await page.content();
  const currentUrl = page.url();
  
  console.log('Current URL:', currentUrl);
  console.log('Page contains invoice-related content:', pageContent.includes('invoice') || pageContent.includes('Invoice'));
  
  if (currentUrl.includes('/invoices/') && currentUrl.length > 30 && !currentUrl.includes('/create')) {
    console.log('✅ Successfully accessed invoice detail page!');
    
    // Look for specific invoice details
    const hasInvoiceNumber = pageContent.includes('INV-') || pageContent.includes('Invoice #');
    const hasCustomerInfo = pageContent.includes('customer') || pageContent.includes('Customer') || pageContent.includes('Bill to');
    const hasAmount = pageContent.includes('$') || pageContent.includes('Total') || pageContent.includes('Amount');
    const hasLineItems = pageContent.includes('item') || pageContent.includes('Description') || pageContent.includes('Quantity');
    const hasStatus = pageContent.includes('status') || pageContent.includes('Status');
    const hasActions = pageContent.includes('Edit') || pageContent.includes('Send') || pageContent.includes('Print') || pageContent.includes('PDF');
    
    console.log('Invoice detail elements found:');
    console.log('  - Invoice Number:', hasInvoiceNumber ? '✅' : '❌');
    console.log('  - Customer Info:', hasCustomerInfo ? '✅' : '❌');
    console.log('  - Amount Info:', hasAmount ? '✅' : '❌');
    console.log('  - Line Items:', hasLineItems ? '✅' : '❌');
    console.log('  - Status:', hasStatus ? '✅' : '❌');
    console.log('  - Action Buttons:', hasActions ? '✅' : '❌');
    
    // Look for action buttons
    const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")').first();
    const sendButton = page.locator('button:has-text("Send"), a:has-text("Send")').first();
    const printButton = page.locator('button:has-text("Print"), a:has-text("Print")').first();
    const pdfButton = page.locator('button:has-text("PDF"), a:has-text("PDF")').first();
    
    console.log('Action buttons found:');
    console.log('  - Edit:', await editButton.isVisible() ? '✅' : '❌');
    console.log('  - Send:', await sendButton.isVisible() ? '✅' : '❌');
    console.log('  - Print:', await printButton.isVisible() ? '✅' : '❌');
    console.log('  - PDF:', await pdfButton.isVisible() ? '✅' : '❌');
    
  } else if (currentUrl.includes('/create')) {
    console.log('⚠️ Redirected to invoice creation page - invoice may not exist or access denied');
  } else {
    console.log('⚠️ Unable to access invoice details - may not exist or permission issue');
  }
  
  // Step 3: Check invoices list for view options again with more specific targeting
  console.log('\n📋 Re-checking invoices list for view options...');
  
  await page.goto('http://localhost:8000/invoices', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  // Look specifically for view icons or view buttons
  const viewButtons = await page.locator('button:has-text("View"), a:has-text("View"), .pi-eye, [title*="View"]').all();
  console.log(`Found ${viewButtons.length} "View" buttons/icons`);
  
  // Look for eye icons specifically
  const eyeIcons = await page.locator('.pi-eye, [class*="eye"], button[icon*="eye"]').all();
  console.log(`Found ${eyeIcons.length} eye icons`);
  
  // Look for clickable invoice numbers/rows
  const clickableInvoices = await page.locator('tr:has-text("INV-"), a[href*="invoices"]:has-text("INV"), .invoice-row').all();
  console.log(`Found ${clickableInvoices.length} potentially clickable invoice elements`);
  
  // Take screenshot of the invoices list
  await page.screenshot({ path: 'test-results/invoices-list-detailed.png', fullPage: true });
  
  // If we found view buttons, try clicking one
  if (viewButtons.length > 0) {
    console.log('Clicking "View" button...');
    await viewButtons[0].click();
    await page.waitForTimeout(3000);
    
    console.log('After clicking view button, URL:', page.url());
    await page.screenshot({ path: 'test-results/after-clicking-view.png', fullPage: true });
  } else if (clickableInvoices.length > 0) {
    console.log('Clicking clickable invoice element...');
    await clickableInvoices[0].click();
    await page.waitForTimeout(3000);
    
    console.log('After clicking invoice, URL:', page.url());
    await page.screenshot({ path: 'test-results/after-clicking-invoice.png', fullPage: true });
  }
  
  console.log('\n✅ Invoice detail viewing test completed');
});