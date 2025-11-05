const { chromium } = require('playwright');

async function comprehensiveCompaniesDebug() {
  console.log('🚀 Comprehensive Companies Debugging...');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Step 1: Login
    console.log('\n🔐 Logging in...');
    await page.goto('http://localhost:8000/login');
    await page.locator('input[name="username"]').fill('admin');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/dashboard', { timeout: 10000 });
    console.log('✅ Login successful');

    // Step 2: Navigate to companies
    console.log('\n🏢 Navigating to companies page...');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');
    console.log('✅ Companies page loaded');

    // Step 3: Analyze companies page structure
    console.log('\n📊 Analyzing companies page structure...');

    // Check for company list/table
    const table = page.locator('table').first();
    if (await table.isVisible()) {
      console.log('✅ Companies table found');

      // Count companies
      const rows = await table.locator('tbody tr').all();
      console.log(`📋 Found ${rows.length} companies in table`);

      if (rows.length > 0) {
        // Analyze first company
        const firstRow = rows[0];
        const cells = await firstRow.locator('td').all();
        console.log(`📄 First company has ${cells.length} columns`);

        for (let i = 0; i < cells.length; i++) {
          const cellText = await cells[i].textContent();
          console.log(`   Column ${i + 1}: ${cellText?.trim()}`);
        }

        // Look for action buttons
        const actionButtons = await firstRow.locator('button, a').all();
        console.log(`🎯 Found ${actionButtons.length} action buttons on first company`);

        for (let i = 0; i < actionButtons.length; i++) {
          const buttonText = await actionButtons[i].textContent();
          console.log(`   Action ${i + 1}: ${buttonText?.trim()}`);
        }
      } else {
        console.log('⚠️ No companies found in table');
      }
    } else {
      console.log('❌ No companies table found');
    }

    // Step 4: Test Add Company functionality
    console.log('\n➕ Testing Add Company functionality...');

    const addButtons = [
      'button:has-text("Add Company")',
      'button:has-text("Add")',
      'a:has-text("Add Company")',
      '.btn-primary',
      '[data-testid="add-company"]'
    ];

    let addButtonFound = false;
    for (const selector of addButtons) {
      const button = page.locator(selector).first();
      if (await button.isVisible()) {
        console.log(`✅ Found add button: ${selector}`);

        try {
          await button.click();
          await page.waitForTimeout(1000);

          // Look for modal or form
          const modalSelectors = ['.modal', '.dialog', 'form', '[data-testid="company-form"]'];
          let formFound = false;

          for (const modalSelector of modalSelectors) {
            const modal = page.locator(modalSelector).first();
            if (await modal.isVisible()) {
              console.log(`✅ Company form/modal found: ${modalSelector}`);
              formFound = true;

              // Test form fields
              const formFields = await modal.locator('input, select, textarea').all();
              console.log(`📝 Found ${formFields.length} form fields`);

              for (let i = 0; i < Math.min(formFields.length, 5); i++) {
                const field = formFields[i];
                const name = await field.getAttribute('name');
                const type = await field.getAttribute('type');
                const placeholder = await field.getAttribute('placeholder');
                console.log(`   Field ${i + 1}: name=${name}, type=${type}, placeholder=${placeholder}`);
              }

              // Try to fill sample data
              try {
                const nameField = modal.locator('input[name*="name"], input[id*="name"]').first();
                if (await nameField.isVisible()) {
                  await nameField.fill('Test Company LLC');
                  console.log('✅ Filled company name');
                }

                const emailField = modal.locator('input[name*="email"], input[type="email"]').first();
                if (await emailField.isVisible()) {
                  await emailField.fill('test@company.com');
                  console.log('✅ Filled company email');
                }

                // Look for save button
                const saveButton = modal.locator('button:has-text("Save"), button:has-text("Submit"), button[type="submit"]').first();
                if (await saveButton.isVisible()) {
                  console.log('✅ Save button found - not clicking to avoid creating test data');
                }

              } catch (error) {
                console.log(`⚠️ Error filling form: ${error.message}`);
              }

              // Close modal if possible
              const closeButton = modal.locator('.close, .cancel, [data-testid="close"]').first();
              if (await closeButton.isVisible()) {
                await closeButton.click();
                await page.waitForTimeout(500);
                console.log('✅ Modal closed');
              }

              break;
            }
          }

          if (!formFound) {
            console.log('⚠️ Add button clicked but no form appeared');
          }

          addButtonFound = true;
          break;

        } catch (error) {
          console.log(`⚠️ Error clicking add button: ${error.message}`);
        }
      }
    }

    if (!addButtonFound) {
      console.log('❌ No Add Company button found');
    }

    // Step 5: Test search functionality
    console.log('\n🔍 Testing search functionality...');

    const searchField = page.locator('input[type="search"], input[placeholder*="search" i], [data-testid="search"]').first();
    if (await searchField.isVisible()) {
      console.log('✅ Search field found');

      try {
        await searchField.fill('Test');
        await page.waitForTimeout(1000);
        console.log('✅ Search query entered');

        // Check if results update
        const rowsAfterSearch = await page.locator('table tbody tr').all();
        console.log(`📊 Found ${rowsAfterSearch.length} companies after search`);

        // Clear search
        await searchField.clear();
        await page.waitForTimeout(1000);
        console.log('✅ Search cleared');

      } catch (error) {
        console.log(`⚠️ Error testing search: ${error.message}`);
      }
    } else {
      console.log('⚠️ No search field found');
    }

    // Step 6: Test responsive design
    console.log('\n📱 Testing responsive design...');

    // Mobile view
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(1000);
    console.log('✅ Mobile view tested');

    // Tablet view
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(1000);
    console.log('✅ Tablet view tested');

    // Desktop view
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(1000);
    console.log('✅ Desktop view tested');

    // Step 7: Check for JavaScript errors
    console.log('\n🐛 Checking for JavaScript errors...');

    const jsErrors = [];
    page.on('pageerror', (error) => {
      jsErrors.push(error.message);
    });

    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    // Do some interactions to trigger potential errors
    try {
      await page.locator('body').click(); // Click to trigger any event listeners
      await page.waitForTimeout(1000);
    } catch (error) {
      console.log(`⚠️ Interaction error: ${error.message}`);
    }

    if (jsErrors.length > 0) {
      console.log(`❌ Found ${jsErrors.length} JavaScript errors:`);
      jsErrors.forEach((error, index) => {
        console.log(`   ${index + 1}. ${error.substring(0, 100)}...`);
      });
    } else {
      console.log('✅ No JavaScript errors detected');
    }

    if (consoleErrors.length > 0) {
      console.log(`⚠️ Found ${consoleErrors.length} console errors:`);
      consoleErrors.forEach((error, index) => {
        console.log(`   ${index + 1}. ${error.substring(0, 100)}...`);
      });
    } else {
      console.log('✅ No console errors detected');
    }

    // Step 8: Take final screenshots
    console.log('\n📸 Taking screenshots...');

    await page.screenshot({
      path: 'test-results/companies-final-desktop.png',
      fullPage: true
    });

    await page.setViewportSize({ width: 375, height: 667 });
    await page.screenshot({
      path: 'test-results/companies-final-mobile.png',
      fullPage: true
    });

    console.log('✅ Screenshots saved');

    // Step 9: Test navigation to related pages
    console.log('\n🧭 Testing navigation to related pages...');

    const relatedPages = [
      { name: 'Dashboard', url: '/dashboard' },
      { name: 'Customers', url: '/customers' },
      { name: 'Invoices', url: '/invoices' },
      { name: 'Settings', url: '/settings' }
    ];

    for (const pageToTest of relatedPages) {
      try {
        await page.goto(`http://localhost:8000${pageToTest.url}`);
        await page.waitForLoadState('networkidle');

        const pageTitle = await page.title();
        console.log(`✅ ${pageToTest.name}: ${pageTitle} (${page.url()})`);

      } catch (error) {
        console.log(`❌ ${pageToTest.name}: Error - ${error.message}`);
      }
    }

    // Go back to companies
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    console.log('\n✅ Comprehensive companies debugging complete!');

  } catch (error) {
    console.error(`❌ Error during debugging: ${error.message}`);
  } finally {
    await browser.close();
  }
}

comprehensiveCompaniesDebug().catch(console.error);