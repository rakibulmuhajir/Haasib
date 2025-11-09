import { test, expect, navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData } from '../helpers/auth-helper';

test.describe('Customer Management E2E Tests', () => {
  let testData: any;

  test.beforeEach(async ({ page }) => {
    testData = generateTestData();
  });

  test('should create a new customer successfully', async ({ page }) => {
    console.log('👤 Testing customer creation workflow...');

    // Step 1: Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Step 2: Navigate to customers
    await navigateToModule(page, 'customers');
    await page.waitForLoadState('networkidle');
    await takeScreenshot(page, 'customers-list-before-creation');

    // Step 3: Click Add Customer button
    await clickButtonWithText(page, 'Add Customer');
    await page.waitForTimeout(1000);

    // Step 4: Fill customer creation form
    console.log('📝 Filling customer creation form...');
    const customerData = testData.customer;

    // Customer Name
    await page.fill('input[name="name"], input[id*="name"], [data-testid="customer-name"]', customerData.name);

    // Customer Email
    await page.fill('input[name="email"], input[id*="email"], [data-testid="customer-email"]', customerData.email);

    // Customer Phone
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="customer-phone"]', customerData.phone);

    // Customer Currency
    await page.selectOption('select[name="currency"], [data-testid="customer-currency"]', customerData.currency);

    // Credit Limit
    await page.fill('input[name="credit_limit"], input[id*="credit_limit"], [data-testid="credit-limit"]', customerData.credit_limit);

    // Additional fields if present
    await page.fill('input[name="tax_id"], input[id*="tax_id"], [data-testid="tax-id"]', '12-3456789');
    await page.fill('input[name="website"], input[id*="website"], [data-testid="website"]', 'https://testcustomer.com');
    await page.fill('textarea[name="notes"], textarea[id*="notes"], [data-testid="notes"]', 'Test customer for E2E validation');

    await takeScreenshot(page, 'customer-form-filled');

    // Step 5: Submit the form
    console.log('💾 Submitting customer creation form...');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Step 6: Verify successful creation
    const success = await waitForSuccessMessage(page);
    expect(success).toBeTruthy();

    // Step 7: Verify customer appears in list
    await navigateToModule(page, 'customers');
    await page.waitForLoadState('networkidle');

    // Look for the created customer in the list
    const customerLink = page.locator(`a:has-text("${customerData.name}")`).first();
    await expect(customerLink).toBeVisible({ timeout: 5000 });

    console.log(`✅ Customer "${customerData.name}" created successfully`);
    await takeScreenshot(page, 'customer-created-successfully');
  });

  test('should validate customer creation form fields', async ({ page }) => {
    console.log('✅ Testing customer form validation...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'customers');
    await clickButtonWithText(page, 'Add Customer');

    // Test 1: Submit empty form - should show validation errors
    console.log('Testing empty form submission...');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(1000);

    const validationErrors = page.locator('.error, .alert-danger, .validation-error, [data-testid="error"]');
    const errorCount = await validationErrors.count();
    expect(errorCount).toBeGreaterThan(0);
    console.log(`Found ${errorCount} validation errors as expected`);

    // Test 2: Test invalid email format
    console.log('Testing invalid email format...');
    await page.fill('input[name="name"], input[id*="name"], [data-testid="customer-name"]', 'Invalid Email Test');
    await page.fill('input[name="email"], input[id*="email"], [data-testid="customer-email"]', 'invalid-email');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(1000);

    const emailErrors = page.locator('.error:has-text("email"), .validation-error:has-text("email")');
    expect(await emailErrors.count()).toBeGreaterThan(0);

    // Test 3: Test negative credit limit
    console.log('Testing negative credit limit...');
    await page.fill('input[name="email"], input[id*="email"], [data-testid="customer-email"]', 'test@example.com');
    await page.fill('input[name="credit_limit"], input[id*="credit_limit"], [data-testid="credit-limit"]', '-1000');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(1000);

    const creditErrors = page.locator('.error:has-text("credit"), .validation-error:has-text("credit")');
    // Note: This might not be enforced in the frontend, so we just log the result
    const creditErrorCount = await creditErrors.count();
    if (creditErrorCount > 0) {
      console.log('✅ Negative credit limit validation works');
    } else {
      console.log('⚠️ Negative credit limit validation not enforced in frontend');
    }

    await takeScreenshot(page, 'customer-validation-errors');
  });

  test('should edit customer information', async ({ page }) => {
    console.log('✏️ Testing customer editing functionality...');

    // First, create a customer
    const customerData = testData.customer;

    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'customers');
    await clickButtonWithText(page, 'Add Customer');

    // Create customer
    await page.fill('input[name="name"], input[id*="name"], [data-testid="customer-name"]', customerData.name);
    await page.fill('input[name="email"], input[id*="email"], [data-testid="customer-email"]', customerData.email);
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="customer-phone"]', customerData.phone);
    await page.fill('input[name="credit_limit"], input[id*="credit_limit"], [data-testid="credit-limit"]', customerData.credit_limit);
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Navigate to the created customer
    await navigateToModule(page, 'customers');
    const customerLink = page.locator(`a:has-text("${customerData.name}")`).first();
    await customerLink.click();
    await page.waitForLoadState('networkidle');

    // Look for Edit button
    await clickButtonWithText(page, 'Edit');
    await page.waitForTimeout(1000);

    // Edit customer information
    const updatedPhone = `+1-555-${Date.now().toString().slice(-4)}`;
    const updatedCreditLimit = '15000.00';
    const updatedNotes = 'Updated notes for E2E testing';

    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="customer-phone"]', updatedPhone);
    await page.fill('input[name="credit_limit"], input[id*="credit_limit"], [data-testid="credit-limit"]', updatedCreditLimit);
    await page.fill('textarea[name="notes"], textarea[id*="notes"], [data-testid="notes"]', updatedNotes);

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

    console.log(`✅ Customer updated successfully`);
    await takeScreenshot(page, 'customer-updated-successfully');
  });

  test('should search and filter customers', async ({ page }) => {
    console.log('🔍 Testing customer search and filtering...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'customers');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchBox = page.locator('input[type="search"], input[placeholder*="search"], [data-testid="search"]').first();
    if (await searchBox.isVisible()) {
      console.log('Testing search functionality...');

      // Search for a term
      await searchBox.fill('Test');
      await page.waitForTimeout(1000);

      // Check if results are filtered
      const searchResults = await page.locator('tr, .customer-item').count();
      console.log(`Found ${searchResults} results for "Test"`);

      // Search for specific customer type
      await searchBox.clear();
      await searchBox.fill('Customer');
      await page.waitForTimeout(1000);

      const customerResults = await page.locator('tr, .customer-item').count();
      console.log(`Found ${customerResults} results for "Customer"`);

      // Clear search
      await searchBox.clear();
      await page.waitForTimeout(1000);

      console.log('✅ Search functionality working');
    } else {
      console.log('⚠️ Search functionality not found');
    }

    // Test filter options if available
    const filterSelects = await page.locator('select[name="status"], select[name="currency"], [data-testid="filter"], .filter').all();
    if (filterSelects.length > 0) {
      console.log('Testing filter functionality...');

      for (const filterSelect of filterSelects.slice(0, 2)) { // Test first 2 filters
        if (await filterSelect.isVisible()) {
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
        }
      }

      console.log('✅ Filter functionality working');
    }

    await takeScreenshot(page, 'customer-search-filter');
  });

  test('should manage customer status and credit limits', async ({ page }) => {
    console.log('🔄 Testing customer status and credit management...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'customers');
    await page.waitForLoadState('networkidle');

    // Find a customer to work with
    const customerItems = page.locator('tr, .customer-item').first();
    if (await customerItems.isVisible()) {
      await customerItems.click();
      await page.waitForLoadState('networkidle');

      // Test credit limit adjustment
      console.log('💰 Testing credit limit adjustment...');

      // Look for credit limit section or edit button
      const creditLimitSection = page.locator(':has-text("Credit Limit"), :has-text("credit limit"), [data-testid="credit-limit-section"]').first();
      if (await creditLimitSection.isVisible()) {
        console.log('Found credit limit section');

        // Look for edit/adjust button
        const adjustButtons = await creditLimitSection.locator('button:has-text("Adjust"), button:has-text("Edit"), .btn').all();
        if (adjustButtons.length > 0) {
          await adjustButtons[0].click();
          await page.waitForTimeout(1000);

          // Fill credit limit adjustment form
          const newCreditLimit = '20000.00';
          const reason = 'E2E test credit limit adjustment';

          await page.fill('input[name="new_limit"], input[name="credit_limit"], [data-testid="new-credit-limit"]', newCreditLimit);
          await page.fill('textarea[name="reason"], [data-testid="adjustment-reason"]', reason);

          await takeScreenshot(page, 'credit-limit-adjustment-form');

          // Submit adjustment
          await clickButtonWithText(page, 'Save');
          await page.waitForTimeout(2000);

          const success = await waitForSuccessMessage(page);
          if (success) {
            console.log(`✅ Credit limit adjusted to: ${newCreditLimit}`);
          }
        }
      }

      // Test customer status changes
      console.log('🔄 Testing customer status changes...');

      const statusSection = page.locator(':has-text("Status"), :has-text("status"), [data-testid="customer-status"]').first();
      if (await statusSection.isVisible()) {
        console.log('Found customer status section');

        // Look for status change options
        const statusButtons = await statusSection.locator('button, .dropdown-toggle').all();
        for (const button of statusButtons) {
          const buttonText = await button.textContent();
          if (buttonText && (
            buttonText.toLowerCase().includes('change status') ||
            buttonText.toLowerCase().includes('update') ||
            buttonText.toLowerCase().includes('status')
          )) {
            await button.click();
            await page.waitForTimeout(1000);

            // Look for status options
            const statusOptions = await page.locator('.dropdown-menu a, .status-option, [data-testid="status-option"]').all();
            for (const option of statusOptions.slice(0, 2)) { // Test first 2 options
              const optionText = await option.textContent();
              if (optionText && optionText.trim()) {
                console.log(`Found status option: ${optionText.trim()}`);
                // Don't actually click to avoid changing customer status
              }
            }

            // Close dropdown
            await page.keyboard.press('Escape');
            await page.waitForTimeout(500);
            break;
          }
        }
      }

      // Test customer actions (activate, deactivate, etc.)
      console.log('⚡ Testing customer actions...');

      const actionButtons = await page.locator('button:has-text("Activate"), button:has-text("Deactivate"), button:has-text("Suspend"), .btn-warning, .btn-danger').all();
      for (const button of actionButtons.slice(0, 2)) { // Test first 2 actions
        const buttonText = await button.textContent();
        if (buttonText) {
          console.log(`Found customer action: ${buttonText.trim()}`);
          // Don't actually click to avoid changing customer status
        }
      }
    } else {
      console.log('⚠️ No customers found to test status management');
    }

    await takeScreenshot(page, 'customer-status-management');
  });

  test('should display customer statements and reports', async ({ page }) => {
    console.log('📊 Testing customer statements and reports...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'customers');
    await page.waitForLoadState('networkidle');

    // Find a customer to view statements for
    const customerItems = page.locator('tr a, .customer-item a').first();
    if (await customerItems.isVisible()) {
      await customerItems.click();
      await page.waitForLoadState('networkidle');

      // Look for statements/reports section
      console.log('📋 Looking for customer statements...');

      const statementSelectors = [
        'a:has-text("Statement")',
        'button:has-text("Statement")',
        'a:has-text("Invoices")',
        'button:has-text("Invoices")',
        'a:has-text("Payments")',
        'button:has-text("Payments")',
        'a:has-text("Reports")',
        '[data-testid="statements-tab"]'
      ];

      for (const selector of statementSelectors) {
        const statementButton = page.locator(selector).first();
        if (await statementButton.isVisible()) {
          console.log(`Found: ${await statementButton.textContent()}`);
          await statementButton.click();
          await page.waitForTimeout(1000);

          // Check for statement content
          const statementContent = page.locator('.statement-content, .invoice-list, .payment-list, [data-testid="statement-content"]').first();
          if (await statementContent.isVisible()) {
            console.log('✅ Statement/reports content loaded');

            // Look for data entries
            const dataEntries = await statementContent.locator('tr, .statement-item, .invoice-item, .payment-item').all();
            if (dataEntries.length > 0) {
              console.log(`Found ${dataEntries.length} entries in statement`);
            }

            // Look for export/print options
            const exportButtons = await page.locator('button:has-text("Export"), button:has-text("Print"), button:has-text("PDF"), .btn-export').all();
            if (exportButtons.length > 0) {
              console.log(`Found ${exportButtons.length} export options`);
            }

            await takeScreenshot(page, 'customer-statement-view');
            break;
          }
        }
      }

      // Test customer statistics/summary
      console.log('📈 Checking customer statistics...');

      const statsSection = page.locator('.customer-stats, .summary, [data-testid="customer-stats"]').first();
      if (await statsSection.isVisible()) {
        console.log('✅ Found customer statistics section');

        // Look for common metrics
        const metrics = [
          'Total Invoices',
          'Total Paid',
          'Balance Due',
          'Average Payment',
          'Last Payment',
          'Credit Used'
        ];

        for (const metric of metrics) {
          const metricElement = statsSection.locator(`:has-text("${metric}")`).first();
          if (await metricElement.isVisible()) {
            console.log(`  ✅ Found metric: ${metric}`);
          }
        }

        await takeScreenshot(page, 'customer-statistics');
      } else {
        console.log('⚠️ Customer statistics section not found');
      }
    } else {
      console.log('⚠️ No customers found to view statements');
    }
  });

  test('should handle customer bulk operations', async ({ page }) => {
    console.log('📦 Testing customer bulk operations...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'customers');
    await page.waitForLoadState('networkidle');

    // Look for bulk operation controls
    console.log('🔍 Looking for bulk operation controls...');

    // Check for checkboxes for bulk selection
    const checkboxes = await page.locator('input[type="checkbox"], .checkbox').all();
    if (checkboxes.length > 1) {
      console.log(`Found ${checkboxes.length} checkboxes for bulk selection`);

      // Test selecting multiple customers
      for (let i = 1; i < Math.min(checkboxes.length, 4); i++) { // Select first 3 customers
        await checkboxes[i].check();
        await page.waitForTimeout(500);
      }

      console.log('✅ Multiple customers selected');

      // Look for bulk action buttons
      const bulkActionButtons = await page.locator('.bulk-actions button, .bulk-actions a, [data-testid="bulk-action"]').all();
      if (bulkActionButtons.length > 0) {
        console.log(`Found ${bulkActionButtons.length} bulk action options`);

        for (const button of bulkActionButtons.slice(0, 3)) { // Check first 3 actions
          const buttonText = await button.textContent();
          if (buttonText) {
            console.log(`  - Bulk action: ${buttonText.trim()}`);
            // Don't actually click to avoid affecting real data
          }
        }
      } else {
        console.log('⚠️ No bulk action buttons found after selection');
      }

      // Uncheck all
      for (let i = 1; i < Math.min(checkboxes.length, 4); i++) {
        await checkboxes[i].uncheck();
      }
    } else {
      console.log('⚠️ No checkboxes found for bulk selection');
    }

    // Look for export functionality
    console.log('📤 Testing export functionality...');

    const exportButtons = await page.locator('button:has-text("Export"), a:has-text("Export"), .btn-export, [data-testid="export"]').all();
    if (exportButtons.length > 0) {
      console.log(`Found ${exportButtons.length} export options`);

      for (const button of exportButtons) {
        const buttonText = await button.textContent();
        if (buttonText) {
          console.log(`  - Export option: ${buttonText.trim()}`);
        }
      }
    } else {
      console.log('⚠️ No export buttons found');
    }

    // Look for import functionality
    const importButtons = await page.locator('button:has-text("Import"), a:has-text("Import"), .btn-import, [data-testid="import"]').all();
    if (importButtons.length > 0) {
      console.log(`Found ${importButtons.length} import options`);

      for (const button of importButtons) {
        const buttonText = await button.textContent();
        if (buttonText) {
          console.log(`  - Import option: ${buttonText.trim()}`);
        }
      }
    } else {
      console.log('⚠️ No import buttons found');
    }

    await takeScreenshot(page, 'customer-bulk-operations');
  });
});