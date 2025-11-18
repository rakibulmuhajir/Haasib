const { test, expect } = require('@playwright/test');

test('Direct Payment Navigation Test', async ({ page }) => {
  console.log('🔍 Testing direct payment navigation...');
  
  // Enable more detailed tracing
  await page.context().tracing.start({ screenshots: true, snapshots: true });
  
  // Step 1: Login with Khan
  console.log('🔐 Login with Khan user...');
  await page.goto('http://localhost:8000/login');
  await page.fill('input[name="username"]', 'Khan');
  await page.fill('input[name="password"]', 'yasirkhan');
  await page.click('button[type="submit"]');
  
  // Wait for navigation to complete
  await page.waitForURL(/dashboard|welcome/);
  console.log('✅ Login successful!');
  
  // Step 2: Try direct navigation to payments with a shorter timeout
  console.log('\n💳 Attempting to navigate to payments...');
  
  try {
    // Use Promise.race to avoid hanging
    const response = await Promise.race([
      page.goto('http://localhost:8000/payments', { waitUntil: 'domcontentloaded', timeout: 10000 }),
      new Promise((_, reject) => setTimeout(() => reject(new Error('Navigation timeout')), 10000))
    ]);
    
    console.log('Payments page responded with status:', response?.status());
    
    // Wait a bit for any initial content to load
    await page.waitForTimeout(2000);
    
    // Check current URL
    console.log('Current URL after navigation:', page.url());
    
    // Take a screenshot
    await page.screenshot({ path: 'test-results/direct-payments-navigation.png', fullPage: true });
    
    // Check page content
    const pageContent = await page.content();
    if (pageContent.includes('Payments') || pageContent.includes('payment')) {
      console.log('✅ Payments page appears to have loaded');
    } else {
      console.log('⚠️ Payments page may not have loaded as expected');
      console.log('Page content preview:', pageContent.substring(0, 500));
    }
    
  } catch (error) {
    console.log('❌ Error navigating to payments:', error.message);
    
    // Try to see what page we're on
    console.log('Current URL after error:', page.url());
    await page.screenshot({ path: 'test-results/payments-navigation-error.png', fullPage: true });
  }
  
  // Stop tracing
  await page.context().tracing.stop({ path: 'test-results/payments-trace.zip' });
  
  console.log('\n✅ Direct navigation test completed');
});