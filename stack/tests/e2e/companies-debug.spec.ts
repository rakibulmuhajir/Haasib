import { test, expect } from '@playwright/test';

test.describe('Companies Functionality Debug', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
  });

  test('Test companies page navigation and basic functionality', async ({ page }) => {
    console.log('🏢 Testing companies page functionality...');

    // Navigate to companies page
    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Check if page loads successfully
    await expect(page).toHaveURL(/.*companies.*/);

    // Check for page title or main heading
    const pageTitle = await page.locator('h1, h2, .page-title, [data-testid="page-title"]').first();
    await expect(pageTitle).toBeVisible({ timeout: 5000 });

    // Look for companies list or table
    const companiesList = await page.locator('table, .companies-list, [data-testid="companies-list"]').first();
    if (await companiesList.isVisible()) {
      console.log('✅ Companies list found');

      // Count companies if present
      const companyRows = await companiesList.locator('tr, .company-item').count();
      console.log(`📊 Found ${companyRows} companies in list`);
    } else {
      console.log('⚠️ No companies list found - checking for empty state');
      const emptyState = await page.locator('.empty-state, .no-data, [data-testid="empty-state"]').first();
      if (await emptyState.isVisible()) {
        console.log('✅ Empty state message found');
      }
    }

    // Look for Add Company button
    const addButtons = await page.locator('button:has-text("Add"), button:has-text("Create"), button:has-text("New"), .btn-primary').all();
    const addButtonFound = addButtons.length > 0;
    console.log(addButtonFound ? '✅ Add Company button found' : '⚠️ No Add Company button found');

    // Look for search functionality
    const searchBox = await page.locator('input[type="search"], input[placeholder*="search"], [data-testid="search"]').first();
    if (await searchBox.isVisible()) {
      console.log('✅ Search functionality found');
    } else {
      console.log('⚠️ No search functionality found');
    }

    // Take screenshot
    await page.screenshot({ path: 'test-results/companies-page.png', fullPage: true });
  });

  test('Test add company functionality', async ({ page }) => {
    console.log('➕ Testing add company functionality...');

    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Find and click Add Company button
    const addSelectors = [
      'button:has-text("Add Company")',
      'button:has-text("Add")',
      'a:has-text("Add Company")',
      '.btn-primary',
      '[data-testid="add-company"]'
    ];

    let addButtonClicked = false;
    for (const selector of addSelectors) {
      const button = page.locator(selector).first();
      if (await button.isVisible()) {
        console.log(`🎯 Clicking add button: ${selector}`);
        await button.click();
        await page.waitForTimeout(1000);
        addButtonClicked = true;
        break;
      }
    }

    if (!addButtonClicked) {
      console.log('❌ No Add Company button found or clickable');
      return;
    }

    // Check if modal or form appears
    const formSelectors = [
      '.modal',
      '.dialog',
      'form',
      '[data-testid="company-form"]'
    ];

    let formFound = false;
    for (const selector of formSelectors) {
      const form = page.locator(selector).first();
      if (await form.isVisible()) {
        console.log(`✅ Company form found: ${selector}`);
        formFound = true;

        // Test form fields
        const expectedFields = [
          { selector: 'input[name="name"], input[id*="name"], [data-testid="company-name"]', label: 'Company Name' },
          { selector: 'input[name="email"], input[id*="email"], [data-testid="company-email"]', label: 'Email' },
          { selector: 'input[name="phone"], input[id*="phone"], [data-testid="company-phone"]', label: 'Phone' },
          { selector: 'input[name="address"], textarea[name="address"], [data-testid="company-address"]', label: 'Address' }
        ];

        for (const field of expectedFields) {
          const fieldElement = page.locator(field.selector).first();
          if (await fieldElement.isVisible()) {
            console.log(`  ✅ ${field.label} field found`);

            // Try to fill the field
            try {
              await fieldElement.fill(`Test ${field.label} Value`);
              console.log(`    ✅ Successfully filled ${field.label}`);
            } catch (error) {
              console.log(`    ⚠️ Could not fill ${field.label}: ${error}`);
            }
          } else {
            console.log(`  ⚠️ ${field.label} field not found`);
          }
        }

        // Look for save/submit button
        const saveButtons = await form.locator('button:has-text("Save"), button:has-text("Submit"), button[type="submit"]').all();
        if (saveButtons.length > 0) {
          console.log('  ✅ Save button found');

          // Try to save (but expect it might fail due to validation)
          try {
            await saveButtons[0].click();
            await page.waitForTimeout(2000);

            // Check for validation errors or success
            const validationErrors = await page.locator('.error, .alert-danger, .validation-error').count();
            const successMessages = await page.locator('.success, .alert-success, .notification-success').count();

            if (validationErrors > 0) {
              console.log(`  ⚠️ Found ${validationErrors} validation errors (expected for test data)`);
            } else if (successMessages > 0) {
              console.log('  ✅ Company saved successfully');
            } else {
              console.log('  ℹ️ No validation or success messages detected');
            }
          } catch (error) {
            console.log(`  ⚠️ Error saving company: ${error}`);
          }
        } else {
          console.log('  ❌ No save button found in form');
        }

        // Try to close modal/form
        const closeButtons = await form.locator('.close, .cancel, [data-testid="close"]').all();
        if (closeButtons.length > 0) {
          await closeButtons[0].click();
          console.log('  ✅ Form closed');
        }

        break;
      }
    }

    if (!formFound) {
      console.log('❌ No company form appeared after clicking add button');
    }

    await page.screenshot({ path: 'test-results/add-company-test.png', fullPage: true });
  });

  test('Test company search and filtering', async ({ page }) => {
    console.log('🔍 Testing company search functionality...');

    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Look for search functionality
    const searchSelectors = [
      'input[type="search"]',
      'input[placeholder*="search"]',
      'input[placeholder*="Search"]',
      '[data-testid="search"]',
      '.search-input'
    ];

    let searchFound = false;
    for (const selector of searchSelectors) {
      const searchBox = page.locator(selector).first();
      if (await searchBox.isVisible()) {
        console.log(`✅ Search box found: ${selector}`);
        searchFound = true;

        // Test search functionality
        try {
          await searchBox.fill('Test');
          await page.waitForTimeout(1000);

          // Check if search results update
          const resultsAfterSearch = await page.locator('tr, .company-item').count();
          console.log(`📊 Found ${resultsAfterSearch} results after searching for "Test"`);

          // Clear search
          await searchBox.clear();
          await page.waitForTimeout(1000);

          console.log('✅ Search functionality working');
        } catch (error) {
          console.log(`⚠️ Error testing search: ${error}`);
        }
        break;
      }
    }

    if (!searchFound) {
      console.log('⚠️ No search functionality found');
    }

    // Look for filter options
    const filterSelectors = [
      'select',
      '.filter',
      '[data-testid="filter"]',
      'button:has-text("Filter")'
    ];

    let filterFound = false;
    for (const selector of filterSelectors) {
      const filter = page.locator(selector).first();
      if (await filter.isVisible()) {
        console.log(`✅ Filter found: ${selector}`);
        filterFound = true;
        break;
      }
    }

    if (!filterFound) {
      console.log('⚠️ No filter functionality found');
    }
  });

  test('Test company list items and actions', async ({ page }) => {
    console.log('📋 Testing company list items and actions...');

    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Look for company items in the list
    const companyItemSelectors = [
      'tr',
      '.company-item',
      '.list-item',
      '[data-testid="company-item"]'
    ];

    let companyItemsFound = false;
    for (const selector of companyItemSelectors) {
      const items = page.locator(selector);
      const itemCount = await items.count();

      if (itemCount > 0) {
        console.log(`✅ Found ${itemCount} company items using selector: ${selector}`);
        companyItemsFound = true;

        // Test first company item
        const firstItem = items.first();

        // Look for action buttons on first item
        const actionButtons = await firstItem.locator('button, a').all();
        if (actionButtons.length > 0) {
          console.log(`  ✅ Found ${actionButtons.length} action buttons on first item`);

          // Try to identify common actions
          for (let i = 0; i < Math.min(actionButtons.length, 3); i++) {
            const button = actionButtons[i];
            const buttonText = await button.textContent();
            if (buttonText) {
              console.log(`    - Button: ${buttonText.trim()}`);
            }
          }
        } else {
          console.log('  ⚠️ No action buttons found on company items');
        }

        // Check for company information display
        const companyText = await firstItem.textContent();
        if (companyText && companyText.length > 5) {
          console.log(`  ✅ Company information displayed: ${companyText.substring(0, 50)}...`);
        }

        break;
      }
    }

    if (!companyItemsFound) {
      console.log('⚠️ No company items found in list - might be empty or different structure');
    }

    await page.screenshot({ path: 'test-results/company-list-test.png', fullPage: true });
  });

  test('Test responsive design on companies page', async ({ page }) => {
    console.log('📱 Testing responsive design for companies page...');

    await page.goto('/companies');
    await page.waitForLoadState('networkidle');

    // Test mobile view
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(1000);
    console.log('📱 Testing mobile view (375x667)');

    // Check if mobile menu appears
    const mobileMenuSelectors = [
      '.mobile-menu-toggle',
      '.hamburger',
      '[data-testid="mobile-menu"]'
    ];

    let mobileMenuFound = false;
    for (const selector of mobileMenuSelectors) {
      if (await page.locator(selector).isVisible()) {
        console.log(`  ✅ Mobile menu found: ${selector}`);
        mobileMenuFound = true;
        break;
      }
    }

    if (!mobileMenuFound) {
      console.log('  ℹ️ No mobile menu toggle found - may use responsive navigation');
    }

    // Test tablet view
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(1000);
    console.log('📱 Testing tablet view (768x1024)');

    // Test desktop view
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(1000);
    console.log('🖥️ Testing desktop view (1920x1080)');

    console.log('✅ Responsive design testing completed');
  });

  test('Check for JavaScript errors and console issues', async ({ page }) => {
    console.log('🐛 Checking for JavaScript errors on companies page...');

    const jsErrors: string[] = [];
    const consoleErrors: string[] = [];

    // Listen for JavaScript errors
    page.on('pageerror', (error) => {
      jsErrors.push(error.message);
    });

    // Listen for console errors
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('/companies');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000); // Wait for any delayed JavaScript

    // Test interactions that might trigger errors
    try {
      // Try clicking around to trigger potential errors
      const clickableElements = await page.locator('button, a, [onclick]').all();
      for (let i = 0; i < Math.min(clickableElements.length, 5); i++) {
        await clickableElements[i].click();
        await page.waitForTimeout(500);
      }
    } catch (error) {
      console.log(`⚠️ Error during interaction testing: ${error}`);
    }

    // Report errors
    if (jsErrors.length > 0) {
      console.log(`❌ Found ${jsErrors.length} JavaScript errors:`);
      jsErrors.forEach((error, index) => {
        console.log(`  ${index + 1}. ${error.substring(0, 150)}...`);
      });
    } else {
      console.log('✅ No JavaScript errors found');
    }

    if (consoleErrors.length > 0) {
      console.log(`⚠️ Found ${consoleErrors.length} console errors:`);
      consoleErrors.forEach((error, index) => {
        console.log(`  ${index + 1}. ${error.substring(0, 150)}...`);
      });
    } else {
      console.log('✅ No console errors found');
    }
  });
});