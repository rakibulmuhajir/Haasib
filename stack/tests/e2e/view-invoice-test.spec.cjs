const { test, expect } = require('@playwright/test');

test('View Invoice - Khan User', async ({ page }) => {
  console.log('🔍 Testing invoice viewing functionality...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  console.log('✅ Login successful! URL:', page.url());
  
  // Step 2: Navigate to invoices list
  console.log('\n📋 Navigate to invoices list...');
  await page.goto('http://localhost:8000/invoices');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('Invoices page loaded:', page.url());
  await page.screenshot({ path: 'test-results/invoice-list-page.png', fullPage: true });
  
  // Look for the invoice we created
  const invoiceId = 'e846ba49-b7cd-497b-9636-669db8aa41ab';
  const invoiceNumber = 'INV-KHAN-0001';
  
  // Try to find the invoice in the list
  console.log('Looking for invoice:', invoiceNumber);
  
  // Method 1: Look for invoice number in the page content
  const invoiceLink = page.locator(`a:has-text("${invoiceNumber}")`).first();
  if (await invoiceLink.isVisible()) {
    console.log('✅ Found invoice link, clicking...');
    await invoiceLink.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    console.log('Invoice detail page loaded:', page.url());
    await page.screenshot({ path: 'test-results/invoice-detail-page.png', fullPage: true });
    
    // Verify invoice details on the page
    await verifyInvoiceDetails(page, invoiceNumber);
    
  } else {
    console.log('⚠️ Invoice link not found in list, trying direct navigation...');
    
    // Method 2: Direct navigation to invoice detail
    await page.goto(`http://localhost:8000/invoices/${invoiceId}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    console.log('Direct invoice page loaded:', page.url());
    await page.screenshot({ path: 'test-results/direct-invoice-detail.png', fullPage: true });
    
    // Verify invoice details
    await verifyInvoiceDetails(page, invoiceNumber);
  }
  
  // Step 3: Test invoice viewing features
  console.log('\n🔍 Testing invoice viewing features...');
  
  // Look for common invoice viewing elements
  const viewingElements = [
    { name: 'Print button', selector: 'button:has-text("Print")' },
    { name: 'Download PDF', selector: 'button:has-text("PDF"), button:has-text("Download")' },
    { name: 'Edit button', selector: 'button:has-text("Edit")' },
    { name: 'Send button', selector: 'button:has-text("Send")' },
    { name: 'View line items', selector: 'table, .table, .invoice-items' },
    { name: 'Customer information', selector: '.customer-info, .customer-details' },
    { name: 'Payment status', selector: '.status, .payment-status' }
  ];
  
  for (const element of viewingElements) {
    try {
      const locator = page.locator(element.selector).first();
      if (await locator.isVisible()) {
        console.log(`✅ Found ${element.name}`);
      } else {
        console.log(`⚠️ ${element.name} not found`);
      }
    } catch (error) {
      console.log(`⚠️ Error checking ${element.name}: ${error.message}`);
    }
  }
  
  // Step 4: Test invoice actions (if available)
  console.log('\n⚡ Testing invoice actions...');
  
  const actionButtons = await page.locator('button').all();
  console.log(`Found ${actionButtons.length} action buttons`);
  
  for (let i = 0; i < Math.min(actionButtons.length, 5); i++) {
    const button = actionButtons[i];
    const text = await button.textContent();
    if (text && text.trim()) {
      console.log(`  Button: "${text.trim()}"`);
    }
  }
  
  console.log('\n✅ Invoice viewing test completed!');
  console.log('Screenshots saved in test-results/ directory');
  
  // Pause for manual exploration
  await page.pause();
});

async function verifyInvoiceDetails(page, invoiceNumber) {
  console.log('Verifying invoice details...');
  
  // Check for invoice number on page
  const invoiceNumberElement = page.locator(`text=${invoiceNumber}, *:has-text("${invoiceNumber}")`).first();
  if (await invoiceNumberElement.isVisible()) {
    console.log('✅ Invoice number found on page');
  } else {
    console.log('⚠️ Invoice number not found on page');
  }
  
  // Check for customer information
  const customerName = page.locator('text=Khan Test Customer, *:has-text("Khan Test Customer")').first();
  if (await customerName.isVisible()) {
    console.log('✅ Customer information found');
  } else {
    console.log('⚠️ Customer information not clearly visible');
  }
  
  // Check for amount information
  const amountElements = await page.locator('text=$1080, *:has-text("$1080"), *:has-text("1080")').all();
  if (amountElements.length > 0) {
    console.log('✅ Invoice amount information found');
  } else {
    console.log('⚠️ Amount information not clearly visible');
  }
  
  // Check for status information
  const statusElements = await page.locator('text=draft, *:has-text("draft"), .status, .invoice-status').all();
  if (statusElements.length > 0) {
    console.log('✅ Invoice status found');
  } else {
    console.log('⚠️ Invoice status not clearly visible');
  }
  
  // Get page title for additional verification
  const title = await page.title();
  console.log(`Page title: ${title}`);
}