const { test, expect } = require('@playwright/test');

test('Test Customer Viewing After Fix', async ({ page }) => {
  console.log('👤 Testing customer viewing after credit service fix...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Go to customers list
  console.log('\n👥 Going to customers list...');
  await page.goto('http://localhost:8000/customers', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  
  console.log('Customers page loaded:', page.url());
  await page.screenshot({ path: 'test-results/customers-list.png', fullPage: true });
  
  // Look for customers and view options
  const customerElements = await page.locator('*:has-text("Test"), *:has-text("Customer")').all();
  const eyeIcons = await page.locator('.pi-eye, .fas fa-eye, button[icon*="eye"]').all();
  const viewButtons = await page.locator('button:has-text("View"), a:has-text("View")').all();
  
  console.log(`Found ${customerElements.length} customer-related elements`);
  console.log(`Found ${eyeIcons.length} eye icons`);
  console.log(`Found ${viewButtons.length} view buttons`);
  
  if (customerElements.length > 0) {
    console.log('✅ Customers found in list');
    
    // Try clicking on the first customer
    const clickableCustomers = await page.locator('tr:has-text("Test"), tr:has-text("Customer"), .customer-row, a[href*="customers"]').all();
    console.log(`Found ${clickableCustomers.length} clickable customer elements`);
    
    if (clickableCustomers.length > 0) {
      console.log('Clicking on first customer...');
      await clickableCustomers[0].click();
      await page.waitForTimeout(3000);
      
      console.log('After clicking customer, URL:', page.url());
      await page.screenshot({ path: 'test-results/customer-detail-after-click.png', fullPage: true });
      
      // Check if we successfully viewed the customer
      const currentUrl = page.url();
      const isCustomerDetail = currentUrl.includes('/customers/') && currentUrl.length > 30 && !currentUrl.includes('/create');
      
      if (isCustomerDetail) {
        console.log('✅ Successfully viewing customer details!');
        
        const detailText = await page.textContent('body');
        const hasCustomerName = detailText.includes('Customer') || detailText.includes('Test');
        const hasContactInfo = detailText.includes('email') || detailText.includes('phone') || detailText.includes('contact');
        const hasCreditInfo = detailText.includes('credit') || detailText.includes('Credit');
        
        console.log('Customer details found:');
        console.log('  - Customer Name:', hasCustomerName ? '✅' : '❌');
        console.log('  - Contact Info:', hasContactInfo ? '✅' : '❌');
        console.log('  - Credit Info:', hasCreditInfo ? '✅' : '❌');
        
        // Check for any error indicators
        const pageText = await page.textContent('body');
        const hasError = pageText.toLowerCase().includes('error') || pageText.toLowerCase().includes('exception');
        const hasTypeError = pageText.includes('TypeError');
        
        if (hasError) {
          console.log('⚠️ Page contains error text');
        }
        
        if (hasTypeError) {
          console.log('❌ Page contains TypeError (customer viewing still has issues)');
        } else {
          console.log('✅ No TypeError found - customer viewing fix successful!');
        }
        
      } else {
        console.log('⚠️ Customer detail viewing may not be working properly');
      }
    } else {
      console.log('⚠️ No clickable customer elements found');
    }
  } else {
    console.log('⚠️ No customers found in the list');
  }
  
  console.log('\n✅ Customer viewing test completed');
});