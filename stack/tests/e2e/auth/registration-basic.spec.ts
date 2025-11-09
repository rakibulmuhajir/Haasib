import { test, expect } from '@playwright/test';

test.describe('Basic Registration Page Tests', () => {
  test('should load registration page without errors', async ({ page }) => {
    console.log('🔍 Testing registration page loading...');
    
    // Go to registration page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    // Check if page loads correctly
    await expect(page.locator('h2:has-text("Create your Haasib account")')).toBeVisible();
    await expect(page.locator('label:has-text("Full Name")')).toBeVisible();
    await expect(page.locator('label:has-text("Company Name")')).toBeVisible();
    await expect(page.locator('label:has-text("Username")')).toBeVisible();
    await expect(page.locator('label:has-text("Email")')).toBeVisible();
    
    // Check if form is present
    await expect(page.locator('button:has-text("Create Account")')).toBeVisible();
    
    // Check if link to login page exists
    await expect(page.locator('a[href="/login"]:has-text("Sign in here")')).toBeVisible();
    
    console.log('✅ Registration page loads successfully!');
  });

  test('should navigate between login and register pages', async ({ page }) => {
    console.log('🔄 Testing navigation between auth pages...');
    
    // Go to login page
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h2:has-text("Sign in to Haasib")')).toBeVisible();
    
    // Click link to register
    await page.click('a[href="/register"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h2:has-text("Create your Haasib account")')).toBeVisible();
    
    // Click link to login
    await page.click('a[href="/login"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h2:has-text("Sign in to Haasib")')).toBeVisible();
    
    console.log('✅ Navigation between pages works correctly!');
  });

  test('should have proper form structure', async ({ page }) => {
    console.log('📝 Testing form structure...');
    
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    // Check for Personal Information section
    await expect(page.locator('text=Personal Information')).toBeVisible();
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('input[name="password_confirmation"]')).toBeVisible();
    
    // Check for Company Information section
    await expect(page.locator('text=Company Information')).toBeVisible();
    await expect(page.locator('input[name="company_name"]')).toBeVisible();
    await expect(page.locator('input[name="company_email"]')).toBeVisible();
    await expect(page.locator('input[name="company_phone"]')).toBeVisible();
    await expect(page.locator('input[name="company_website"]')).toBeVisible();
    
    // Check required field indicators
    await expect(page.locator('text=Company Name span:has-text("*")').first()).toBeVisible();
    
    console.log('✅ Form structure is correct!');
  });
});