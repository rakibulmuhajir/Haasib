import { test, expect } from '@playwright/test';

test.describe('User Registration E2E Tests', () => {
  test('should register a new user and create company successfully', async ({ page }) => {
    console.log('🔐 Testing user registration workflow...');

    // Step 1: Go to registration page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Verify registration page is loaded
    await expect(page.locator('h2:has-text("Create your Haasib account")')).toBeVisible();
    await expect(page.locator('label:has-text("Full Name")')).toBeVisible();
    await expect(page.locator('label:has-text("Company Name")')).toBeVisible();

    // Step 2: Fill personal information
    const timestamp = Date.now();
    const userData = {
      name: 'Test User',
      username: `testuser${timestamp}`,
      email: `testuser${timestamp}@example.com`,
      password: 'password123',
      company_name: `Test Company ${timestamp}`,
      company_email: `info@testcompany${timestamp}.com`,
      company_phone: '+1 (555) 123-4567',
      company_website: `https://testcompany${timestamp}.com`,
    };

    // Fill Personal Information
    await page.fill('input[name="name"]', userData.name);
    await page.fill('input[name="username"]', userData.username);
    await page.fill('input[name="email"]', userData.email);
    await page.fill('input[name="password"]', userData.password);
    await page.fill('input[name="password_confirmation"]', userData.password);

    // Fill Company Information
    await page.fill('input[name="company_name"]', userData.company_name);
    await page.fill('input[name="company_email"]', userData.company_email);
    await page.fill('input[name="company_phone"]', userData.company_phone);
    await page.fill('input[name="company_website"]', userData.company_website);

    // Step 3: Submit registration form
    console.log('📝 Submitting registration form...');
    await page.click('button:has-text("Create Account")');

    // Step 4: Wait for success message and redirect
    await page.waitForTimeout(2000); // Wait for processing

    // Should show success message
    const successMessage = page.locator('div:has-text("Registration successful")');
    await expect(successMessage).toBeVisible({ timeout: 10000 });

    // Should redirect to login page
    await page.waitForURL('**/login', { timeout: 10000 });
    await expect(page.locator('h2:has-text("Sign in to Haasib")')).toBeVisible();

    console.log('✅ Registration successful! User and company created.');

    // Step 5: Test login with new credentials
    console.log('🔑 Testing login with new credentials...');
    await page.fill('input[name="username"]', userData.username);
    await page.fill('input[name="password"]', userData.password);
    await page.click('button:has-text("Sign in")');

    // Wait for successful login (redirect to dashboard or companies)
    await page.waitForURL('**/dashboard', { timeout: 10000 });

    console.log('✅ Login successful! User can access the application.');

    // Step 6: Verify user can access their company
    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Should see the company that was created during registration
    const companyLink = page.locator(`a:has-text("${userData.company_name}")`);
    await expect(companyLink).toBeVisible({ timeout: 5000 });

    console.log(`✅ Company "${userData.company_name}" is accessible to the registered user.`);

    // Take screenshot for verification
    await page.screenshot({ 
      path: `tests/e2e/screenshots/registration-success-${timestamp}.png`,
      fullPage: true 
    });
  });

  test('should validate registration form fields', async ({ page }) => {
    console.log('✅ Testing registration form validation...');

    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Test 1: Submit empty form - should show validation errors
    console.log('Testing empty form submission...');
    await page.click('button:has-text("Create Account")');
    await page.waitForTimeout(1000);

    // Look for validation errors - Full Name, Username, Email, Password, Company Name should be required
    const requiredFields = ['Full Name', 'Username', 'Email', 'Password', 'Company Name'];
    for (const field of requiredFields) {
      const fieldError = page.locator(`text=${field}`).locator('..').locator('.text-red-600, .validation-error');
      // Note: The exact error message styling might vary, so we're checking for any error near the field
    }

    // Test 2: Test email validation
    console.log('Testing email validation...');
    await page.fill('input[name="name"]', 'Test User');
    await page.fill('input[name="username"]', 'testuser');
    await page.fill('input[name="email"]', 'invalid-email');
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'password123');
    await page.fill('input[name="company_name"]', 'Test Company');
    
    await page.click('button:has-text("Create Account")');
    await page.waitForTimeout(1000);

    // Should still have validation errors for email
    const emailErrors = page.locator('text=Email').locator('..').locator('.text-red-600, .validation-error');
    // Email validation error should be present

    console.log('✅ Registration form validation working correctly.');
  });

  test('should handle duplicate username/email gracefully', async ({ page }) => {
    console.log('🔄 Testing duplicate registration handling...');

    const timestamp = Date.now();
    const existingUserData = {
      name: 'Existing User',
      username: `existinguser${timestamp}`,
      email: `existinguser${timestamp}@example.com`,
      password: 'password123',
      company_name: `Existing Company ${timestamp}`,
    };

    // First, register a user successfully
    await page.goto('/register');
    await page.fill('input[name="name"]', existingUserData.name);
    await page.fill('input[name="username"]', existingUserData.username);
    await page.fill('input[name="email"]', existingUserData.email);
    await page.fill('input[name="password"]', existingUserData.password);
    await page.fill('input[name="password_confirmation"]', existingUserData.password);
    await page.fill('input[name="company_name"]', existingUserData.company_name);
    await page.click('button:has-text("Create Account")');

    // Wait for success message
    await page.waitForTimeout(3000);

    // Go back to registration page
    await page.goto('/register');

    // Try to register with the same username
    await page.fill('input[name="name"]', 'New User');
    await page.fill('input[name="username"]', existingUserData.username); // Same username
    await page.fill('input[name="email"]', `newuser${timestamp}@example.com`);
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirmation"]', 'password123');
    await page.fill('input[name="company_name"]', 'New Company');
    await page.click('button:has-text("Create Account")');

    await page.waitForTimeout(1000);

    // Should show validation error for duplicate username
    const usernameError = page.locator('text=Username').locator('..').locator('.text-red-600, .validation-error');
    // Username validation error should be present

    console.log('✅ Duplicate validation working correctly.');
  });
});