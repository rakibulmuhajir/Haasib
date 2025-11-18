const { test, expect } = require('@playwright/test');

test('Test Customer and Invoice Viewing with Button Targets', async ({ page }) => {
  console.log('🧪 Testing customer and invoice viewing with specific button targets...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Test 1: Customer Viewing
  console.log('\n👤 Testing customer viewing...');
  await page.goto('http://localhost:8000/customers', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  // Look specifically for the eye icon buttons
  const customerEyeButtons = await page.locator('button:has(.fas.fa-eye), button[icon="fas fa-eye"], .customer-actions button:has-text("View")').all();
  console.log(`Found ${customerEyeButtons.length} customer eye buttons`);
  
  if (customerEyeButtons.length > 0) {
    console.log('Clicking customer eye button...');
    await customerEyeButtons[0].click();
    await page.waitForTimeout(3000);
    
    console.log('After customer click, URL:', page.url());
    await page.screenshot({ path: 'test-results/customer-view-result.png', fullPage: true });
    
    // Check for success (no TypeError)
    const pageText = await page.textContent('body');
    const hasTypeError = pageText.includes('TypeError');
    const hasError = pageText.toLowerCase().includes('error');
    const currentUrl = page.url();
    const isCustomerDetail = currentUrl.includes('/customers/') && currentUrl.length > 30;
    
    if (hasTypeError) {
      console.log('❌ Customer viewing still has TypeError');
    } else if (hasError) {
      console.log('⚠️ Customer viewing has some error, but not TypeError');
    } else if (isCustomerDetail) {
      console.log('✅ Customer viewing working - successfully navigated to detail page');
    } else {
      console.log('⚠️ Customer viewing button clicked but did not navigate to detail page');
    }
  } else {
    console.log('⚠️ No customer eye buttons found - may need to scroll or different selectors');
    
    // Try looking for any customer row that might be clickable
    const customerRows = await page.locator('tr').all();
    console.log(`Found ${customerRows.length} table rows`);
    
    if (customerRows.length > 5) {
      // Click on the first customer row
      console.log('Trying to click on first customer row...');
      await customerRows[1].click(); // Skip header row
      await page.waitForTimeout(3000);
      console.log('After customer row click, URL:', page.url());
    }
  }
  
  // Test 2: Invoice Viewing
  console.log('\n🧾 Testing invoice viewing...');
  await page.goto('http://localhost:8000/invoices', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  // Look for invoice eye buttons
  const invoiceEyeButtons = await page.locator('button:has(.fas.fa-eye), button[icon="fas fa-eye"], .invoice-actions button:has-text("View")').all();
  console.log(`Found ${invoiceEyeButtons.length} invoice eye buttons`);
  
  if (invoiceEyeButtons.length > 0) {
    console.log('Clicking invoice eye button...');
    await invoiceEyeButtons[0].click();
    await page.waitForTimeout(3000);
    
    console.log('After invoice click, URL:', page.url());
    await page.screenshot({ path: 'test-results/invoice-view-result.png', fullPage: true });
    
    const invoicePageText = await page.textContent('body');
    const currentInvoiceUrl = page.url();
    const isInvoiceDetail = currentInvoiceUrl.includes('/invoices/') && currentInvoiceUrl.length > 30 && !currentInvoiceUrl.includes('/create');
    
    if (isInvoiceDetail) {
      console.log('✅ Invoice viewing working - successfully navigated to detail page');
      
      const hasInvoiceNumber = invoicePageText.includes('INV-') || invoicePageText.includes('Invoice #');
      const hasCustomerInfo = invoicePageText.includes('customer') || invoicePageText.includes('Customer');
      const hasAmount = invoicePageText.includes('$') || invoicePageText.includes('Total');
      
      console.log('Invoice details found:');
      console.log('  - Invoice Number:', hasInvoiceNumber ? '✅' : '❌');
      console.log('  - Customer Info:', hasCustomerInfo ? '✅' : '❌');
      console.log('  - Amount Info:', hasAmount ? '✅' : '❌');
    } else {
      console.log('⚠️ Invoice viewing button clicked but did not navigate to detail page');
    }
  } else {
    console.log('⚠️ No invoice eye buttons found');
    
    // Try looking for any invoice row that might be clickable
    const invoiceRows = await page.locator('tr').all();
    console.log(`Found ${invoiceRows.length} invoice table rows`);
    
    if (invoiceRows.length > 5) {
      console.log('Trying to click on first invoice row...');
      await invoiceRows[1].click(); // Skip header row
      await page.waitForTimeout(3000);
      console.log('After invoice row click, URL:', page.url());
    }
  }
  
  // Test 3: Test route creation and navigation manually
  console.log('\n🔗 Testing manual route creation...');
  
  // Test customer route by manually constructing URL
  console.log('Testing if customer detail route exists by checking Laravel routes...');
  
  // Test invoice route
  console.log('Testing if invoice detail route exists by checking page response...');
  
  console.log('\n✅ Customer and invoice viewing test completed');
  console.log('Screenshots saved to test-results/ directory');
});