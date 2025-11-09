import { test, expect } from '@playwright/test';

test.describe('Diagnostic Test - Show Browser', () => {
  test('should show browser window and diagnose login page', async ({ page }) => {
    console.log('🔍 Starting diagnostic test...');

    // This test is designed to show the browser window and help debug

    // Step 1: Go to the base URL with a long timeout
    console.log('📱 Navigating to application...');
    await page.goto('http://localhost:8001', { timeout: 30000 });

    // Take a screenshot immediately to see what we get
    await page.screenshot({ path: 'test-results/diagnostic-1-initial-load.png', fullPage: true });
    console.log('✅ Screenshot 1 taken - Initial page load');

    // Step 2: Check page title and content
    const title = await page.title();
    console.log(`📄 Page title: "${title}"`);

    // Get page content
    const bodyText = await page.locator('body').textContent();
    console.log(`📝 Page content length: ${bodyText?.length || 0} characters`);

    if (bodyText) {
      console.log(`First 200 characters: ${bodyText.substring(0, 200)}...`);
    }

    // Step 3: Look for any form elements
    console.log('🔍 Looking for form elements...');

    const allInputs = await page.locator('input').all();
    console.log(`  Found ${allInputs.length} input elements`);

    for (let i = 0; i < Math.min(allInputs.length, 5); i++) {
      const input = allInputs[i];
      const type = await input.getAttribute('type');
      const name = await input.getAttribute('name');
      const placeholder = await input.getAttribute('placeholder');
      console.log(`    Input ${i + 1}: type="${type}" name="${name}" placeholder="${placeholder}"`);
    }

    // Step 4: Look specifically for login elements
    console.log('🔐 Looking for login elements...');

    const emailInputs = await page.locator('input[type="email"], input[name*="email"], input[placeholder*="email"]').all();
    const passwordInputs = await page.locator('input[type="password"], input[name*="password"]').all();
    const submitButtons = await page.locator('button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Sign In")').all();

    console.log(`  Email inputs: ${emailInputs.length}`);
    console.log(`  Password inputs: ${passwordInputs.length}`);
    console.log(`  Submit buttons: ${submitButtons.length}`);

    // Step 5: Try to find login link or button
    const loginLinks = await page.locator('a[href*="login"], a:has-text("Login"), a:has-text("Sign In"), button:has-text("Login")').all();
    console.log(`  Login links/buttons: ${loginLinks.length}`);

    // Step 6: Check for error messages
    const errorElements = await page.locator('.error, .alert-danger, .exception, .message').all();
    if (errorElements.length > 0) {
      console.log('⚠️ Found error elements:');
      for (let i = 0; i < errorElements.length; i++) {
        const errorText = await errorElements[i].textContent();
        console.log(`  Error ${i + 1}: ${errorText}`);
      }
    }

    // Step 7: Take another screenshot
    await page.screenshot({ path: 'test-results/diagnostic-2-after-analysis.png', fullPage: true });
    console.log('✅ Screenshot 2 taken - After analysis');

    // Step 8: Wait for user to see the browser window
    console.log('⏸️ Waiting 10 seconds for user to observe the browser window...');
    await page.waitForTimeout(10000);

    // Step 9: Final screenshot
    await page.screenshot({ path: 'test-results/diagnostic-3-final.png', fullPage: true });
    console.log('✅ Screenshot 3 taken - Final state');

    console.log('🎉 Diagnostic test completed!');
    console.log('📁 Screenshots saved in test-results/diagnostic-*.png');
  });

  test('should try direct login navigation', async ({ page }) => {
    console.log('🔍 Testing direct login navigation...');

    // Try going directly to login
    await page.goto('http://localhost:8001/login', { timeout: 30000 });
    await page.waitForTimeout(3000); // Wait for page to load

    await page.screenshot({ path: 'test-results/diagnostic-login-page.png', fullPage: true });
    console.log('✅ Login page screenshot taken');

    // Check if we have login form
    const emailInput = page.locator('input[type="email"], input[name*="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name*="password"]').first();

    if (await emailInput.isVisible()) {
      console.log('✅ Found email input field');
    } else {
      console.log('❌ No email input field found');
    }

    if (await passwordInput.isVisible()) {
      console.log('✅ Found password input field');
    } else {
      console.log('❌ No password input field found');
    }

    // Wait 5 seconds before ending
    await page.waitForTimeout(5000);
  });
});
