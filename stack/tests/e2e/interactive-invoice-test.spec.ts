import { test, expect, navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData } from './helpers/auth-helper';

test.describe('Interactive Invoice Management Tests', () => {
  let testData: any;

  test.beforeEach(async ({ page }) => {
    testData = generateTestData();
  });

  test('interactive invoice creation workflow', async ({ page }) => {
    console.log('🧾 Starting interactive invoice creation test...');

    // Step 1: Login
    await page.goto('/login');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    console.log('✅ Logged in successfully');

    // Step 2: Navigate to customers and examine the page
    console.log('🔍 Navigating to customers page...');
    await navigateToModule(page, 'customers');
    
    // Take screenshot to see what's on the page
    await takeScreenshot(page, 'customers-page-loaded');
    
    // Pause for interactive debugging
    console.log('🛑 Pausing for interactive debugging...');
    console.log('Check the browser to see the customers page');
    console.log('Press any key to continue...');
    
    // Enable headful mode for debugging
    await page.pause();
    
    // Look for any buttons that might create customers
    const allButtons = await page.locator('button, a').all();
    console.log(`Found ${allButtons.length} buttons/links on customers page:`);
    
    for (let i = 0; i < Math.min(allButtons.length, 15); i++) {
      const button = allButtons[i];
      const text = await button.textContent();
      if (text && text.trim() && (text.toLowerCase().includes('add') || text.toLowerCase().includes('create') || text.toLowerCase().includes('new'))) {
        console.log(`  - Button: "${text.trim()}"`);
      }
    }
    
    // Try to find customer creation routes
    console.log('Trying to navigate to customer creation page directly...');
    await page.goto('/customers/create');
    await page.waitForLoadState('networkidle');
    
    await takeScreenshot(page, 'customer-create-page');
    
    // Check if we have form fields
    const nameFields = await page.locator('input[name*="name"], input[id*="name"]').all();
    console.log(`Found ${nameFields.length} name fields:`);
    
    for (let i = 0; i < nameFields.length; i++) {
      const field = nameFields[i];
      const name = await field.getAttribute('name');
      const id = await field.getAttribute('id');
      const placeholder = await field.getAttribute('placeholder');
      console.log(`  - Field: name="${name}", id="${id}", placeholder="${placeholder}"`);
    }
    
    // If we have form fields, try to fill them
    if (nameFields.length > 0) {
      console.log('📝 Attempting to create customer...');
      
      const customerData = testData.customer;
      
      // Fill name field
      await page.fill('input[name*="name"], input[id*="name"]', customerData.name);
      console.log(`✅ Filled name: ${customerData.name}`);
      
      // Fill email field
      const emailField = page.locator('input[name*="email"], input[id*="email"]').first();
      if (await emailField.isVisible()) {
        await emailField.fill(customerData.email);
        console.log(`✅ Filled email: ${customerData.email}`);
      }
      
      // Fill phone field
      const phoneField = page.locator('input[name*="phone"], input[id*="phone"]').first();
      if (await phoneField.isVisible()) {
        await phoneField.fill(customerData.phone);
        console.log(`✅ Filled phone: ${customerData.phone}`);
      }
      
      await takeScreenshot(page, 'customer-form-filled');
      
      // Try to find submit button
      const submitButtons = await page.locator('button[type="submit"], button:has-text("Save"), button:has-text("Create"), button:has-text("Submit")').all();
      
      if (submitButtons.length > 0) {
        console.log(`Found ${submitButtons.length} potential submit buttons`);
        await submitButtons[0].click();
        console.log('✅ Clicked submit button');
        
        await page.waitForTimeout(3000);
        
        // Check if customer was created successfully
        const success = await waitForSuccessMessage(page);
        if (success) {
          console.log('✅ Customer created successfully!');
        } else {
          console.log('❌ Customer creation may have failed - no success message found');
        }
        
        await takeScreenshot(page, 'after-customer-creation');
      } else {
        console.log('❌ No submit button found');
      }
    } else {
      console.log('❌ No form fields found on customer creation page');
    }
    
    // Continue to invoices if customer creation worked
    console.log('🧾 Navigating to invoices page...');
    await navigateToModule(page, 'invoices');
    
    await takeScreenshot(page, 'invoices-page-loaded');
    
    // Look for invoice creation options
    const invoiceButtons = await page.locator('button, a').all();
    console.log(`Found ${invoiceButtons.length} buttons/links on invoices page:`);
    
    for (let i = 0; i < Math.min(invoiceButtons.length, 10); i++) {
      const button = invoiceButtons[i];
      const text = await button.textContent();
      if (text && text.trim() && (
        text.toLowerCase().includes('create') || 
        text.toLowerCase().includes('add') || 
        text.toLowerCase().includes('new') ||
        text.toLowerCase().includes('invoice')
      )) {
        console.log(`  - Button: "${text.trim()}"`);
      }
    }
    
    // Try to create invoice directly
    console.log('Trying to navigate to invoice creation page directly...');
    await page.goto('/invoices/create');
    await page.waitForLoadState('networkidle');
    
    await takeScreenshot(page, 'invoice-create-page');
    
    console.log('✅ Interactive test completed - check screenshots for debugging');
  });
});