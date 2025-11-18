const { test, expect } = require('@playwright/test');

test('Simple Manual Customer and Invoice Test', async ({ page }) => {
  console.log('🚀 Simple manual test for customer and invoice creation...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('Login URL:', page.url());
  
  if (page.url().includes('/login')) {
    console.log('❌ Login failed, trying admin user...');
    await page.goto('http://localhost:8000/login');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
  }
  
  console.log('After login URL:', page.url());
  
  // Step 2: Create Customer with minimal required fields
  console.log('\n👤 Creating customer...');
  await page.goto('http://localhost:8000/customers/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  // Fill the absolute minimum required fields
  try {
    // Customer name (required)
    await page.fill('input[placeholder="Enter customer name"]', 'Simple Test Customer');
    console.log('✅ Customer name filled');
    
    // Wait and then click customer type dropdown
    await page.waitForTimeout(1000);
    
    // Try different approaches for customer type
    const dropdowns = await page.locator('.p-dropdown').all();
    if (dropdowns.length > 0) {
      await dropdowns[0].click();
      await page.waitForTimeout(1000);
      
      // Look for "Individual" option
      const individualOption = page.locator('li:has-text("Individual")').first();
      if (await individualOption.isVisible()) {
        await individualOption.click();
        console.log('✅ Customer type selected: Individual');
      } else {
        // Try pressing Escape to close dropdown if Individual not found
        await page.keyboard.press('Escape');
        console.log('⚠️ Individual option not found, continuing...');
      }
    }
    
    // Contact email (optional but good to have)
    await page.fill('input[placeholder="Email or phone number"]', 'test@example.com');
    console.log('✅ Contact filled');
    
    // Take screenshot before submission
    await page.screenshot({ path: 'test-results/simple-customer-form.png', fullPage: true });
    
    // Look for and click submit button
    const submitButtons = await page.locator('button:has-text("Create"), button:has-text("Save"), button[type="submit"]').all();
    if (submitButtons.length > 0) {
      await submitButtons[0].click();
      console.log('✅ Submit button clicked');
      
      // Wait for result
      await page.waitForTimeout(3000);
      
      const afterSubmitUrl = page.url();
      console.log('After customer creation URL:', afterSubmitUrl);
      
      // Take screenshot after submission
      await page.screenshot({ path: 'test-results/after-customer-submit.png', fullPage: true });
      
      // Check for any success or error messages
      const messages = await page.locator('.p-toast-message, .alert, .message, .text-red-500, .text-green-500').all();
      for (const message of messages) {
        const text = await message.textContent();
        if (text && text.trim()) {
          console.log('Message:', text.trim());
        }
      }
      
    } else {
      console.log('❌ No submit button found');
    }
    
  } catch (error) {
    console.log('⚠️ Error during customer creation:', error.message);
  }
  
  // Step 3: Simple invoice creation (if customer creation worked)
  console.log('\n🧾 Attempting invoice creation...');
  await page.goto('http://localhost:8000/invoices/create');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  // Take screenshot of invoice form
  await page.screenshot({ path: 'test-results/invoice-form.png', fullPage: true });
  
  // Just show the invoice form without auto-filling (let user see the structure)
  console.log('Invoice creation page loaded:', page.url());
  
  // Count form elements
  const inputs = await page.locator('input').all();
  const textareas = await page.locator('textarea').all();
  const selects = await page.locator('select, .p-dropdown').all();
  
  console.log(`Invoice form has ${inputs.length} inputs, ${textareas.length} textareas, ${selects.length} dropdowns`);
  
  // Show first few input placeholders
  for (let i = 0; i < Math.min(inputs.length, 5); i++) {
    const placeholder = await inputs[i].getAttribute('placeholder');
    const name = await inputs[i].getAttribute('name');
    console.log(`Input ${i+1}: placeholder="${placeholder}", name="${name}"`);
  }
  
  console.log('\n📋 Test Summary:');
  console.log('- Login: Attempted with Khan/admin');
  console.log('- Customer creation: Attempted');
  console.log('- Invoice creation: Form loaded');
  console.log('\n🔍 Check test-results/ directory for screenshots');
  console.log('📊 Check Laravel logs for any validation errors');
  
  // Final pause to let user see the state
  await page.pause();
});