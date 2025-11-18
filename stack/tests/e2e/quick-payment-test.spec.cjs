const { test, expect } = require('@playwright/test');

test('Quick Payment Navigation Test', async ({ page }) => {
  console.log('🔍 Quick payment navigation test...');
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  
  // Wait for login to complete
  await page.waitForTimeout(5000);
  console.log('✅ Login completed');
  
  // Step 2: Try to navigate to payments 
  console.log('\n💳 Navigating to payments...');
  
  try {
    await page.goto('http://localhost:8000/payments', { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForTimeout(3000);
    
    console.log('Current URL:', page.url());
    await page.screenshot({ path: 'test-results/quick-payment-test.png', fullPage: true });
    
    // Check if we see any payment-related content
    const pageText = await page.textContent('body');
    if (pageText && pageText.toLowerCase().includes('payment')) {
      console.log('✅ Payment-related content found!');
    } else {
      console.log('⚠️ No payment content found');
      console.log('Page title:', await page.title());
    }
    
  } catch (error) {
    console.log('❌ Error:', error.message);
    console.log('Current URL after error:', page.url());
  }
  
  console.log('\n✅ Quick test completed');
});