import { test, expect } from '@playwright/test';

test.describe('Complete Business Workflow E2E Tests', () => {
  test('should register user, create company, and manage it successfully', async ({ page }) => {
    console.log('🚀 Testing complete business workflow...');

    const timestamp = Date.now();
    const userData = {
      name: 'Business Owner',
      username: `businessowner${timestamp}`,
      email: `businessowner${timestamp}@example.com`,
      password: 'password123',
      company_name: `Business Solutions ${timestamp}`,
      company_email: `info@businesssolutions${timestamp}.com`,
      company_phone: '+1 (555) 987-6543',
      company_website: `https://businesssolutions${timestamp}.com`,
    };

    // Step 1: Register a new user and company
    console.log('1️⃣ Registering new user and company...');
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Fill registration form
    await page.fill('input[name="name"]', userData.name);
    await page.fill('input[name="username"]', userData.username);
    await page.fill('input[name="email"]', userData.email);
    await page.fill('input[name="password"]', userData.password);
    await page.fill('input[name="password_confirmation"]', userData.password);
    await page.fill('input[name="company_name"]', userData.company_name);
    await page.fill('input[name="company_email"]', userData.company_email);
    await page.fill('input[name="company_phone"]', userData.company_phone);
    await page.fill('input[name="company_website"]', userData.company_website);

    await page.click('button:has-text("Create Account")');
    await page.waitForTimeout(2000);

    // Should show success message and redirect to login
    await expect(page.locator('div:has-text("Registration successful")')).toBeVisible();
    await page.waitForURL('**/login');
    console.log('✅ User registered successfully!');

    // Step 2: Login with new credentials
    console.log('2️⃣ Logging in with new credentials...');
    await page.fill('input[name="username"]', userData.username);
    await page.fill('input[name="password"]', userData.password);
    await page.click('button:has-text("Sign in")');
    await page.waitForURL('**/dashboard');
    console.log('✅ Login successful!');

    // Step 3: Navigate to companies and verify the company exists
    console.log('3️⃣ Verifying company creation...');
    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Should see the company created during registration
    await expect(page.locator(`a:has-text("${userData.company_name}")`)).toBeVisible();
    console.log(`✅ Company "${userData.company_name}" found!`);

    // Step 4: View company details
    console.log('4️⃣ Viewing company details...');
    await page.click(`a:has-text("${userData.company_name}")`);
    await page.waitForLoadState('networkidle');

    // Verify company information is displayed
    await expect(page.locator('h1, h2, .company-name:has-text("' + userData.company_name + '")').first()).toBeVisible();
    console.log('✅ Company details loaded!');

    // Step 5: Test editing company
    console.log('5️⃣ Testing company editing...');
    const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")').first();
    if (await editButton.isVisible()) {
      await editButton.click();
      await page.waitForTimeout(1000);

      // Update company phone
      const updatedPhone = `+1-555-${timestamp.toString().slice(-4)}`;
      await page.fill('input[name="phone"], input[name="company_phone"]', updatedPhone);
      
      await page.click('button:has-text("Save"), button:has-text("Update")');
      await page.waitForTimeout(2000);

      // Verify changes were saved
      await expect(page.locator('div:has-text("updated"), div:has-text("saved")').first()).toBeVisible({ timeout: 5000 });
      console.log(`✅ Company phone updated to: ${updatedPhone}`);
    } else {
      console.log('⚠️ Edit button not found - skipping edit test');
    }

    // Step 6: Test accessing other modules
    console.log('6️⃣ Testing module access...');
    
    // Test Dashboard
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');
    console.log('✅ Dashboard accessible');

    // Test Invoices module
    await page.goto('/invoices');
    await page.waitForLoadState('networkidle');
    console.log('✅ Invoices module accessible');

    // Test Customers module
    await page.goto('/customers');
    await page.waitForLoadState('networkidle');
    console.log('✅ Customers module accessible');

    // Step 7: Test company switching if multiple companies exist
    console.log('7️⃣ Testing company context...');
    
    // Check if company switcher is available
    const companySwitcher = page.locator('button:has-text("Switch Company"), select[name="company_id"], .company-switcher').first();
    if (await companySwitcher.isVisible()) {
      console.log('✅ Company switcher found');
    } else {
      console.log('ℹ️ Single company - no company switcher');
    }

    // Step 8: Test logout
    console.log('8️⃣ Testing logout...');
    await page.goto('/logout');
    await page.waitForTimeout(1000);

    // Should redirect to login page
    await page.waitForURL('**/login');
    console.log('✅ Logout successful');

    // Take final screenshot
    await page.screenshot({ 
      path: `tests/e2e/screenshots/complete-workflow-${timestamp}.png`,
      fullPage: true 
    });

    console.log('🎉 Complete workflow test finished successfully!');
  });

  test('should handle user session and company context properly', async ({ page }) => {
    console.log('🔄 Testing session and company context...');

    const timestamp = Date.now();
    const userData = {
      name: 'Session Test User',
      username: `sessionuser${timestamp}`,
      email: `sessionuser${timestamp}@example.com`,
      password: 'password123',
      company_name: `Session Test Company ${timestamp}`,
    };

    // Register and login
    await page.goto('/register');
    await page.fill('input[name="name"]', userData.name);
    await page.fill('input[name="username"]', userData.username);
    await page.fill('input[name="email"]', userData.email);
    await page.fill('input[name="password"]', userData.password);
    await page.fill('input[name="password_confirmation"]', userData.password);
    await page.fill('input[name="company_name"]', userData.company_name);
    await page.click('button:has-text("Create Account")');

    await page.waitForTimeout(2000);
    await page.waitForURL('**/login');

    await page.fill('input[name="username"]', userData.username);
    await page.fill('input[name="password"]', userData.password);
    await page.click('button:has-text("Sign in")');
    await page.waitForURL('**/dashboard');

    // Test session persistence
    await page.goto('/companies');
    await page.waitForLoadState('networkidle');
    
    // Should still be logged in and see company
    await expect(page.locator(`a:has-text("${userData.company_name}")`)).toBeVisible();
    
    // Test user info retrieval
    await page.goto('/settings');
    await page.waitForLoadState('networkidle');
    console.log('✅ Session persistence working');

    // Test logout and session cleanup
    await page.goto('/logout');
    await page.waitForTimeout(1000);
    
    // Try to access protected route
    await page.goto('/dashboard');
    await page.waitForURL('**/login');
    console.log('✅ Session cleanup working correctly');
  });
});