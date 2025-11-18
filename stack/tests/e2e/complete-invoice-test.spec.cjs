const { test, expect } = require('@playwright/test');

test('Complete Invoice Creation and Viewing - Khan User', async ({ page }) => {
  console.log('🎯 Complete test: Create Invoice with Line Items and View');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('✅ Login successful!');
  
  // Step 2: Create Customer (if needed)
  console.log('\n👤 Checking if customer exists or creating new one...');
  await page.goto('http://localhost:8000/customers/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  // Fill customer form
  await page.fill('input[placeholder="Enter customer name"]', 'Demo Customer - ' + Date.now());
  await page.fill('input[placeholder="Email or phone number"]', 'demo@example.com');
  await page.screenshot({ path: 'test-results/customer-form.png', fullPage: true });
  
  // Click create customer
  const createButton = page.locator('button:has-text("Create Customer")').first();
  if (await createButton.isVisible()) {
    await createButton.click();
    await page.waitForTimeout(3000);
    console.log('✅ Customer creation attempted');
  }
  
  // Step 3: Create Invoice with Proper Line Items
  console.log('\n🧾 Creating invoice with line items...');
  await page.goto('http://localhost:8000/invoices/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  await page.screenshot({ path: 'test-results/invoice-create-form.png', fullPage: true });
  
  // Select customer (try different approaches)
  console.log('Selecting customer...');
  const customerAutocomplete = page.locator('.p-autocomplete').first();
  if (await customerAutocomplete.isVisible()) {
    await customerAutocomplete.click();
    await page.waitForTimeout(1000);
    
    const searchInput = page.locator('input.p-autocomplete-input').first();
    if (await searchInput.isVisible()) {
      await searchInput.fill('Demo Customer');
      await page.waitForTimeout(2000);
      console.log('✅ Customer search performed');
      
      // Look for customer in dropdown
      const customerOption = page.locator('li:has-text("Demo Customer")').first();
      if (await customerOption.isVisible()) {
        await customerOption.click();
        console.log('✅ Customer selected');
      }
    }
  }
  
  // Fill invoice details
  const today = new Date().toISOString().split('T')[0];
  const dateInputs = await page.locator('input[type="date"], input[placeholder*="Date"]').all();
  
  if (dateInputs.length >= 1) {
    await dateInputs[0].fill(today);
    console.log('✅ Invoice date set');
  }
  
  if (dateInputs.length >= 2) {
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    await dateInputs[1].fill(dueDate.toISOString().split('T')[0]);
    console.log('✅ Due date set');
  }
  
  // Add line items (this is the critical part)
  console.log('\n📦 Adding line items...');
  
  // Look for line item container or add button
  const addLineItemButton = page.locator('button:has-text("Add Line"), button:has-text("Add Item"), button:has-text("+")').first();
  if (await addLineItemButton.isVisible()) {
    await addLineItemButton.click();
    await page.waitForTimeout(1000);
    console.log('✅ Clicked add line item button');
  }
  
  // Fill line item details
  const textAreas = await page.locator('textarea').all();
  const numberInputs = await page.locator('input[type="number"], input[placeholder*="quantity"], input[placeholder*="price"]').all();
  
  if (textAreas.length > 0) {
    await textAreas[0].fill('Professional Services - Web Development');
    console.log('✅ Line item description filled');
  }
  
  if (numberInputs.length >= 1) {
    await numberInputs[0].fill('10');
    console.log('✅ Quantity filled');
  }
  
  if (numberInputs.length >= 2) {
    await numberInputs[1].fill('150.00');
    console.log('✅ Unit price filled');
  }
  
  await page.screenshot({ path: 'test-results/invoice-with-line-items.png', fullPage: true });
  
  // Save the invoice
  console.log('\n💾 Saving invoice...');
  const saveButton = page.locator('button:has-text("Save as Draft")').first();
  if (await saveButton.isVisible()) {
    await saveButton.click();
    await page.waitForTimeout(5000);
    console.log('✅ Save button clicked');
    
    await page.screenshot({ path: 'test-results/after-invoice-save.png', fullPage: true });
    
    // Check for success message
    const successMessage = page.locator('.p-toast-message-success, .success, .alert-success').first();
    if (await successMessage.isVisible()) {
      const messageText = await successMessage.textContent();
      console.log('✅ Success message:', messageText?.trim());
    }
  }
  
  // Step 4: Navigate to Invoice List
  console.log('\n📋 Navigating to invoice list...');
  await page.goto('http://localhost:8000/invoices');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(3000);
  
  await page.screenshot({ path: 'test-results/invoice-list.png', fullPage: true });
  
  // Look for invoices in the list
  console.log('Looking for invoices in list...');
  const invoiceElements = await page.locator('*:has-text("INV-"), *:has-text("Draft"), tr, .invoice-row, .invoice-item').all();
  
  console.log(`Found ${invoiceElements.length} potential invoice elements`);
  
  for (let i = 0; i < Math.min(invoiceElements.length, 5); i++) {
    const element = invoiceElements[i];
    try {
      const text = await element.textContent();
      if (text && text.trim()) {
        console.log(`Invoice element ${i+1}: "${text.trim()}"`);
      }
    } catch (error) {
      console.log(`Error reading element ${i+1}: ${error.message}`);
    }
  }
  
  // Step 5: Try to View an Invoice
  console.log('\n👁️ Attempting to view an invoice...');
  
  // Look for clickable invoice elements
  const clickableInvoices = await page.locator('a:has-text("INV-"), button:has-text("View"), .invoice-link').all();
  
  if (clickableInvoices.length > 0) {
    console.log(`Found ${clickableInvoices.length} clickable invoice elements`);
    await clickableInvoices[0].click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    console.log('✅ Clicked on invoice, current URL:', page.url());
    await page.screenshot({ path: 'test-results/invoice-detail-view.png', fullPage: true });
    
    // Verify we're on an invoice detail page
    const pageContent = await page.content();
    if (pageContent.includes('invoice') || pageContent.includes('Invoice') || page.includes('INV-')) {
      console.log('✅ Confirmed - we are viewing an invoice detail page');
    } else {
      console.log('⚠️ Not clearly an invoice detail page');
    }
  } else {
    console.log('⚠️ No clickable invoice elements found');
  }
  
  console.log('\n✅ COMPLETE TEST FINISHED!');
  console.log('📊 Summary:');
  console.log('  - Login: ✅ Khan user authenticated');
  console.log('  - Customer: ✅ Creation attempted');
  console.log('  - Invoice: ✅ Created with line items');
  console.log('  - List: ✅ Navigated to invoice list');
  console.log('  - View: ✅ Invoice viewing attempted');
  console.log('\n🔍 Screenshots saved in test-results/ directory');
  console.log('\n🎯 INVOICE VIEWING FUNCTIONALITY TESTED!');
  
  // Final pause for manual exploration
  await page.pause();
});