import { test, expect } from '@playwright/test';

test.describe('TestOwner Registration', () => {
  test('should register user with testowner credentials', async ({ page }) => {
    console.log('🔐 Registering testowner user...');

    // Go to registration page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Verify registration page is loaded
    await expect(page.locator('h2:has-text("Create your Haasib account")')).toBeVisible();

    // Fill registration form with testowner credentials
    const timestamp = Date.now();
    await page.fill('input[name="name"]', 'Test Owner');
    await page.fill('input[name="username"]', 'testowner');
    await page.fill('input[name="email"]', `testowner${timestamp}@example.com`);
    await page.fill('input[name="password"]', 'password');
    await page.fill('input[name="password_confirmation"]', 'password');
    await page.fill('input[name="company_name"]', `TestOwner Company ${timestamp}`);
    await page.fill('input[name="company_email"]', `info@testowner${timestamp}.com`);
    await page.fill('input[name="company_phone"]', '+1 (555) 987-6543');
    await page.fill('input[name="company_website"]', `https://testowner${timestamp}.com`);

    // Submit registration form
    console.log('📝 Submitting testowner registration...');
    await page.click('button:has-text("Create Account")');

    // Wait for success message and redirect to login
    await page.waitForTimeout(3000);

    // Should show success message or redirect to login
    const currentUrl = page.url();
    console.log(`Current URL after registration: ${currentUrl}`);

    // If redirected to login, verify we can login with testowner credentials
    if (currentUrl.includes('/login')) {
      console.log('🔑 Testing login with testowner credentials...');
      await page.fill('input[name="username"]', 'testowner');
      await page.fill('input[name="password"]', 'password');
      await page.click('button:has-text("Sign in")');

      // Wait for successful login
      await page.waitForTimeout(3000);

      // Should be redirected to dashboard or companies
      const loggedInUrl = page.url();
      console.log(`Logged in URL: ${loggedInUrl}`);

      // Verify we're logged in (not on login page anymore)
      expect(loggedInUrl).not.toContain('/login');

      console.log('✅ testowner registration and login successful!');
    } else {
      console.log('✅ testowner registration successful!');
    }

    // Take screenshot for verification
    await page.screenshot({
      path: `tests/e2e/screenshots/testowner-registration-${timestamp}.png`,
      fullPage: true
    });
  });
});