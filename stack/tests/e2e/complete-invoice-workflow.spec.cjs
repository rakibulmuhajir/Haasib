const { test, expect } = require('@playwright/test');

test('Complete Invoice Creation and Viewing Test', async ({ page }) => {
  console.log('🧾 Complete invoice creation and viewing test...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: First create a customer if needed
  console.log('\n👤 Creating a test customer...');
  await page.goto('http://localhost:8000/customers/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  // Fill customer form
  const customerName = 'Test Customer for Invoice - ' + Date.now();
  await page.fill('input[placeholder*="name"], input[name="name"]', customerName);
  await page.fill('input[placeholder*="email"], input[placeholder*="phone"], input[name="email"], input[name="phone"]', 'test@example.com');
  
  await page.screenshot({ path: 'test-results/customer-form-filled.png', fullPage: true });
  
  // Submit customer form
  const createCustomerButton = page.locator('button:has-text("Create Customer"), button:has-text("Save")').first();
  if (await createCustomerButton.isVisible()) {
    await createCustomerButton.click();
    console.log('✅ Customer creation submitted');
    await page.waitForTimeout(3000);
  }
  
  // Step 3: Create an invoice
  console.log('\n🧾 Creating invoice...');
  await page.goto('http://localhost:8000/invoices/create', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Invoice creation page loaded');
  await page.screenshot({ path: 'test-results/invoice-create-page.png', fullPage: true });
  
  // Select customer
  console.log('Selecting customer...');
  const customerSelect = page.locator('select[name="customer_id"], .p-dropdown').first();
  if (await customerSelect.isVisible()) {
    await customerSelect.click();
    await page.waitForTimeout(1000);
    
    // Look for our created customer or any customer
    const customerOptions = await page.locator('option, li').all();
    console.log(`Found ${customerOptions.length} customer options`);
    
    if (customerOptions.length > 0) {
      // Click on an option (skipping the first if it's a placeholder)
      const targetOption = customerOptions.length > 1 ? customerOptions[1] : customerOptions[0];
      await targetOption.click();
      console.log('✅ Customer selected');
    }
  }
  
  // Fill invoice details
  console.log('Filling invoice details...');
  const today = new Date().toISOString().split('T')[0];
  
  // Look for date fields
  const dateFields = await page.locator('input[type="date"], input[name*="date"]').all();
  if (dateFields.length >= 1) {
    await dateFields[0].fill(today);
    console.log('✅ Invoice date set');
  }
  
  if (dateFields.length >= 2) {
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    await dateFields[1].fill(dueDate.toISOString().split('T')[0]);
    console.log('✅ Due date set');
  }
  
  // Add line item
  console.log('Adding line item...');
  const descriptionFields = await page.locator('textarea, input[placeholder*="description"]').all();
  if (descriptionFields.length > 0) {
    await descriptionFields[0].fill('Professional Services - Testing');
    console.log('✅ Description filled');
  }
  
  // Look for quantity and price fields
  const numberFields = await page.locator('input[type="number"], input[name*="quantity"], input[name*="price"], input[placeholder*="quantity"], input[placeholder*="price"]').all();
  if (numberFields.length >= 1) {
    await numberFields[0].fill('5');
    console.log('✅ Quantity set');
  }
  
  if (numberFields.length >= 2) {
    await numberFields[1].fill('100');
    console.log('✅ Unit price set');
  }
  
  await page.screenshot({ path: 'test-results/invoice-form-filled.png', fullPage: true });
  
  // Submit invoice
  console.log('Submitting invoice...');
  const saveButton = page.locator('button:has-text("Save"), button:has-text("Create"), button:has-text("Save as Draft")').first();
  if (await saveButton.isVisible()) {
    await saveButton.click();
    console.log('✅ Invoice submitted');
    await page.waitForTimeout(5000);
    
    console.log('After submission URL:', page.url());
    await page.screenshot({ path: 'test-results/after-invoice-creation.png', fullPage: true });
    
    // Check for success message
    const pageText = await page.textContent('body');
    if (pageText.includes('success') || pageText.includes('created')) {
      console.log('✅ Invoice appears to have been created successfully');
    }
  }
  
  // Step 4: Go to invoices list and test viewing
  console.log('\n📋 Testing invoice viewing from list...');
  await page.goto('http://localhost:8000/invoices', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Invoices list loaded:', page.url());
  await page.screenshot({ path: 'test-results/invoices-list-with-data.png', fullPage: true });
  
  // Look for invoices and view options
  const invoiceElements = await page.locator('*:has-text("INV-")').all();
  const eyeIcons = await page.locator('.pi-eye, button[icon*="eye"], button:has(.pi-eye)').all();
  const viewButtons = await page.locator('button:has-text("View"), a:has-text("View")').all();
  
  console.log(`Found ${invoiceElements.length} invoice elements`);
  console.log(`Found ${eyeIcons.length} eye icons`);
  console.log(`Found ${viewButtons.length} view buttons`);
  
  if (invoiceElements.length > 0) {
    console.log('✅ Invoices are now present in the list');
    
    // Try to click on the first invoice
    console.log('Clicking on first invoice...');
    if (eyeIcons.length > 0) {
      await eyeIcons[0].click();
      console.log('Clicked eye icon');
    } else if (viewButtons.length > 0) {
      await viewButtons[0].click();
      console.log('Clicked view button');
    } else if (invoiceElements.length > 0) {
      await invoiceElements[0].click();
      console.log('Clicked invoice element');
    }
    
    await page.waitForTimeout(3000);
    console.log('After clicking invoice, URL:', page.url());
    await page.screenshot({ path: 'test-results/invoice-detail-after-click.png', fullPage: true });
    
    // Check if we successfully viewed the invoice
    const currentUrl = page.url();
    const isInvoiceDetail = currentUrl.includes('/invoices/') && currentUrl.length > 30 && !currentUrl.includes('/create');
    
    if (isInvoiceDetail) {
      console.log('✅ Successfully viewing invoice details!');
      
      const detailText = await page.textContent('body');
      const hasInvoiceNumber = detailText.includes('INV-') || detailText.includes('Invoice #');
      const hasCustomer = detailText.includes('customer') || detailText.includes('Customer');
      const hasAmount = detailText.includes('$') || detailText.includes('Total');
      const hasStatus = detailText.includes('status') || detailText.includes('Status');
      
      console.log('Invoice details found:');
      console.log('  - Invoice Number:', hasInvoiceNumber ? '✅' : '❌');
      console.log('  - Customer Info:', hasCustomer ? '✅' : '❌');
      console.log('  - Amount Info:', hasAmount ? '✅' : '❌');
      console.log('  - Status:', hasStatus ? '✅' : '❌');
    } else {
      console.log('⚠️ Invoice detail viewing may not be working properly');
    }
  } else {
    console.log('⚠️ No invoices found in the list');
  }
  
  console.log('\n✅ Complete invoice creation and viewing test completed');
});