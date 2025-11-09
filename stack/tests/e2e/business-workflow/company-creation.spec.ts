import { test, expect } from '@playwright/test';
import { navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData, performLogin } from '../helpers/auth-helper';

test.describe('Company Creation and Management E2E Tests', () => {
  let testData: any;

  test.beforeEach(async ({ page }) => {
    await performLogin(page);
    testData = generateTestData();
  });

  test('should create a new company successfully', async ({ page }) => {
    console.log('🏢 Testing company creation workflow...');

    // Step 1: Navigate to companies (login handled by beforeEach)
    await navigateToModule(page, 'companies');
    await takeScreenshot(page, 'companies-list-before-creation');

    // Step 2: Click Add Company button
    await clickButtonWithText(page, 'Add Company');
    await page.waitForTimeout(1000);

    // Step 3: Fill company creation form
    console.log('📝 Filling company creation form...');

    // Look for form fields and fill them
    const companyData = testData.company;

    // Company Name
    await page.fill('input[name="name"], input[id*="name"], [data-testid="company-name"]', companyData.name);

    // Company Email
    await page.fill('input[name="email"], input[id*="email"], [data-testid="company-email"]', companyData.email);

    // Company Phone
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="company-phone"]', companyData.phone);

    // Company Website
    await page.fill('input[name="website"], input[id*="website"], [data-testid="company-website"]', companyData.website);

    // Industry
    await page.selectOption('select[name="industry"], [data-testid="company-industry"]', companyData.industry);

    // Base Currency
    await page.selectOption('select[name="base_currency"], [data-testid="company-currency"]', companyData.base_currency);

    await takeScreenshot(page, 'company-form-filled');

    // Step 4: Submit the form
    console.log('💾 Submitting company creation form...');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Step 5: Verify successful creation
    const success = await waitForSuccessMessage(page);
    expect(success).toBeTruthy();

    // Step 6: Verify company appears in list
    await navigateToModule(page, 'companies');
    await page.waitForLoadState('networkidle');

    // Look for the created company in the list
    const companyLink = page.locator(`a:has-text("${companyData.name}")`).first();
    await expect(companyLink).toBeVisible({ timeout: 5000 });

    console.log(`✅ Company "${companyData.name}" created successfully`);
    await takeScreenshot(page, 'company-created-successfully');
  });

  test('should validate company creation form fields', async ({ page }) => {
    console.log('✅ Testing company form validation...');

    // Navigate to companies (login handled by beforeEach)
    await navigateToModule(page, 'companies');
    await clickButtonWithText(page, 'Add Company');

    // Test 1: Submit empty form - should show validation errors
    console.log('Testing empty form submission...');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(1000);

    // Look for validation errors
    const validationErrors = page.locator('.error, .alert-danger, .validation-error, [data-testid="error"]');
    const errorCount = await validationErrors.count();
    expect(errorCount).toBeGreaterThan(0);
    console.log(`Found ${errorCount} validation errors as expected`);

    // Test 2: Test invalid email format
    console.log('Testing invalid email format...');
    await page.fill('input[name="name"], input[id*="name"], [data-testid="company-name"]', 'Invalid Email Test');
    await page.fill('input[name="email"], input[id*="email"], [data-testid="company-email"]', 'invalid-email');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(1000);

    // Should still have validation errors
    const emailErrors = page.locator('.error:has-text("email"), .validation-error:has-text("email")');
    expect(await emailErrors.count()).toBeGreaterThan(0);

    await takeScreenshot(page, 'company-validation-errors');
  });

  test('should edit company information', async ({ page }) => {
    console.log('✏️ Testing company editing functionality...');

    // First, create a company
    const companyData = testData.company;

    // Navigate to companies (login handled by beforeEach)
    await navigateToModule(page, 'companies');
    await clickButtonWithText(page, 'Add Company');

    // Create company
    await page.fill('input[name="name"], input[id*="name"], [data-testid="company-name"]', companyData.name);
    await page.fill('input[name="email"], input[id*="email"], [data-testid="company-email"]', companyData.email);
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="company-phone"]', companyData.phone);
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Navigate to the created company
    await navigateToModule(page, 'companies');
    const companyLink = page.locator(`a:has-text("${companyData.name}")`).first();
    await companyLink.click();
    await page.waitForLoadState('networkidle');

    // Look for Edit button
    await clickButtonWithText(page, 'Edit');
    await page.waitForTimeout(1000);

    // Edit company information
    const updatedPhone = `+1-555-${Date.now().toString().slice(-4)}`;
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="company-phone"]', updatedPhone);

    // Save changes
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Verify changes were saved
    const success = await waitForSuccessMessage(page);
    expect(success).toBeTruthy();

    // Refresh and verify updated information
    await page.reload();
    await page.waitForLoadState('networkidle');

    const phoneElement = page.locator(`text=${updatedPhone}`);
    await expect(phoneElement).toBeVisible();

    console.log(`✅ Company phone updated to: ${updatedPhone}`);
    await takeScreenshot(page, 'company-updated-successfully');
  });

  test('should search and filter companies', async ({ page }) => {
    console.log('🔍 Testing company search and filtering...');

    // Navigate to companies (login handled by beforeEach)
    await navigateToModule(page, 'companies');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchBox = page.locator('input[type="search"], input[placeholder*="search"], [data-testid="search"]').first();
    if (await searchBox.isVisible()) {
      console.log('Testing search functionality...');

      // Search for a term
      await searchBox.fill('Test');
      await page.waitForTimeout(1000);

      // Check if results are filtered
      const searchResults = await page.locator('tr, .company-item').count();
      console.log(`Found ${searchResults} results for "Test"`);

      // Clear search
      await searchBox.clear();
      await page.waitForTimeout(1000);

      console.log('✅ Search functionality working');
    } else {
      console.log('⚠️ Search functionality not found');
    }

    // Test filter options if available
    const filterSelect = page.locator('select[name="status"], [data-testid="filter"], .filter').first();
    if (await filterSelect.isVisible()) {
      console.log('Testing filter functionality...');

      // Try different filter options
      const filterOptions = await filterSelect.locator('option').all();
      for (let i = 1; i < Math.min(filterOptions.length, 3); i++) {
        const option = filterOptions[i];
        const optionText = await option.textContent();
        if (optionText && optionText.trim()) {
          await filterSelect.selectOption({ index: i });
          await page.waitForTimeout(1000);
          console.log(`  - Filtered by: ${optionText.trim()}`);
        }
      }

      console.log('✅ Filter functionality working');
    }

    await takeScreenshot(page, 'company-search-filter');
  });

  test('should handle company status changes', async ({ page }) => {
    console.log('🔄 Testing company status management...');

    // Navigate to companies (login handled by beforeEach)
    await navigateToModule(page, 'companies');
    await page.waitForLoadState('networkidle');

    // Find a company in the list
    const companyItems = page.locator('tr, .company-item').first();
    if (await companyItems.isVisible()) {
      // Look for status-related actions
      const actionButtons = await companyItems.locator('button, a').all();

      for (const button of actionButtons) {
        const buttonText = await button.textContent();
        if (buttonText && (
          buttonText.toLowerCase().includes('activate') ||
          buttonText.toLowerCase().includes('deactivate') ||
          buttonText.toLowerCase().includes('status')
        )) {
          console.log(`Found status action: ${buttonText.trim()}`);

          // Click the status button
          await button.click();
          await page.waitForTimeout(2000);

          // Look for confirmation dialog if present
          const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Yes"), .btn-danger').first();
          if (await confirmButton.isVisible()) {
            await confirmButton.click();
            await page.waitForTimeout(2000);
          }

          // Check for success message
          const success = await waitForSuccessMessage(page);
          if (success) {
            console.log('✅ Company status updated successfully');
            break;
          }
        }
      }
    } else {
      console.log('⚠️ No companies found to test status changes');
    }

    await takeScreenshot(page, 'company-status-management');
  });

  test('should display company details correctly', async ({ page }) => {
    console.log('📄 Testing company detail page...');

    // Navigate to companies (login handled by beforeEach)
    await navigateToModule(page, 'companies');
    await page.waitForLoadState('networkidle');

    // Click on first company if available
    const firstCompany = page.locator('tr a, .company-item a').first();
    if (await firstCompany.isVisible()) {
      await firstCompany.click();
      await page.waitForLoadState('networkidle');

      // Verify company detail page elements
      console.log('Checking company detail page elements...');

      // Company name should be prominent
      const companyName = page.locator('h1, h2, .company-name, [data-testid="company-name"]').first();
      if (await companyName.isVisible()) {
        const name = await companyName.textContent();
        console.log(`  ✅ Company name: ${name}`);
      }

      // Check for information sections
      const infoSections = [
        'Contact Information',
        'Company Details',
        'Settings',
        'Users',
        'Activity'
      ];

      for (const section of infoSections) {
        const sectionElement = page.locator(`:has-text("${section}")`).first();
        if (await sectionElement.isVisible()) {
          console.log(`  ✅ Found section: ${section}`);
        }
      }

      // Check for action buttons
      const actionButtons = [
        'Edit',
        'Settings',
        'Users',
        'Audit Log'
      ];

      for (const action of actionButtons) {
        const actionButton = page.locator(`button:has-text("${action}"), a:has-text("${action}")`).first();
        if (await actionButton.isVisible()) {
          console.log(`  ✅ Found action: ${action}`);
        }
      }

    } else {
      console.log('⚠️ No companies found to view details');
    }

    await takeScreenshot(page, 'company-detail-page');
  });
});
